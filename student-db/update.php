<?php
require_once 'header.php';
require_once 'config.php';
require_once 'helpers.php';

$stuId = app_get_int('id');
?>
<div id="main-content">
    <h2>Update Record</h2>

    <?php
    if ($stuId <= 0) {
        echo '<p>Invalid or missing student id.</p>';
    } else {
        $sql = 'SELECT * FROM student WHERE sid = ' . $stuId;
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            app_fail($conn, 'Select failed');
        }

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
    ?>

    <form class="post-form" action="updatedata.php" method="post">
      <div class="form-group">
          <label>Name</label>
          <input type="hidden" name="sid" value="<?php echo $row['sid']; ?>" />
          <input type="text" name="sname" value="<?php echo $row['sname']; ?>" required />
      </div>
      <div class="form-group">
          <label>Address</label>
          <input type="text" name="sadress" value="<?php echo $row['sadress']; ?>" required />
      </div>
      <div class="form-group">
          <label>Class</label>
          <?php
          $sql1 = 'SELECT cid, cname FROM studentclass ORDER BY cid';
          $result1 = mysqli_query($conn, $sql1);
          if (!$result1) {
              app_fail($conn, 'Select classes failed');
          }
          if (mysqli_num_rows($result1) > 0) {
              echo '<select name="sclass" required>';
              while ($row1 = mysqli_fetch_assoc($result1)) {
                  $selected = ($row['sclass'] == $row1['cid']) ? 'selected' : '';
                  echo '<option ' . $selected . ' value="' . $row1['cid'] . '">' . $row1['cname'] . '</option>';
              }
              echo '</select>';
          }
          ?>
      </div>
      <div class="form-group">
          <label>Phone</label>
          <input type="text" name="sphone" value="<?php echo $row['sphone']; ?>" required />
      </div>
      <div class="form-group">
          <label>Result</label>
          <input type="text" name="sresult" value="<?php echo $row['sresult']; ?>" required />
      </div>
      <div class="form-group">
          <label>Attendance</label>
          <input type="text" name="sattendance" value="<?php echo $row['sattendance']; ?>" required />
      </div>
      <input class="submit" type="submit" value="Update" />
    </form>

    <?php
        } else {
            echo '<p>No record found.</p>';
        }
    }
    ?>
</div>
</div>
</body>
</html>
<?php mysqli_close($conn); ?>
