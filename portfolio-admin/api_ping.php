<?php
// api_ping.php - diagnostic endpoint to test DB connection and session
require_once __DIR__ . '/api_config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db_connect();
    $stmt = $pdo->query('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE()');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $portfolioTables = ['portfolio_content','portfolio_site_images','portfolio_skills','portfolio_projects','portfolio_project_screenshots','portfolio_timeline_items'];
    $present = [];
    $check = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    foreach ($portfolioTables as $table) {
        $check->execute([$table]);
        if ($check->fetchColumn()) {
            $present[] = $table;
        }
    }
    echo json_encode([
        'ok' => true,
        'tables_in_db' => (int)$row['c'],
        'portfolio_tables' => count($present),
        'portfolio_tables_ready' => count($present) === count($portfolioTables),
        'public_json_writable' => is_writable(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'portfolio-content.json')
            || (!file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'portfolio-content.json') && is_writable(dirname(__DIR__))),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>