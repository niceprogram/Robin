<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$confirm = (bool)($input['confirm'] ?? false);

if (!$confirm) {
    jsonResponse(['success' => false, 'error' => 'Bevestiging vereist.'], 400);
}

try {
    // DELETE (not TRUNCATE) on purpose: many shared-hosting MySQL users
    // only have the DELETE privilege, not DROP, which TRUNCATE requires.
    // Deleting child rows before parent rows respects foreign keys
    // naturally, with no need to touch FOREIGN_KEY_CHECKS at all.
    // Player records are never touched by a reset.
    $pdo->exec('DELETE FROM tournament_matches');
    $pdo->exec('DELETE FROM tournament_round_idles');
    $pdo->exec('DELETE FROM tournament_rounds');

    setSetting($pdo, 'tournament_status', 'not_started');
    setSetting($pdo, 'current_round_id', '');
} catch (Throwable $e) {
    jsonResponse(['success' => false, 'error' => 'Reset mislukt: ' . $e->getMessage()], 500);
}

jsonResponse(['success' => true]);
