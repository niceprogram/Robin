<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$totalRounds = (int)($input['total_rounds'] ?? -1);

if ($totalRounds < 0) {
    jsonResponse(['success' => false, 'error' => 'total_rounds moet 0 of een positief getal zijn.'], 400);
}

// 0 means "no plan set" (hides the progress/estimate on the dashboard).
setSetting($pdo, 'total_rounds', (string)$totalRounds);

jsonResponse(['success' => true, 'total_rounds' => $totalRounds]);
