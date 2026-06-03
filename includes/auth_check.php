<?php

/* Start session only if not started */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Check if user is logged in */
function requireLogin() {

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
}


/* Check if user has required role */
function requireRole(array $allowedRoles) {

    requireLogin();

    if (
        !isset($_SESSION['role']) ||
        !in_array($_SESSION['role'], $allowedRoles)
    ) {
        header("Location: /brgy_system/includes/logout.php");
        exit();
    }
}

?>