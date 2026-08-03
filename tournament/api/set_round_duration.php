<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$minutes = (float)($input['minutes'] ?? 0);

if ($minutes <= 0 || $minutes > 60) {
    jsonResponse(['success' => false, 'error' => 'Ronde duur moet tussen 1 en 60 minuten liggen.'], 400);
}

$seconds = (int)round($minutes * 60);
setSetting($pdo, 'round_duration_seconds', (string)$seconds);

// Note: this only affects rounds generated from now on. The round
// currently in progress (if any) keeps the duration it already started
// with, since its ends_at timestamp was fixed at the moment it began.
jsonResponse(['success' => true, 'round_duration_seconds' => $seconds]);
