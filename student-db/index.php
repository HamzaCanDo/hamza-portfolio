<?php
require_once 'header.php';
require_once 'config.php';
require_once 'helpers.php';

$sql = 'SELECT student.sid, student.sname, student.sadress, student.sphone, student.sresult, student.sattendance, studentclass.cname '
     . 'FROM student '
     . 'JOIN studentclass ON student.sclass = studentclass.cid '
     . 'ORDER BY student.sid DESC';
$result = mysqli_query($conn, $sql);
if (!$result) {
    app_fail($conn, 'Select failed');
}
?>
<div id="main-content">
    <h2>All Records</h2>
    <?php if (mysqli_num_rows($result) > 0) { ?>
    <table cellpadding="7px">
        <thead>
            <tr>
                <th>Id</th>
                <th>Name</th>
                <th>Address</th>
                <th>Class</th>
                <th>Phone</th>
                <th>Result</th>
                <th>Attendance</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?php echo $row['sid']; ?></td>
                <td><?php echo $row['sname']; ?></td>
                <td><?php echo $row['sadress']; ?></td>
                <td><?php echo $row['cname']; ?></td>
                <td><?php echo $row['sphone']; ?></td>
                <td><?php echo $row['sresult']; ?></td>
                <td><?php echo $row['sattendance']; ?></td>
                <td>
                    <a href="update.php?id=<?php echo $row['sid']; ?>">Edit</a>
                    <a href="delete-inline.php?id=<?php echo $row['sid']; ?>">Delete</a>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php } else { ?>
        <h2>No Record Found</h2>
    <?php } ?>
</div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
