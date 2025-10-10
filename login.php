<?php
session_start();
require 'conn.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = trim($_POST["login"]); // can be username OR email
    $password = $_POST["password"];

    // Check if it's an admin (username OR email)
    $sql = "SELECT admin_id, admin_email, username, password_hash, role_id 
            FROM adminusers 
            WHERE admin_email = ? OR username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($admin_id, $db_email, $db_username, $db_password_hash, $role_id);
        $stmt->fetch();

        if (password_verify($password, $db_password_hash)) {
            $_SESSION["loggedin"] = true;
            $_SESSION["admin_id"] = $admin_id;
            $_SESSION["email"] = $db_email;
            $_SESSION["username"] = $db_username;
            $_SESSION["role_id"] = $role_id;

            // Update last_logged_in
            $updateLogin = "UPDATE adminusers 
                            SET last_logged_in = NOW(), last_logged_out = NULL 
                            WHERE admin_id = ?";
            $stmtUpdate = $conn->prepare($updateLogin);
            $stmtUpdate->bind_param("i", $admin_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            // ✅ Redirect based on role_id from `roles` table
            if ($role_id == 1) {
                header("Location: superadmin_dashboard.php"); // Super Admin
            } elseif ($role_id == 2) {
                header("Location: dashboard.php"); // Staff
            } elseif ($role_id == 3) {
                header("Location: manager_dashboard.php"); // Manager
            } elseif ($role_id == 0) {
                header("Location: cashier_pos.php"); // Cashier
            } else {
                header("Location: dashboard.php"); // Fallback
            }
            exit;
        }
    }
    $stmt->close();

    // If not admin, check if it's a customer
    $sql = "SELECT customer_id, email, password_hash 
            FROM customers 
            WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $login_input);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($customer_id, $db_email, $db_password_hash);
        $stmt->fetch();

        if (password_verify($password, $db_password_hash)) {
            $_SESSION["loggedin"] = true;
            $_SESSION["customer_id"] = $customer_id;
            $_SESSION["email"] = $db_email;
            $_SESSION["role"] = "Customer";

            header("Location: customerside/homepage.php");
            exit;
        }
    }

    $stmt->close();
    $conn->close();

    $error = "Invalid username/email or password.";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Load Google Font: Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
</head>
<body class="bg-gradient-to-r from-pink-100 to-pink-200 min-h-screen flex items-center justify-center">
  <div class="bg-white shadow-xl rounded-2xl p-10 w-full max-w-md transition-transform duration-300 transform hover:scale-105">
    
    <!-- Logo -->
    <div class="flex justify-center mb-6">
      <img src="logo2.png" alt="Seven Dwarfs Logo" class="h-24 w-auto shadow-md" />
    </div>

    <h2 class="text-2xl font-bold text-center text-pink-600 mb-4">Admin Login</h2>

    <?php if (!empty($error)) echo "<p class='text-red-500 text-center mb-4'>$error</p>"; ?>

    <form method="post" class="space-y-4">
  <div>
    <label class="block text-sm font-medium text-gray-700">Username or Email</label>
    <input type="text" name="login" required
           class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md 
                  focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Password</label>
    <div class="relative">
      <input type="password" name="password" id="password" required
             class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md 
                    focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent pr-10" />
      <button type="button" onclick="togglePassword()"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-sm text-gray-500 
                     hover:text-pink-500 focus:outline-none">
        Show
      </button>
    </div>
  </div>

  <div class="pt-4">
    <input type="submit" value="Login"
           class="w-full bg-pink-500 text-white py-2 rounded-md font-semibold hover:bg-pink-600 transition-colors" />
  </div>
</form>

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById("password");
      const button = event.currentTarget;
      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        button.textContent = "Hide";
      } else {
        passwordInput.type = "password";
        button.textContent = "Show";
      }
    }
  </script>
</body>
</html>
