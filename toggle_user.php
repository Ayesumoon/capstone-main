<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    header("Location: superadmin_login.php");
    exit();
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = intval($_GET['id']);
    $status = intval($_GET['status']);

    $sql = "UPDATE adminusers SET status_id = ? WHERE admin_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $status, $id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "User status updated!";
    } else {
        $_SESSION['message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
}
header("Location: manage_users.php");
exit();
