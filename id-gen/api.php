<?php

require_once __DIR__ . '/UniqueIdGenerator.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

try {
    $idGenerator = new UniqueIdGenerator();
    $generatedId = $idGenerator->generateId();

    echo json_encode([
        'success' => true,
        'id' => $generatedId,
    ]);
} catch (Throwable $exception) {
    error_log('Unique ID API error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'The application could not connect to its database.',
    ]);
}
