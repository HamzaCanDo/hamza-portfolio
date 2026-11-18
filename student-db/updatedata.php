<?php
require_once 'config.php';
require_once 'helpers.php';

$stuId = app_post_int('sid');
$stuName = app_post_string('sname');
$stuAddress = app_post_string('sadress');
$stuClass = app_post_int('sclass');
$stuPhone = app_post_string('sphone');
$stuResult = app_post_string('sresult');
$stuAttendance = app_post_string('sattendance');

if ($stuId <= 0 || $stuName === '' || $stuAddress === '' || $stuClass <= 0 || $stuPhone === '' || $stuResult === '' || $stuAttendance === '') {
	http_response_code(400);
	echo 'Please fill in all required fields.';
	exit;
}

$stmt = mysqli_prepare(
	$conn,
	'UPDATE student SET sname = ?, sadress = ?, sclass = ?, sphone = ?, sresult = ?, sattendance = ? WHERE sid = ?'
);
if (!$stmt) {
	app_fail($conn, 'Prepare failed for update');
}

mysqli_stmt_bind_param($stmt, 'ssisssi', $stuName, $stuAddress, $stuClass, $stuPhone, $stuResult, $stuAttendance, $stuId);
if (!mysqli_stmt_execute($stmt)) {
	app_fail($conn, 'Update failed');
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

app_redirect('index.php');
?>