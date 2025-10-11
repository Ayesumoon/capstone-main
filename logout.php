<?php
session_start();
require 'conn.php';

// Admin or Cashier logout tracking
if (isset($_SESSION["admin_id"])) {
    $user_id = $_SESSION["admin_id"];
    $role_id = $_SESSION["role_id"];
    $role = ($role_id == 0) ? 'Cashier' : 'Admin';

    // Record logout action
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, role_id, action) VALUES (?, ?, 'Logout')");
    $stmt->bind_param("is", $user_id, $role);
    $stmt->execute();

    // Update last_logged_out
    $update = $conn->prepare("UPDATE adminusers SET last_logged_out = NOW() WHERE admin_id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
}

// Destroy session
session_unset();
session_destroy();

header("Location: login.php");
exit;
?>
