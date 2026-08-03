<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$id = (int)($input['player_id'] ?? 0);
$name = trim((string)($input['name'] ?? ''));

if (!$id || $name === '') {
    jsonResponse(['success' => false, 'error' => 'player_id en naam zijn verplicht.'], 400);
}

$pdo->prepare('UPDATE tournament_players SET name = ? WHERE id = ?')->execute([$name, $id]);

jsonResponse(['success' => true, 'player_id' => $id, 'name' => $name]);
