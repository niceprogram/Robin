<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$name = trim((string)($input['name'] ?? ''));
if ($name === '') {
    jsonResponse(['success' => false, 'error' => 'Naam is verplicht.'], 400);
}

$stmt = $pdo->prepare('INSERT INTO tournament_players (name, active) VALUES (?, 1)');
$stmt->execute([$name]);

jsonResponse(['success' => true, 'player_id' => (int)$pdo->lastInsertId(), 'name' => $name]);
