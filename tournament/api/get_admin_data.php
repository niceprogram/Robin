<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();

$tournamentStatus = getSetting($pdo, 'tournament_status', 'not_started');
$round = getCurrentRound($pdo);

$data = [
    'tournament_status'      => $tournamentStatus,
    'round'                  => null,
    'matches'                => [],
    'idle_players'           => [],
    'missing_count'          => 0,
    'round_complete'         => false,
    'can_start_tournament'   => $tournamentStatus === 'not_started',
    'can_start_next_round'   => false,
    'players'                => getAllPlayers($pdo),
    'active_player_count'    => count(getActivePlayers($pdo)),
    'total_rounds'           => null,
    'estimated_seconds_left' => null,
    'round_duration_seconds' => (int)getSetting($pdo, 'round_duration_seconds', DEFAULT_ROUND_SECONDS),
];

$progress = getTournamentProgress($pdo, $round);
$data['total_rounds'] = $progress['total_rounds'];
$data['estimated_seconds_left'] = $progress['estimated_seconds_left'];

if ($round) {
    $matches = getRoundMatches($pdo, (int)$round['id']);
    $missing = 0;
    foreach ($matches as $m) {
        if ($m['result_type'] === 'pending') $missing++;
    }

    $data['round'] = [
        'id'                => (int)$round['id'],
        'round_number'      => (int)$round['round_number'],
        'status'            => $round['status'],
        'duration_seconds'  => (int)$round['duration_seconds'],
        'remaining_seconds' => remainingSeconds($round),
    ];
    $data['matches']        = $matches;
    $data['idle_players']   = getRoundIdlePlayers($pdo, (int)$round['id']);
    $data['missing_count']  = $missing;
    $data['round_complete'] = $missing === 0;
    $data['can_start_next_round'] = $tournamentStatus === 'running' && $missing === 0;
}

jsonResponse($data);
