<?php
/**
 * API — Get States by Country
 */
require_once '../config/db.php';

header('Content-Type: application/json');

$countryId = (int)($_GET['country_id'] ?? 0);

if ($countryId > 0) {
    $stmt = $pdo->prepare('SELECT id, name FROM states WHERE country_id = ? AND status = "active" ORDER BY name');
    $stmt->execute([$countryId]);
    $states = $stmt->fetchAll();
    echo json_encode($states);
} else {
    echo json_encode([]);
}
