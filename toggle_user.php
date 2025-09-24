<?php
session_start();
require 'conn.php';

// ✅ Only Super Admin (role_id = 1) can toggle
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    $_SESSION['message'] = "Unauthorized access.";
    header("Location: manage_users.php");
    exit();
}

// ✅ Ensure valid inputs
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['message'] = "Invalid request.";
    header("Location: manage_users.php");
    exit();
}

$admin_id = (int) $_GET['id'];
$new_status = (int) $_GET['status'];

// ✅ Prevent Super Admin from deactivating themselves
if ($admin_id == $_SESSION['admin_id']) {
    $_SESSION['message'] = "You cannot deactivate your own account.";
    header("Location: manage_users.php");
    exit();
}

// ✅ Check if status exists in `status` table
$statusCheck = $conn->prepare("SELECT status_name FROM status WHERE status_id = ?");
$statusCheck->bind_param("i", $new_status);
$statusCheck->execute();
$statusResult = $statusCheck->get_result();

if ($statusResult->num_rows === 0) {
    $_SESSION['message'] = "Invalid status.";
    header("Location: manage_users.php");
    exit();
}

$statusRow = $statusResult->fetch_assoc();
$statusName = $statusRow['status_name'];
$statusCheck->close();

// ✅ Update status
$stmt = $conn->prepare("UPDATE adminusers SET status_id = ? WHERE admin_id = ?");
$stmt->bind_param("ii", $new_status, $admin_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "User status changed to '{$statusName}' successfully.";
} else {
    $_SESSION['message'] = "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: manage_users.php");
exit();
