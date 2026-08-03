<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$round = getCurrentRound($pdo);

if (!$round || $round['status'] === 'completed') {
    jsonResponse(['success' => false, 'error' => 'Er is geen actieve ronde om te pauzeren of hervatten.'], 400);
}

if ($round['status'] === 'active') {
    $remaining = remainingSeconds($round);
    $pdo->prepare('UPDATE tournament_rounds SET status = "paused", paused_remaining_seconds = ? WHERE id = ?')
        ->execute([$remaining, $round['id']]);
    jsonResponse(['success' => true, 'status' => 'paused', 'remaining_seconds' => $remaining]);
}

if ($round['status'] === 'paused') {
    $remaining = (int)$round['paused_remaining_seconds'];
    $pdo->prepare('
        UPDATE tournament_rounds
        SET status = "active", ends_at = DATE_ADD(NOW(), INTERVAL ? SECOND), paused_remaining_seconds = NULL
        WHERE id = ?
    ')->execute([$remaining, $round['id']]);
    jsonResponse(['success' => true, 'status' => 'active', 'remaining_seconds' => $remaining]);
}
