<?php
/**
 * includes/auth_guard.php
 * Include at the TOP of every protected page (before any output):
 *   require_once 'includes/auth_guard.php';
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}
// Shorthand helpers available on every protected page
$current_user_id   = (int) $_SESSION['user_id'];
$current_username  = $_SESSION['username'];