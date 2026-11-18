<?php
// Include config.php safely
$configPath = __DIR__ . "/config.php";
if (!file_exists($configPath)) {
    die("Configuration file not found!");
}
include $configPath;

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging (comment out in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize page title
$page_title = "";

// Check connection
if (!$conn) {
    die("Database connection not established.");
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// ---------- Get page title ----------
if ($current_page === 'category' && isset($_GET['cid'])) {
    $cat_id = mysqli_real_escape_string($conn, $_GET['cid']);
    $cat_sql = "SELECT category_name FROM category WHERE category_id = '$cat_id' LIMIT 1";
    $cat_result = mysqli_query($conn, $cat_sql);
    if ($cat_result && $cat_row = mysqli_fetch_assoc($cat_result)) {
        $page_title = $cat_row['category_name'];
    } else {
        $page_title = "Category";
    }
} else {
    // Non-category pages: get website name
    $sql_settings = "SELECT websitename, logo FROM settings LIMIT 1";
    $result_settings = mysqli_query($conn, $sql_settings);
    if ($result_settings && $row_settings = mysqli_fetch_assoc($result_settings)) {
        $page_title = $row_settings['websitename'] ?: "My Website";
    } else {
        $page_title = "My Website";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="header">
    <div class="container">
        <div class="row">
            <div class="col-md-offset-4 col-md-4 text-center">
                <?php
                if (isset($row_settings)) {
                    if (!empty($row_settings['logo'])) {
                        echo '<a href="index.php" id="logo"><img src="admin/images/' . htmlspecialchars($row_settings['logo']) . '" alt="' . htmlspecialchars($row_settings['websitename']) . '"></a>';
                    } else {
                        echo '<a href="index.php"><h1>' . htmlspecialchars($row_settings['websitename']) . '</h1></a>';
                    }
                }
                ?>
            </div>
            <div class="col-md-4 text-center">
                <?php
                if (isset($_SESSION['username'])) {
                    echo '<div class="user-info"><h4>Welcome, ' . htmlspecialchars($_SESSION['username']) . '!</h4></div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<div id="menu-bar">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <?php
                $sql_categories = "SELECT category_id, category_name FROM category WHERE post > 0";
                $result_categories = mysqli_query($conn, $sql_categories);

                if ($result_categories && mysqli_num_rows($result_categories) > 0) {
                    echo '<ul class="menu">';
                    echo '<li><a href="' . htmlspecialchars($hostname) . '">Home</a></li>';

                    while ($row = mysqli_fetch_assoc($result_categories)) {
                        $active = (isset($_GET['cid']) && $_GET['cid'] == $row['category_id']) ? 'active' : '';
                        echo '<li><a class="' . $active . '" href="category.php?cid=' . htmlspecialchars($row['category_id']) . '">' . htmlspecialchars($row['category_name']) . '</a></li>';
                    }

                    if (isset($_SESSION['username'])) {
                        echo '<li><a href="logout-redirect.php">Log Out</a></li>';
                    } else {
                        echo '<li><a href="admin/">Log In</a></li>';
                    }

                    echo '</ul>';
                }
                ?>
            </div>
        </div>
    </div>
</div>
