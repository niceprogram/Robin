<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$confirm = (bool)($input['confirm'] ?? false);
$clearPlayers = (bool)($input['clear_players'] ?? false);

if (!$confirm) {
    jsonResponse(['success' => false, 'error' => 'Bevestiging vereist.'], 400);
}

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE tournament_matches');
    $pdo->exec('TRUNCATE TABLE tournament_round_idles');
    $pdo->exec('TRUNCATE TABLE tournament_rounds');
    if ($clearPlayers) {
        $pdo->exec('TRUNCATE TABLE tournament_players');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    setSetting($pdo, 'tournament_status', 'not_started');
    setSetting($pdo, 'current_round_id', '');
} catch (Throwable $e) {
    // Note: TRUNCATE auto-commits in MySQL/MariaDB, so there is no
    // transaction to roll back here — if a statement above already
    // succeeded, its effect is already permanent. We simply report the
    // failure so the admin can retry (safe to retry: every statement
    // above is idempotent).
    jsonResponse(['success' => false, 'error' => 'Reset mislukt: ' . $e->getMessage()], 500);
}

jsonResponse(['success' => true, 'cleared_players' => $clearPlayers]);
