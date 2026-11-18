<?php
// api_ping.php - diagnostic endpoint to test DB connection and session
require_once __DIR__ . '/api_config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db_connect();
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE()');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'tables_in_db' => (int)$row['c']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>