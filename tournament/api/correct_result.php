<?php
require_once __DIR__ . '/../includes/functions.php';

// Functionally identical to submit_result.php (both overwrite a match's
// result), kept as a separate endpoint to match the "Correct result"
// admin action and to make the audit trail/intent explicit in the code.

$pdo = getPDO();
$input = readJsonInput();

$matchId = (int)($input['match_id'] ?? 0);
$result  = (string)($input['result'] ?? '');

if (!$matchId || $result === '') {
    jsonResponse(['success' => false, 'error' => 'match_id en result zijn verplicht.'], 400);
}

try {
    $out = recordMatchResult($pdo, $matchId, $result);
    jsonResponse(['success' => true] + $out);
} catch (InvalidArgumentException|RuntimeException $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
