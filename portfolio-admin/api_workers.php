<?php
// api_workers.php - list and create worker records
require_once __DIR__ . '/api_config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$pdo = db_connect();

if ($action === 'list') {
    dashboard_require_admin($pdo);
    $stmt = $pdo->query('SELECT id,name,days,hours,performance,status,created_at FROM workers ORDER BY id ASC');
    $workers = $stmt->fetchAll();
    echo json_encode(['workers' => $workers]); exit;
}

if ($action === 'create') {
    try {
        // admin only
        if (!isset($_SESSION['user_id'])) { echo json_encode(['error' => 'Not authorized']); exit; }
        $me = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$me]);
        $row = $stmt->fetch();
        if (!$row || $row['role'] !== 'admin') { echo json_encode(['error' => 'Not authorized']); exit; }

        $body = getJsonBody();
        $name = trim($body['name'] ?? '');
        $days = (int)($body['days'] ?? 0);
        $hours = (int)($body['hours'] ?? 0);
        $performance = (int)($body['performance'] ?? 0);
        $status = $body['status'] ?? ($performance >= 85 ? 'Excellent' : ($performance >= 70 ? 'Good' : 'Focus'));

        if (!$name) { echo json_encode(['error' => 'Missing name']); exit; }

        $stmt = $pdo->prepare('INSERT INTO workers (name,days,hours,performance,status,created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$name, $days, $hours, $performance, $status]);
        $id = (int)$pdo->lastInsertId();
        echo json_encode(['worker' => ['id'=>$id,'name'=>$name,'days'=>$days,'hours'=>$hours,'performance'=>$performance,'status'=>$status]]); exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]); exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);
?>