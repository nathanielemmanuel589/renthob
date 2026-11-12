<?php
/**
 * API: Get LGAs (Local Government Areas) by State ID
 * Returns all cities/LGAs for a given state
 */
header('Content-Type: application/json');

require_once '../config/db.php';

$stateId = isset($_GET['state_id']) ? (int)$_GET['state_id'] : 0;

if ($stateId <= 0) {
    echo json_encode(['error' => 'Invalid state_id']);
    exit;
}

try {
    $stmt = $pdo->prepare('
        SELECT id, name 
        FROM cities 
        WHERE state_id = ? AND status = "active"
        ORDER BY name ASC
    ');
    $stmt->execute([$stateId]);
    $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($lgas);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
