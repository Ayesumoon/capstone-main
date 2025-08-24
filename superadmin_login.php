<?php
session_start();
require 'conn.php'; // DB connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Fetch user with role = Super Admin (role_id = 1)
    $sql = "SELECT admin_id, username, password_hash, role_id, first_name, last_name 
            FROM adminusers 
            WHERE username = ? AND status_id = 1 AND role_id = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {
            // Set session
            $_SESSION['admin_id'] = $user['admin_id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['name'] = $user['first_name'] . " " . $user['last_name'];

            header("Location: superadmin_dashboard.php");
            exit();
        } else {
            $_SESSION['message'] = "❌ Invalid password.";
        }
    } else {
        $_SESSION['message'] = "❌ Super Admin not found or inactive.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

  <div class="w-full max-w-md bg-white p-8 rounded-2xl shadow-lg">
    <h2 class="text-3xl font-bold text-pink-600 text-center mb-6">🔑 Super Admin Login</h2>

    <?php if (isset($_SESSION['message'])) { ?>
      <div class="mb-4 px-4 py-2 rounded bg-red-100 text-red-700 font-medium">
        <?php 
          echo $_SESSION['message']; 
          unset($_SESSION['message']);
        ?>
      </div>
    <?php } ?>

    <form action="superadmin_login.php" method="POST" class="space-y-5">
      <div>
        <label class="block text-gray-700 font-medium mb-1">Username</label>
        <input type="text" name="username" required
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
      </div>

      <div>
        <label class="block text-gray-700 font-medium mb-1">Password</label>
        <input type="password" name="password" required
          class="w-full border border-gray-300 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-pink-400">
      </div>

      <button type="submit"
        class="w-full bg-pink-500 text-white py-3 rounded-lg font-medium hover:bg-pink-600 transition">
        🚀 Login
      </button>
    </form>
  </div>

</body>
</html>
