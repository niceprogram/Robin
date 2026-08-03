<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$matchId = (int)($input['match_id'] ?? 0);
$result  = (string)($input['result'] ?? '');

if (!$matchId || $result === '') {
    jsonResponse(['success' => false, 'error' => 'match_id and result are required.'], 400);
}

try {
    $out = recordMatchResult($pdo, $matchId, $result);
    jsonResponse(['success' => true] + $out);
} catch (InvalidArgumentException|RuntimeException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
