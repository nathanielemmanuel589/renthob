<?php
/**
 * API — Get Cities by State
 */
require_once '../config/db.php';

header('Content-Type: application/json');

$stateId = (int)($_GET['state_id'] ?? 0);

if ($stateId > 0) {
    $stmt = $pdo->prepare('SELECT id, name FROM cities WHERE state_id = ? AND status = "active" ORDER BY name');
    $stmt->execute([$stateId]);
    $cities = $stmt->fetchAll();
    echo json_encode($cities);
} else {
    echo json_encode([]);
}
