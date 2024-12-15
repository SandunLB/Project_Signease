<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.html");
    exit();
}

// If needed, include database configuration
include 'config.php';
?>