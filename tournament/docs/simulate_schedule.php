<?php
/**
 * Simulates a full tournament with the current active players, generating
 * round after round and recording RANDOM (but valid) results after each
 * round, so subsequent rounds have realistic standings to pair against.
 *
 * Usage (from XAMPP or CLI, DB already seeded):
 *   php docs/simulate_schedule.php 10
 *
 * This is a demonstration/documentation tool only — it is not part of the
 * live application.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scheduler.php';

$pdo = getPDO();
$rounds = (int)($argv[1] ?? 10);

// Make sure we start fresh for a clean demonstration.
$pdo->exec('DELETE FROM tournament_matches');
$pdo->exec('DELETE FROM tournament_round_idles');
$pdo->exec('DELETE FROM tournament_rounds');
setSetting($pdo, 'tournament_status', 'not_started');

$scheduler = new Scheduler($pdo);
$possibleResults = ['win_a', 'win_b', 'draw', 'fault'];

echo "==================================================================\n";
echo " SAMPLE " . $rounds . "-ROUND SCHEDULE — " . count(getActivePlayers($pdo)) . " players\n";
echo "==================================================================\n\n";

for ($r = 1; $r <= $rounds; $r++) {
    $plan = $scheduler->generateNextRound();

    if (!$plan['feasible']) {
        echo "Round $r could not be generated: {$plan['reason']}\n";
        break;
    }

    echo "----- ROUND {$plan['round_number']} ----- ({$plan['match_count']} matches, " . count($plan['idle']) . " resting)\n";
    foreach ($plan['matches'] as $m) {
        $repeatFlag = $m['repeat_opponents'] ? '  [REMATCH]' : '';
        printf(
            "  [%-18s] %-10s vs %-10s   judge: %-10s%s\n",
            $m['station_name'],
            $m['player_a_name'],
            $m['player_b_name'],
            $m['judge_name'],
            $repeatFlag
        );
    }
    if ($plan['idle']) {
        echo '  Resting: ' . implode(', ', array_map(fn($p) => $p['name'], $plan['idle'])) . "\n";
    }
    echo "\n";

    // Record a random-but-plausible result for every match so the next
    // round's Swiss pairing has real standings to work with.
    $matches = getRoundMatches($pdo, $plan['round_id']);
    foreach ($matches as $m) {
        $result = $possibleResults[array_rand($possibleResults)];
        // Faulty games should be rare in real life; weight the random draw.
        if ($result === 'fault' && random_int(1, 100) > 15) {
            $result = random_int(0, 1) ? 'win_a' : 'win_b';
        }
        recordMatchResult($pdo, (int)$m['id'], $result);
    }
}

echo "==================================================================\n";
echo " FINAL LEADERBOARD AFTER $rounds ROUNDS\n";
echo "==================================================================\n";
printf("%-4s %-10s %-7s %-5s %-5s %-6s %-6s %-6s %-6s\n", 'Rank', 'Name', 'Points', 'W', 'L', 'Draw', 'Judge', 'Played', 'Idle');
foreach (getLeaderboard($pdo) as $row) {
    printf(
        "%-4s %-10s %-7s %-5s %-5s %-6s %-6s %-6s %-6s\n",
        $row['rank'], $row['name'], $row['points'], $row['wins'], $row['losses'],
        $row['draws'], $row['judge_count'], $row['play_count'], $row['idle_count']
    );
}

echo "\n==================================================================\n";
echo " FAIRNESS CHECK (play_count / judge_count / idle_count spread)\n";
echo "==================================================================\n";
$stats = array_values(getPlayerStats($pdo));
$plays = array_column($stats, 'play_count');
$judges = array_column($stats, 'judge_count');
$idles = array_column($stats, 'idle_count');
printf("play_count : min=%d max=%d (spread of %d)\n", min($plays), max($plays), max($plays) - min($plays));
printf("judge_count: min=%d max=%d (spread of %d)\n", min($judges), max($judges), max($judges) - min($judges));
printf("idle_count : min=%d max=%d (spread of %d)\n", min($idles), max($idles), max($idles) - min($idles));
