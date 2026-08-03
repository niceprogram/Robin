<?php
require_once __DIR__ . '/db.php';

/* -----------------------------------------------------------------
 * Generic helpers
 * ---------------------------------------------------------------*/

function jsonResponse($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    return is_array($json) ? $json : $_POST;
}

/* -----------------------------------------------------------------
 * settings (key/value store)
 * ---------------------------------------------------------------*/

function getSetting(PDO $pdo, string $key, $default = null)
{
    $stmt = $pdo->prepare('SELECT setting_value FROM tournament_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function setSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('
        INSERT INTO tournament_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ');
    $stmt->execute([$key, $value]);
}

/* -----------------------------------------------------------------
 * players / stations
 * ---------------------------------------------------------------*/

function getActivePlayers(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM tournament_players WHERE active = 1 ORDER BY name')->fetchAll();
}

function getAllPlayers(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM tournament_players ORDER BY active DESC, name')->fetchAll();
}

function getActiveStations(PDO $pdo): array
{
    return $pdo->query('SELECT id, name FROM tournament_stations WHERE active = 1 ORDER BY name')->fetchAll();
}

/* -----------------------------------------------------------------
 * Scoring
 * ---------------------------------------------------------------*/

/**
 * Returns [points_a, points_b, points_judge] for a given match result.
 *   win_a  -> A wins (3 / 1 / 2)
 *   win_b  -> B wins (1 / 3 / 2)
 *   draw   -> both players get 2, judge gets 2
 *   fault  -> faulty game, both players get 0, judge still gets 2 for judging
 */
function computeScoring(string $resultType): array
{
    switch ($resultType) {
        case 'win_a': return [3, 1, 2];
        case 'win_b': return [1, 3, 2];
        case 'draw':  return [2, 2, 2];
        case 'fault': return [0, 0, 2];
        default:      return [null, null, null];
    }
}

/**
 * Writes (or overwrites) the result of a match, recomputing points,
 * and keeps the parent round's status in sync.
 */
function recordMatchResult(PDO $pdo, int $matchId, string $resultType): array
{
    $validResults = ['win_a', 'win_b', 'draw', 'fault'];
    if (!in_array($resultType, $validResults, true)) {
        throw new InvalidArgumentException('Ongeldig resultaattype.');
    }

    $stmt = $pdo->prepare('SELECT * FROM tournament_matches WHERE id = ?');
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        throw new RuntimeException('Wedstrijd niet gevonden.');
    }

    [$pa, $pb, $pj] = computeScoring($resultType);

    $upd = $pdo->prepare('
        UPDATE tournament_matches
        SET result_type = ?, points_a = ?, points_b = ?, points_judge = ?, decided_at = NOW()
        WHERE id = ?
    ');
    $upd->execute([$resultType, $pa, $pb, $pj, $matchId]);

    $roundId = (int)$match['round_id'];
    $roundComplete = isRoundFullyDecided($pdo, $roundId);

    if ($roundComplete) {
        $pdo->prepare("UPDATE tournament_rounds SET status = 'completed' WHERE id = ? AND status <> 'completed'")
            ->execute([$roundId]);
    } else {
        // Correcting a result inside a round that had already been marked
        // complete re-opens it (e.g. admin fixed a mistake after the fact).
        $pdo->prepare("UPDATE tournament_rounds SET status = 'active' WHERE id = ? AND status = 'completed'")
            ->execute([$roundId]);
    }

    return ['match_id' => $matchId, 'round_id' => $roundId, 'round_complete' => $roundComplete];
}

/* -----------------------------------------------------------------
 * Rounds
 * ---------------------------------------------------------------*/

function getCurrentRound(PDO $pdo): ?array
{
    // TIMESTAMPDIFF is computed by MySQL itself (using NOW() on the same
    // server that wrote started_at/ends_at), which avoids any mismatch
    // between PHP's and MySQL's configured timezones.
    $row = $pdo->query('
        SELECT *, TIMESTAMPDIFF(SECOND, NOW(), ends_at) AS seconds_left_calc
        FROM tournament_rounds ORDER BY round_number DESC LIMIT 1
    ')->fetch();
    return $row ?: null;
}

function getRoundMatches(PDO $pdo, int $roundId): array
{
    $stmt = $pdo->prepare('
        SELECT m.*,
               pa.name AS player_a_name, pb.name AS player_b_name, pj.name AS judge_name,
               s.name  AS station_name
        FROM tournament_matches m
        JOIN tournament_players pa ON pa.id = m.player_a_id
        JOIN tournament_players pb ON pb.id = m.player_b_id
        JOIN tournament_players pj ON pj.id = m.judge_id
        JOIN tournament_stations s ON s.id = m.station_id
        WHERE m.round_id = ?
        ORDER BY s.name
    ');
    $stmt->execute([$roundId]);
    return $stmt->fetchAll();
}

function getRoundIdlePlayers(PDO $pdo, int $roundId): array
{
    $stmt = $pdo->prepare('
        SELECT p.id, p.name
        FROM tournament_round_idles ri
        JOIN tournament_players p ON p.id = ri.player_id
        WHERE ri.round_id = ?
        ORDER BY p.name
    ');
    $stmt->execute([$roundId]);
    return $stmt->fetchAll();
}

function isRoundFullyDecided(PDO $pdo, int $roundId): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) c FROM tournament_matches WHERE round_id = ? AND result_type = 'pending'");
    $stmt->execute([$roundId]);
    return (int)$stmt->fetch()['c'] === 0;
}

/**
 * Progress info for the "Round X of Y" + "~N minutes left" display.
 * total_rounds is an optional admin-set target (settings key
 * 'total_rounds'); if it hasn't been set (0/blank), progress/estimate
 * are both returned as null and the UI simply hides them.
 */
function getTournamentProgress(PDO $pdo, ?array $round): array
{
    $totalRounds = (int)getSetting($pdo, 'total_rounds', 0);
    $roundDuration = (int)getSetting($pdo, 'round_duration_seconds', DEFAULT_ROUND_SECONDS);

    if ($totalRounds <= 0) {
        return [
            'total_rounds' => null,
            'estimated_seconds_left' => null,
        ];
    }

    if (!$round) {
        // Tournament hasn't started: estimate the whole plan.
        return [
            'total_rounds' => $totalRounds,
            'estimated_seconds_left' => $totalRounds * $roundDuration,
        ];
    }

    $currentNumber = (int)$round['round_number'];
    $remainingCurrent = remainingSeconds($round);
    $futureRounds = max(0, $totalRounds - $currentNumber);
    $estimate = $remainingCurrent + ($futureRounds * $roundDuration);

    return [
        'total_rounds' => $totalRounds,
        'estimated_seconds_left' => max(0, $estimate),
    ];
}

/**
 * Seconds remaining on the clock for a round, based on its status.
 */
function remainingSeconds(array $round): int
{
    if ($round['status'] === 'paused') {
        return (int)$round['paused_remaining_seconds'];
    }
    if ($round['status'] === 'completed') {
        return 0;
    }
    // seconds_left_calc is computed by MySQL (see getCurrentRound()) so it
    // is immune to PHP/MySQL timezone configuration differences.
    if (array_key_exists('seconds_left_calc', $round)) {
        return max(0, (int)$round['seconds_left_calc']);
    }
    // Fallback if this array didn't come from getCurrentRound().
    return max(0, strtotime($round['ends_at']) - time());
}

/* -----------------------------------------------------------------
 * Statistics (drives both the scheduler and the leaderboard)
 * ---------------------------------------------------------------*/

/**
 * Returns per-player statistics keyed by player id:
 * play_count, judge_count, idle_count, wins, losses, draws, faults,
 * points, games_played.
 *
 * play_count / judge_count include matches that have been *assigned*
 * (even if the result is still pending) because the scheduler needs to
 * know how many times someone has already played/judged, not just how
 * many finished matches they have.
 */
function getPlayerStats(PDO $pdo): array
{
    $players = $pdo->query('SELECT id, name, active FROM tournament_players')->fetchAll();
    $stats = [];
    foreach ($players as $p) {
        $stats[$p['id']] = [
            'id' => (int)$p['id'],
            'name' => $p['name'],
            'active' => (int)$p['active'],
            'play_count' => 0,
            'judge_count' => 0,
            'idle_count' => 0,
            'wins' => 0,
            'losses' => 0,
            'draws' => 0,
            'faults' => 0,
            'points' => 0,
            'games_played' => 0,
        ];
    }

    $matches = $pdo->query('
        SELECT player_a_id, player_b_id, judge_id, result_type, points_a, points_b, points_judge
        FROM tournament_matches
    ')->fetchAll();

    foreach ($matches as $m) {
        $a = $m['player_a_id'];
        $b = $m['player_b_id'];
        $j = $m['judge_id'];

        if (isset($stats[$a])) $stats[$a]['play_count']++;
        if (isset($stats[$b])) $stats[$b]['play_count']++;
        if (isset($stats[$j])) $stats[$j]['judge_count']++;

        if ($m['result_type'] !== 'pending') {
            if (isset($stats[$a])) {
                $stats[$a]['points'] += (int)$m['points_a'];
                $stats[$a]['games_played']++;
            }
            if (isset($stats[$b])) {
                $stats[$b]['points'] += (int)$m['points_b'];
                $stats[$b]['games_played']++;
            }
            if (isset($stats[$j])) {
                $stats[$j]['points'] += (int)$m['points_judge'];
            }

            switch ($m['result_type']) {
                case 'win_a':
                    if (isset($stats[$a])) $stats[$a]['wins']++;
                    if (isset($stats[$b])) $stats[$b]['losses']++;
                    break;
                case 'win_b':
                    if (isset($stats[$b])) $stats[$b]['wins']++;
                    if (isset($stats[$a])) $stats[$a]['losses']++;
                    break;
                case 'draw':
                    if (isset($stats[$a])) $stats[$a]['draws']++;
                    if (isset($stats[$b])) $stats[$b]['draws']++;
                    break;
                case 'fault':
                    if (isset($stats[$a])) $stats[$a]['faults']++;
                    if (isset($stats[$b])) $stats[$b]['faults']++;
                    break;
            }
        }
    }

    $idles = $pdo->query('SELECT player_id, COUNT(*) c FROM tournament_round_idles GROUP BY player_id')->fetchAll();
    foreach ($idles as $row) {
        if (isset($stats[$row['player_id']])) {
            $stats[$row['player_id']]['idle_count'] = (int)$row['c'];
        }
    }

    return $stats;
}

/**
 * Symmetric map of how many times each pair of players has already met:
 * $map[playerIdA][playerIdB] = count
 */
function buildOpponentMap(PDO $pdo): array
{
    $map = [];
    $rows = $pdo->query('SELECT player_a_id, player_b_id FROM tournament_matches')->fetchAll();
    foreach ($rows as $r) {
        $a = (int)$r['player_a_id'];
        $b = (int)$r['player_b_id'];
        $map[$a][$b] = ($map[$a][$b] ?? 0) + 1;
        $map[$b][$a] = ($map[$b][$a] ?? 0) + 1;
    }
    return $map;
}

/**
 * Full leaderboard, sorted with the tie-break rules:
 *   1) total points (desc)
 *   2) wins (desc)
 *   3) draws (desc)
 *   4) losses (asc)
 *   5) name (alphabetical, final deterministic tiebreak)
 */
function getLeaderboard(PDO $pdo): array
{
    $stats = array_values(getPlayerStats($pdo));
    usort($stats, function ($a, $b) {
        if ($a['points'] !== $b['points']) return $b['points'] <=> $a['points'];
        if ($a['wins'] !== $b['wins']) return $b['wins'] <=> $a['wins'];
        if ($a['draws'] !== $b['draws']) return $b['draws'] <=> $a['draws'];
        if ($a['losses'] !== $b['losses']) return $a['losses'] <=> $b['losses'];
        return strcmp($a['name'], $b['name']);
    });
    $rank = 1;
    foreach ($stats as &$row) {
        $row['rank'] = $rank++;
    }
    return $stats;
}
