<?php
require_once 'header.php';
require_once 'config.php';
require_once 'helpers.php';

$sql = 'SELECT cid, cname FROM studentclass ORDER BY cid';
$result = mysqli_query($conn, $sql);
if (!$result) {
    app_fail($conn, 'Select failed');
}
?>
<div id="main-content">
    <h2>Add New Record</h2>
    <form class="post-form" action="savedata.php" method="post">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="sname" required />
        </div>
        <div class="form-group">
            <label>Address</label>
            <input type="text" name="sadress" required />
        </div>
        <div class="form-group">
            <label>Class</label>
            <select name="class" required>
                <option value="" selected disabled>Select Class</option>
                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                <option value="<?php echo $row['cid']; ?>"><?php echo $row['cname']; ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="sphone" required />
        </div>
        <div class="form-group">
            <label>Result</label>
            <input type="text" name="sresult" required />
        </div>
        <div class="form-group">
            <label>Attendance</label>
            <input type="text" name="sattendance" required />
        </div>
        <input class="submit" type="submit" value="Save" />
    </form>
</div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
