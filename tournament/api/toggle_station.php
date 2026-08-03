<?php
require_once __DIR__ . '/../includes/functions.php';

$pdo = getPDO();
$input = readJsonInput();

$id = (int)($input['station_id'] ?? 0);
$active = array_key_exists('active', $input) ? (int)!!$input['active'] : null;

if (!$id || $active === null) {
    jsonResponse(['success' => false, 'error' => 'station_id en active zijn verplicht.'], 400);
}

$pdo->prepare('UPDATE tournament_stations SET active = ? WHERE id = ?')->execute([$active, $id]);

jsonResponse(['success' => true]);
