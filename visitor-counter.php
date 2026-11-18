<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$counterPath = __DIR__ . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'visitor-count.txt';
$handle = @fopen($counterPath, 'c+');

if ($handle === false || !flock($handle, LOCK_EX)) {
    if (is_resource($handle)) {
        fclose($handle);
    }
    http_response_code(500);
    echo json_encode(['error' => 'Visitor counter is temporarily unavailable']);
    exit;
}

$contents = stream_get_contents($handle);
$count = is_string($contents) && preg_match('/^\d+$/', trim($contents)) ? (int) trim($contents) : 0;
$count++;

rewind($handle);
if (ftruncate($handle, 0) === false || fwrite($handle, (string) $count) === false) {
    flock($handle, LOCK_UN);
    fclose($handle);
    http_response_code(500);
    echo json_encode(['error' => 'Visitor counter could not be updated']);
    exit;
}

fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

echo json_encode(['count' => $count]);
