<?php
session_start();
require 'conn.php'; // Database connection

// Ensure user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid User ID!";
    header("Location: users.php");
    exit();
}

$admin_id = $_GET['id'];

// Fetch user details before editing
$sql = "SELECT admin_id, username, admin_email, first_name, last_name, role_id, status_id FROM adminusers WHERE admin_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['message'] = "User not found!";
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Fetch available roles
$roles = [];
$role_query = "SELECT role_id, role_name FROM roles";
$role_result = $conn->query($role_query);
while ($row = $role_result->fetch_assoc()) {
    $roles[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $updates = [];
    $params = [];
    $types = "";

    // Check each field and only add it if changed
    if (!empty($_POST['username']) && $_POST['username'] !== $user['username']) {
        $updates[] = "username = ?";
        $params[] = $_POST['username'];
        $types .= "s";
    }
    if (!empty($_POST['admin_email']) && $_POST['admin_email'] !== $user['admin_email']) {
        $updates[] = "admin_email = ?";
        $params[] = $_POST['admin_email'];
        $types .= "s";
    }
    if (!empty($_POST['first_name']) && $_POST['first_name'] !== $user['first_name']) {
        $updates[] = "first_name = ?";
        $params[] = $_POST['first_name'];
        $types .= "s";
    }
    if (!empty($_POST['last_name']) && $_POST['last_name'] !== $user['last_name']) {
        $updates[] = "last_name = ?";
        $params[] = $_POST['last_name'];
        $types .= "s";
    }
    if (isset($_POST['role_id']) && $_POST['role_id'] != $user['role_id']) {
        $updates[] = "role_id = ?";
        $params[] = $_POST['role_id'];
        $types .= "i";
    }
    if (isset($_POST['status_id']) && $_POST['status_id'] != $user['status_id']) {
        $updates[] = "status_id = ?";
        $params[] = $_POST['status_id'];
        $types .= "i";
    }

    // Handle password change
    if (!empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $_SESSION['message'] = "Passwords do not match!";
            header("Location: edit_user.php?id=$admin_id");
            exit();
        } else {
            $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $updates[] = "password_hash = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
    }

    // Only update if there are changes
    if (!empty($updates)) {
        $query = "UPDATE adminusers SET " . implode(", ", $updates) . " WHERE admin_id = ?";
        $params[] = $admin_id;
        $types .= "i";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $_SESSION['success'] = "User updated successfully!";
            header("Location: users.php");
            exit();
        } else {
            $_SESSION['message'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = "No changes were made.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-pink-600 mb-4">Edit User</h2>

        <?php if (isset($_SESSION['message'])) { ?>
            <div class="mb-4 text-red-600 font-medium">
                <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                ?>
            </div>
        <?php } ?>

        <form action="edit_user.php?id=<?php echo $admin_id; ?>" method="POST" class="space-y-4">

            <div>
                <label class="block font-medium text-gray-700">Username:</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">First Name:</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Last Name:</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Email:</label>
                <input type="email" name="admin_email" value="<?php echo htmlspecialchars($user['admin_email']); ?>" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">New Password:</label>
                <input type="password" name="new_password" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Confirm Password:</label>
                <input type="password" name="confirm_password" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Role:</label>
                <select name="role_id" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
                    <?php foreach ($roles as $role) { ?>
                        <option value="<?php echo $role['role_id']; ?>" 
                            <?php echo ($user['role_id'] == $role['role_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div>
                <label class="block font-medium text-gray-700">Status:</label>
                <select name="status_id" 
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
                    <option value="1" <?php echo ($user['status_id'] == 1) ? 'selected' : ''; ?>>Active</option>
                    <option value="2" <?php echo ($user['status_id'] == 2) ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit"
                    class="bg-pink-500 text-white px-6 py-2 rounded hover:bg-pink-600 transition-all">Update User</button>
                <button type="button" onclick="window.location.href='users.php'"
                    class="bg-gray-300 text-gray-800 px-6 py-2 rounded hover:bg-gray-400 transition-all">Cancel</button>
            </div>

        </form>
    </div>

</body>
</html>
