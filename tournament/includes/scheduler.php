<?php
require_once __DIR__ . '/functions.php';

/**
 * Scheduler
 * ---------
 * Builds one round at a time (never the whole tournament in advance).
 * Building rounds on-demand is what makes the system "dynamic": if a
 * participant is added or removed between rounds, the very next call to
 * generateNextRound() automatically works from the current active roster
 * and current play/judge/idle counts -- there is nothing stale to
 * "recalculate".
 *
 * For each round:
 *   1. Work out how many simultaneous 1v1 matches we can run
 *      (limited by player count AND by available stations).
 *   2. Pick who PLAYS this round: players with the fewest games played
 *      so far get first priority (keeps play counts balanced).
 *   3. From whoever is left, pick who JUDGES: players with the fewest
 *      judging turns so far get first priority (keeps judge counts
 *      balanced). Judges are, by construction, disjoint from the
 *      players picked in step 2, so nobody can ever judge their own
 *      match.
 *   4. Whoever is left after that simply rests this round (idle). Since
 *      steps 2 and 3 are randomised before sorting, idle duty rotates
 *      fairly across rounds rather than always landing on the same kids.
 *   5. Pair the "to play" group Swiss-style: rank by total points,
 *      then match neighbours in the ranking, backtracking whenever a
 *      pairing would repeat a prior opponent, until a matching with no
 *      repeats is found (or, only if truly unavoidable, the pairing
 *      with the fewest repeats).
 *   6. Hand out stations and judges to the resulting pairs.
 */
class Scheduler
{
    private PDO $pdo;
    private int $backtrackAttempts = 0;
    private const MAX_ATTEMPTS = 20000;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Builds the plan for the next round WITHOUT writing to the database.
     * Used for the live "next round preview" on the dashboard.
     */
    public function planNextRound(): array
    {
        return $this->buildPlan();
    }

