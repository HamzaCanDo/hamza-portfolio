<?php
require_once 'config.php';
require_once 'helpers.php';

$stuName = app_post_string('sname');
$stuAddress = app_post_string('sadress');
$stuClass = app_post_int('class');
$stuPhone = app_post_string('sphone');
$stuResult = app_post_string('sresult');
$stuAttendance = app_post_string('sattendance');

if ($stuName === '' || $stuAddress === '' || $stuClass <= 0 || $stuPhone === '' || $stuResult === '' || $stuAttendance === '') {
	http_response_code(400);
	echo 'Please fill in all required fields.';
	exit;
}

$stmt = mysqli_prepare(
	$conn,
	'INSERT INTO student (sname, sadress, sclass, sphone, sresult, sattendance) VALUES (?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
	app_fail($conn, 'Prepare failed for insert');
}

mysqli_stmt_bind_param($stmt, 'ssisss', $stuName, $stuAddress, $stuClass, $stuPhone, $stuResult, $stuAttendance);
if (!mysqli_stmt_execute($stmt)) {
	app_fail($conn, 'Insert failed');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

app_redirect('index.php');
?>