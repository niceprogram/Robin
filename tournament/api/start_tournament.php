<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/scheduler.php';

$pdo = getPDO();

$status = getSetting($pdo, 'tournament_status', 'not_started');
if ($status !== 'not_started') {
    jsonResponse(['success' => false, 'error' => 'The tournament has already been started.'], 400);
}

$scheduler = new Scheduler($pdo);
$plan = $scheduler->generateNextRound();

if (!$plan['feasible']) {
    jsonResponse(['success' => false, 'error' => $plan['reason']], 400);
}

jsonResponse(['success' => true, 'round_number' => $plan['round_number'], 'round_id' => $plan['round_id']]);
