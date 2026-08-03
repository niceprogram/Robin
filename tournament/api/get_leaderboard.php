<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();

jsonResponse([
    'leaderboard' => getLeaderboard($pdo),
    'tournament_status' => getSetting($pdo, 'tournament_status', 'not_started'),
]);
