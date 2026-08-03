<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scheduler.php';

$pdo = getPDO();

$tournamentStatus = getSetting($pdo, 'tournament_status', 'not_started');
$round = getCurrentRound($pdo);

$data = [
    'tournament_status'   => $tournamentStatus,
    'round'               => null,
    'matches'             => [],
    'idle_players'        => [],
    'round_complete'      => false,
    'leaderboard'         => getLeaderboard($pdo),
    'next_round_preview'  => null,
    'total_rounds'            => null,
    'estimated_seconds_left'  => null,
];

$progress = getTournamentProgress($pdo, $round);
$data['total_rounds'] = $progress['total_rounds'];
$data['estimated_seconds_left'] = $progress['estimated_seconds_left'];

if ($round) {
    $data['round'] = [
        'id'                => (int)$round['id'],
        'round_number'      => (int)$round['round_number'],
        'status'            => $round['status'],
        'duration_seconds'  => (int)$round['duration_seconds'],
        'remaining_seconds' => remainingSeconds($round),
    ];
    $data['matches']        = getRoundMatches($pdo, (int)$round['id']);
    $data['idle_players']   = getRoundIdlePlayers($pdo, (int)$round['id']);
    $data['round_complete'] = isRoundFullyDecided($pdo, (int)$round['id']);
}

// Always compute a live preview of the next round (provisional — it can
// shift slightly if pending results change before the round is started).
if ($tournamentStatus !== 'not_started' || !$round) {
    $scheduler = new Scheduler($pdo);
    $data['next_round_preview'] = $scheduler->planNextRound();
}

jsonResponse($data);
