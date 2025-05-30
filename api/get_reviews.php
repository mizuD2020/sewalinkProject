<?php
require_once '../db_config.php';

header('Content-Type: application/json');

if (!isset($_GET['worker_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Worker ID is required']);
    exit();
}

$worker_id = intval($_GET['worker_id']);

try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.name AS user_name 
        FROM reviews r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.worker_id = ?
        ORDER BY r.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$worker_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($reviews);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}