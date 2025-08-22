<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    header("Location: superadmin_login.php");
    exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $sql = "DELETE FROM adminusers WHERE admin_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User deleted successfully!";
    } else {
        $_SESSION['message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
}
header("Location: manage_users.php");
exit();
