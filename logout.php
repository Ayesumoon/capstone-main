<?php
session_start();
require 'conn.php';

if (isset($_SESSION["admin_id"])) {
    $user_id   = $_SESSION["admin_id"];
    $username  = $_SESSION["username"] ?? 'Unknown';
    $role_id   = $_SESSION["role_id"] ?? null;

    // ✅ Update last_logged_out
    $updateLogout = "UPDATE adminusers SET last_logged_out = NOW() WHERE admin_id = ?";
    if ($stmtLogout = $conn->prepare($updateLogout)) {
        $stmtLogout->bind_param("i", $user_id);
        if (!$stmtLogout->execute()) {
            error_log("Logout Update Failed: " . $stmtLogout->error);
        }
        $stmtLogout->close();
    } else {
        error_log("Prepare Failed: " . $conn->error);
    }

    // ✅ Insert into logs (tracks Super Admin/Admin logout)
    $log_sql = "INSERT INTO system_logs (user_id, username, role_id, action) VALUES (?, ?, ?, ?)";
    if ($log_stmt = $conn->prepare($log_sql)) {
        $action = "User logged out";
        $log_stmt->bind_param("isis", $user_id, $username, $role_id, $action);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// ✅ Clear session
session_unset();
session_destroy();

// ✅ Redirect to login page
header("Location: login.php");
exit;
?>
