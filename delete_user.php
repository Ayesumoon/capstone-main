<?php
session_start();
require 'conn.php'; // Database connection

// Ensure an ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid User ID!";
    header("Location: users.php");
    exit();
}

$admin_id = $_GET['id'];

// Check if the user exists before deleting
$check_sql = "SELECT * FROM adminusers WHERE admin_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "User not found!";
    header("Location: users.php");
    exit();
}
$stmt->close();

// Proceed with deletion
$delete_sql = "DELETE FROM adminusers WHERE admin_id = ?";
$stmt = $conn->prepare($delete_sql);
$stmt->bind_param("i", $admin_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "User deleted successfully!";
} else {
    $_SESSION['message'] = "Error deleting user: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: users.php"); // Redirect back to the users page
exit();
?>
