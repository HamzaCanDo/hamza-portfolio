<?php
function app_redirect($path)
{
    header('Location: ' . $path);
    exit;
}

function app_post_string($key)
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
}

function app_post_int($key)
{
    return isset($_POST[$key]) ? (int)$_POST[$key] : 0;
}

function app_get_int($key)
{
    return isset($_GET[$key]) ? (int)$_GET[$key] : 0;
}

function app_fail($conn, $message)
{
    $detail = $message;
    if ($conn instanceof mysqli) {
        $detail .= ' | MySQL: ' . mysqli_error($conn);
    }

    error_log($detail);

    http_response_code(500);
    if (getenv('APP_DEBUG') === '1') {
        echo htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    } else {
        echo 'Server error. Please try again later.';
    }
    exit;
}
