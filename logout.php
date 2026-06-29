<?php
session_start();

// Session-Variablen leeren
session_unset();

// Session-Cookie im Browser löschen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session komplett vernichten
session_destroy();

// Cache-Control-Header setzen, um Browser-Caching zu verhindern
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Weiterleitung zum Login
header("Location: login.php");
exit;
?>