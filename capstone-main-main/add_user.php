<?php
session_start();
require 'conn.php'; // Database connection

$message = '';

// Fetch available roles
$roles = [];
$role_query = "SELECT role_id, role_name FROM roles";
$role_result = $conn->query($role_query);
while ($row = $role_result->fetch_assoc()) {
    $roles[] = $row;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $status_id = $_POST['status_id'];
    $created_at = date("Y-m-d H:i:s");
    $role_id = $_POST['role_id']; // Now correctly using role_id

    // Check if username or email already exists
    $check_query = "SELECT admin_id FROM adminusers WHERE username = ? OR admin_email = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $_SESSION['message'] = "Error: Username or Email already exists!";
        header("Location: add_user.php");
        exit();
    } else {
        // Insert new user
        $sql = "INSERT INTO adminusers (username, admin_email, password_hash, role_id, status_id, created_at, first_name, last_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssissss", $username, $email, $password, $role_id, $status_id, $created_at, $first_name, $last_name);

        if ($stmt->execute()) {
            $_SESSION['success'] = "User added successfully!";
            header("Location: users.php"); // Redirect to users page
            exit();
        } else {
            $_SESSION['message'] = "Error: " . $stmt->error;
            header("Location: add_user.php");
            exit();
        }
        $stmt->close();
    }
    $check_stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

    <div class="max-w-2xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-pink-600 mb-4">Add New User</h2>

        <?php if (isset($_SESSION['message'])) { ?>
            <div class="mb-4 text-red-600 font-medium">
                <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                ?>
            </div>
        <?php } ?>

        <form action="add_user.php" method="POST" class="space-y-4">

            <div>
                <label class="block font-medium text-gray-700">Username:</label>
                <input type="text" name="username" required
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">First Name:</label>
                <input type="text" name="first_name" required
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Last Name:</label>
                <input type="text" name="last_name" required
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Email:</label>
                <input type="email" name="email" required
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Password:</label>
                <input type="password" name="password" required
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
            </div>

            <div>
                <label class="block font-medium text-gray-700">Role:</label>
                <select name="role_id" required
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
                    <?php foreach ($roles as $role) { ?>
                        <option value="<?php echo $role['role_id']; ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div>
                <label class="block font-medium text-gray-700">Status:</label>
                <select name="status_id" required
                    class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-pink-400">
                    <option value="1">Active</option>
                    <option value="2">Inactive</option>
                </select>
            </div>

            <div class="flex gap-4 pt-4">
                <button type="submit"
                    class="bg-pink-500 text-white px-6 py-2 rounded hover:bg-pink-600 transition-all">Add User</button>
                <button type="button" onclick="window.location.href='users.php'"
                    class="bg-gray-300 text-gray-800 px-6 py-2 rounded hover:bg-gray-400 transition-all">Cancel</button>
            </div>

        </form>
    </div>

</body>
</html>
