<?php
// Main entry point - Redirects to appropriate page based on auth status
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    if (is_admin()) {
        header("Location: admin/dashboard");
    } else {
        header("Location: employee/dashboard");
    }
} else {
    header("Location: auth/login");
}
exit;
