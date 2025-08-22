<?php
session_start();
require 'conn.php'; // Database connection

// Ensure user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid User ID!";
    header("Location: users.php");
    exit();
}

$admin_id = (int) $_GET['id'];

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

    // Sanitize inputs
    $username = trim($_POST['username']);
    $email = trim($_POST['admin_email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $role_id = (int) $_POST['role_id'];
    $status_id = (int) $_POST['status_id'];

    if (!empty($username) && $username !== $user['username']) {
        $updates[] = "username = ?";
        $params[] = $username;
        $types .= "s";
    }
    if (!empty($email) && $email !== $user['admin_email']) {
        $updates[] = "admin_email = ?";
        $params[] = $email;
        $types .= "s";
    }
    if (!empty($first_name) && $first_name !== $user['first_name']) {
        $updates[] = "first_name = ?";
        $params[] = $first_name;
        $types .= "s";
    }
    if (!empty($last_name) && $last_name !== $user['last_name']) {
        $updates[] = "last_name = ?";
        $params[] = $last_name;
        $types .= "s";
    }
    if ($role_id != $user['role_id']) {
        $updates[] = "role_id = ?";
        $params[] = $role_id;
        $types .= "i";
    }
    if ($status_id != $user['status_id']) {
        $updates[] = "status_id = ?";
        $params[] = $status_id;
        $types .= "i";
    }

    // Handle password change
    if (!empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $_SESSION['message'] = "Passwords do not match!";
            header("Location: edit_user.php?id=$admin_id");
            exit();
        } elseif (strlen($_POST['new_password']) < 6) {
            $_SESSION['message'] = "Password must be at least 6 characters long!";
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
            header("Location: manage_users.php");
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

  <div class="max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-lg">
    <h2 class="text-3xl font-bold text-pink-600 mb-6 text-center">✏️ Edit User</h2>

    <!-- Flash Message -->
    <?php if (isset($_SESSION['message'])) { ?>
      <div class="mb-4 px-4 py-2 rounded bg-red-100 text-red-700 font-medium">
        <?php 
          echo $_SESSION['message']; 
          unset($_SESSION['message']);
        ?>
      </div>
    <?php } elseif (isset($_SESSION['success'])) { ?>
      <div class="mb-4 px-4 py-2 rounded bg-green-100 text-green-700 font-medium">
        <?php 
          echo $_SESSION['success']; 
          unset($_SESSION['success']);
        ?>
      </div>
    <?php } ?>

    <form action="edit_user.php?id=<?php echo $admin_id; ?>" method="POST" class="space-y-5">

      <!-- Username -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Username</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
      </div>

      <!-- First Name -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">First Name</label>
        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" 
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
      </div>

      <!-- Last Name -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Last Name</label>
        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" 
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
      </div>

      <!-- Email -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Email</label>
        <input type="email" name="admin_email" value="<?php echo htmlspecialchars($user['admin_email']); ?>" 
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
      </div>

      <!-- New Password -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">New Password</label>
        <input type="password" name="new_password" 
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
        <p class="text-sm text-gray-500 mt-1">Leave blank if you don't want to change the password. Must be at least 6 characters.</p>
      </div>

      <!-- Confirm Password -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Confirm Password</label>
        <input type="password" name="confirm_password" 
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
      </div>

      <!-- Role -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Role</label>
        <select name="role_id" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
          <?php foreach ($roles as $role) { ?>
            <option value="<?php echo $role['role_id']; ?>" 
              <?php echo ($user['role_id'] == $role['role_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($role['role_name']); ?>
            </option>
          <?php } ?>
        </select>
      </div>

      <!-- Status -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Status</label>
        <select name="status_id" class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
          <option value="1" <?php echo ($user['status_id'] == 1) ? 'selected' : ''; ?>>Active</option>
          <option value="2" <?php echo ($user['status_id'] == 2) ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>

      <!-- Buttons -->
      <div class="flex gap-4 pt-4">
        <button type="submit"
          class="flex-1 bg-pink-500 text-white px-6 py-3 rounded-lg font-medium shadow-md hover:bg-pink-600 transition-all">Update User</button>
        <button type="button" onclick="window.location.href='manage_users.php'"
          class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-medium hover:bg-gray-300 transition-all">Cancel</button>
      </div>

    </form>
  </div>

</body>
</html>
