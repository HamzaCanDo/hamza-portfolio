<?php
// Include config.php safely
include __DIR__ . "/config.php";

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize variables
$page_title = "News Site";
$websitename = "News Site";
$logo = "";

// Get website settings
$sql_settings = "SELECT websitename, logo FROM settings LIMIT 1";
$result_settings = mysqli_query($conn, $sql_settings);
if ($result_settings && $row_settings = mysqli_fetch_assoc($result_settings)) {
    $websitename = $row_settings['websitename'] ?: "My Website";
    $logo = $row_settings['logo'] ?: "";
    $page_title = $websitename;
}

// Check current page
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Override page title for category pages
if ($current_page === 'category' && isset($_GET['cid'])) {
    $cat_id = mysqli_real_escape_string($conn, $_GET['cid']);
    $cat_sql = "SELECT category_name FROM category WHERE category_id = '$cat_id' LIMIT 1";
    $cat_result = mysqli_query($conn, $cat_sql);
    if ($cat_result && $cat_row = mysqli_fetch_assoc($cat_result)) {
        $page_title = htmlspecialchars($cat_row['category_name']) . " - " . $websitename;
    } else {
        $page_title = "Category - " . $websitename;
    }
}

// Override page title for single post pages
if ($current_page === 'single' && isset($_GET['id'])) {
    $post_id = mysqli_real_escape_string($conn, $_GET['id']);
    $post_sql = "SELECT title FROM post WHERE post_id = '$post_id' LIMIT 1";
    $post_result = mysqli_query($conn, $post_sql);
    if ($post_result && $post_row = mysqli_fetch_assoc($post_result)) {
        $page_title = htmlspecialchars($post_row['title']) . " - " . $websitename;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo $page_title; ?></title>
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.css">
    <link rel="stylesheet" href="css/style.css">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($websitename); ?> - Latest news and updates">
    <meta name="keywords" content="news, articles, <?php echo htmlspecialchars($websitename); ?>">
</head>
<body>
<!-- Header -->
<div id="header">
    <div class="container">
        <div class="row">
            <div class="col-md-4"></div>
            <div class="col-md-4 text-center">
                <?php
                if (!empty($logo)) {
                    echo '<a href="index.php" id="logo"><img src="admin/images/' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($websitename) . '"></a>';
                } else {
                    echo '<a href="index.php" id="logo"><h1>' . htmlspecialchars($websitename) . '</h1></a>';
                }
                ?>
            </div>
            <div class="col-md-4 text-right">
                <?php
                if (isset($_SESSION['username'])) {
                    echo '<div class="user-info">';
                    echo '<p>Welcome, <strong>' . htmlspecialchars($_SESSION['username']) . '</strong>!</p>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Menu Bar -->
<div id="menu-bar">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <?php
                $sql_categories = "SELECT category_id, category_name FROM category WHERE post > 0 ORDER BY category_name ASC";
                $result_categories = mysqli_query($conn, $sql_categories);
                
                if ($result_categories && mysqli_num_rows($result_categories) > 0) {
                    echo '<ul class="menu">';
                    
                    // Home link
                    $home_active = ($current_page === 'index') ? 'active' : '';
                    echo '<li><a class="' . $home_active . '" href="index.php">Home</a></li>';
                    
                    // Category links
                    while ($row = mysqli_fetch_assoc($result_categories)) {
                        $active = (isset($_GET['cid']) && $_GET['cid'] == $row['category_id']) ? 'active' : '';
                        echo '<li><a class="' . $active . '" href="category.php?cid=' . htmlspecialchars($row['category_id']) . '">' . htmlspecialchars($row['category_name']) . '</a></li>';
                    }
                    
                    // Login/Logout link
                    if (isset($_SESSION['username'])) {
                        echo '<li><a href="logout.php">Logout</a></li>';
                    } else {
                        echo '<li><a href="admin/">Login</a></li>';
                    }
                    
                    echo '</ul>';
                } else {
                    echo '<p>No categories available.</p>';
                }
                ?>
            </div>
        </div>
    </div>
</div>
<!-- Page Content Starts Below -->
