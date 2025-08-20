<?php
session_start();
require 'conn.php';

if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];

    // Prepare and execute the update query for last_logged_out
    $updateLogout = "UPDATE adminusers SET last_logged_out = NOW() WHERE admin_id = ?";
    if ($stmtLogout = $conn->prepare($updateLogout)) {
        $stmtLogout->bind_param("i", $user_id);
        $stmtLogout->execute();
        $stmtLogout->close();
    } else {
        error_log("Logout Error: " . $conn->error); // Log errors for debugging
    }
}

// Destroy the session
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
