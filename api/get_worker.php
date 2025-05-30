<?php
require_once '../db_config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Worker ID is required']);
    exit();
}

$worker_id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("
        SELECT w.*, c.name AS category_name 
        FROM workers w
        JOIN categories c ON w.category_id = c.id
        WHERE w.id = ?
    ");
    $stmt->execute([$worker_id]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$worker) {
        http_response_code(404);
        echo json_encode(['error' => 'Worker not found']);
        exit();
    }

    echo json_encode($worker);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}