    /**
     * Builds the plan and commits it as a new round (rounds, matches,
     * round_idles rows). Returns the plan plus the new round id/number.
     */
    public function generateNextRound(): array
    {
        $plan = $this->buildPlan();
        if (!$plan['feasible']) {
            return $plan;
        }

        $this->pdo->beginTransaction();
        try {
            $lastRound = $this->pdo->query('SELECT MAX(round_number) rn FROM tournament_rounds')->fetch();
            $roundNumber = ((int)($lastRound['rn'] ?? 0)) + 1;
            $duration = (int)getSetting($this->pdo, 'round_duration_seconds', DEFAULT_ROUND_SECONDS);

            $stmt = $this->pdo->prepare('
                INSERT INTO tournament_rounds (round_number, status, duration_seconds, started_at, ends_at)
                VALUES (?, "active", ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
            ');
            $stmt->execute([$roundNumber, $duration, $duration]);
            $roundId = (int)$this->pdo->lastInsertId();

            $insMatch = $this->pdo->prepare('
                INSERT INTO tournament_matches (round_id, station_id, player_a_id, player_b_id, judge_id, result_type)
                VALUES (?, ?, ?, ?, ?, "pending")
            ');
            foreach ($plan['matches'] as $m) {
                $insMatch->execute([$roundId, $m['station_id'], $m['player_a_id'], $m['player_b_id'], $m['judge_id']]);
            }

            $insIdle = $this->pdo->prepare('INSERT INTO tournament_round_idles (round_id, player_id) VALUES (?, ?)');
            foreach ($plan['idle'] as $p) {
                $insIdle->execute([$roundId, $p['id']]);
            }

            setSetting($this->pdo, 'current_round_id', (string)$roundId);
            setSetting($this->pdo, 'tournament_status', 'running');

            $this->pdo->commit();

            $plan['round_id'] = $roundId;
            $plan['round_number'] = $roundNumber;
            return $plan;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function buildPlan(): array
    {
        $players = getActivePlayers($this->pdo);
        $stats = getPlayerStats($this->pdo);
        $stations = getActiveStations($this->pdo);
        $opponentMap = buildOpponentMap($this->pdo);

        $n = count($players);
        if ($n < 3) {
            return [
                'feasible' => false,
                'reason' => "Minstens 3 actieve deelnemers nodig voor een wedstrijd (nu: {$n}).",
                'matches' => [], 'idle' => [],
            ];
        }

        $maxStations = count($stations);
        if ($maxStations < 1) {
            return ['feasible' => false, 'reason' => 'Geen actieve stations beschikbaar.', 'matches' => [], 'idle' => []];
        }

        // Each match needs exactly 3 kids (2 players + 1 judge). Cap by stations too.
        $matchCount = min(intdiv($n, 3), $maxStations, STATIONS_MAX);
        if ($matchCount < 1) {
            return ['feasible' => false, 'reason' => 'Nog niet genoeg actieve deelnemers voor een volledige wedstrijd.', 'matches' => [], 'idle' => []];
        }

        $roster = [];
        foreach ($players as $p) {
            $roster[] = $stats[$p['id']];
        }

        // Randomise first so that ties are broken differently every round
        // (this is what keeps idle duty rotating instead of always hitting
        // the same kids).
        shuffle($roster);

        // Step 2: fewest games played so far -> play this round.
        usort($roster, fn($a, $b) => $a['play_count'] <=> $b['play_count']);
        $toPlay = array_slice($roster, 0, $matchCount * 2);
        $rest = array_slice($roster, $matchCount * 2);

        // Step 3: fewest judging turns so far -> judge this round.
        shuffle($rest);
        usort($rest, fn($a, $b) => $a['judge_count'] <=> $b['judge_count']);
        $judges = array_slice($rest, 0, $matchCount);
        $idle = array_slice($rest, $matchCount);

        // Step 5: Swiss-style pairing of the "to play" group.
        $ranked = $toPlay;
        usort($ranked, fn($a, $b) => $b['points'] <=> $a['points']);
        $pairs = $this->pairPlayers($ranked, $opponentMap);

        // Step 6: hand out stations & judges.
        shuffle($stations);
        shuffle($judges);

        $matches = [];
        foreach ($pairs as $i => $pair) {
            $matches[] = [
                'player_a_id'      => $pair[0]['id'],
                'player_a_name'    => $pair[0]['name'],
                'player_b_id'      => $pair[1]['id'],
                'player_b_name'    => $pair[1]['name'],
                'judge_id'         => $judges[$i]['id'],
                'judge_name'       => $judges[$i]['name'],
                'station_id'       => $stations[$i]['id'],
                'station_name'     => $stations[$i]['name'],
                'repeat_opponents' => ($opponentMap[$pair[0]['id']][$pair[1]['id']] ?? 0) > 0,
            ];
        }

        return [
            'feasible'    => true,
            'matches'     => $matches,
            'idle'        => array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $idle),
            'total_active'=> $n,
            'match_count' => $matchCount,
        ];
    }

    /**
     * Pairs a ranked list of players into 1v1 matches, preferring
     * opponents who have never played each other (classic Swiss
     * backtracking pairing). Falls back to a repeat pairing only when
     * genuinely unavoidable.
     */
    private function pairPlayers(array $ranked, array $opponentMap): array
    {
        $this->backtrackAttempts = 0;
        $result = $this->solvePairs($ranked, $opponentMap);
        if ($result === null) {
            // Should only happen in pathological edge cases; guarantees
            // we always produce a valid set of pairs.
            $result = [];
            for ($i = 0; $i < count($ranked); $i += 2) {
                $result[] = [$ranked[$i], $ranked[$i + 1]];
            }
        }
        return $result;
    }

    private function solvePairs(array $remaining, array $opponentMap, array $pairs = []): ?array
    {
        if (empty($remaining)) {
            return $pairs;
        }
        if (++$this->backtrackAttempts > self::MAX_ATTEMPTS) {
            return null;
        }

        $first = array_shift($remaining);

        // Pass 1: try every candidate that has never played $first.
        foreach ($remaining as $i => $candidate) {
            if (($opponentMap[$first['id']][$candidate['id']] ?? 0) === 0) {
                $newRemaining = $remaining;
                unset($newRemaining[$i]);
                $attempt = $this->solvePairs(array_values($newRemaining), $opponentMap, [...$pairs, [$first, $candidate]]);
                if ($attempt !== null) {
                    return $attempt;
                }
            }
        }

        // Pass 2: no repeat-free completion exists from here; allow a
        // repeat, trying the least-repeated opponents first.
        $ordered = $remaining;
        usort($ordered, fn($a, $b) =>
            ($opponentMap[$first['id']][$a['id']] ?? 0) <=> ($opponentMap[$first['id']][$b['id']] ?? 0)
        );
        foreach ($ordered as $candidate) {
            $idx = array_search($candidate, $remaining, true);
            $newRemaining = $remaining;
            unset($newRemaining[$idx]);
            $attempt = $this->solvePairs(array_values($newRemaining), $opponentMap, [...$pairs, [$first, $candidate]]);
            if ($attempt !== null) {
                return $attempt;
            }
        }

        return null;
    }
}
