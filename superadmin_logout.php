<?php
session_start();
require 'conn.php';

// ✅ Check if logged in and is super admin (role_id = 1)
if (!empty($_SESSION['admin_id']) && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
    $admin_id = $_SESSION['admin_id'];

    // Update last_logged_out for this super admin
    $updateLogout = "UPDATE adminusers SET last_logged_out = NOW() WHERE admin_id = ?";
    if ($stmt = $conn->prepare($updateLogout)) {
        $stmt->bind_param("i", $admin_id);
        if (!$stmt->execute()) {
            error_log("Logout Update Execute Error: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Logout Update Prepare Error: " . $conn->error);
    }
}

// ✅ Destroy session AFTER updating logout time
session_unset();
session_destroy();

// Redirect to super admin login page
header("Location: login.php");
exit();
?>
