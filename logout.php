
<?php
/**
 * logout.php — LMS Logout Handler
 * Destroys the session and redirects to login page.
 */

session_start();

// 1. Unset all session variables
$_SESSION = [];

// 2. Destroy the session cookie (makes it expire immediately)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,         // set to past to expire it
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy the session on the server side
session_destroy();

// 4. Redirect to login with a logout message
header("Location: login.php?logout=1");
exit();