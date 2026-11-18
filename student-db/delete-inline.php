<?php
require_once 'config.php';
require_once 'helpers.php';

$stuId = app_get_int('id');
if ($stuId <= 0) {
	http_response_code(400);
	echo 'Invalid student id.';
	exit;
}

$stmt = mysqli_prepare($conn, 'DELETE FROM student WHERE sid = ?');
if (!$stmt) {
	app_fail($conn, 'Prepare failed for delete');
}

mysqli_stmt_bind_param($stmt, 'i', $stuId);
if (!mysqli_stmt_execute($stmt)) {
	app_fail($conn, 'Delete failed');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

app_redirect('index.php');
?>