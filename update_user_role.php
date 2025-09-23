<?php
session_start();
require 'conn.php';

// ✅ Restrict access to Super Admin only
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    header("Location: superadmin_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = intval($_POST['admin_id']);
    $new_role_id = intval($_POST['role_id']);

    // Prevent modifying your own role if you're Super Admin
    if ($admin_id == $_SESSION['admin_id'] && $_SESSION['role_id'] == 1 && $new_role_id != 1) {
        $_SESSION['message'] = "⚠️ You cannot remove your own Super Admin role!";
        header("Location: manage_roles.php");
        exit();
    }

    // Update role
    $stmt = $conn->prepare("UPDATE adminusers SET role_id = ? WHERE admin_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $new_role_id, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "✅ User role updated successfully!";
        } else {
            $_SESSION['message'] = "❌ Error updating role: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "❌ Database error: " . $conn->error;
    }

    header("Location: manage_roles.php");
    exit();
} else {
    header("Location: manage_roles.php");
    exit();
}
?>
