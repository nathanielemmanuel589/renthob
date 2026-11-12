<?php
/**
 * API — Get Areas by City
 */
require_once '../config/db.php';

header('Content-Type: application/json');

$cityId = (int)($_GET['city_id'] ?? 0);

if ($cityId > 0) {
    $stmt = $pdo->prepare('SELECT id, name FROM areas WHERE city_id = ? AND status = "active" ORDER BY name');
    $stmt->execute([$cityId]);
    $areas = $stmt->fetchAll();
    echo json_encode($areas);
} else {
    echo json_encode([]);
}
