<?php
session_start();
require 'conn.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT admin_id, admin_email, password_hash, role_id, username FROM adminusers WHERE admin_email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($admin_id, $db_email, $db_password, $role, $username);
        $stmt->fetch();

        if (password_verify($password, $db_password)) {
            $_SESSION["loggedin"] = true;
            $_SESSION["user_id"] = $admin_id;
            $_SESSION["email"] = $db_email;
            $_SESSION["role"] = $role;
            $_SESSION["username"] = $username; // Store username in session

            // Update last_logged_in timestamp
            $updateLogin = "UPDATE adminusers SET last_logged_in = NOW() WHERE admin_id = ?";
            $stmtUpdate = $conn->prepare($updateLogin);
            $stmtUpdate->bind_param("i", $admin_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            header("Location: dashboard.php");
            exit;
        }
    }
    
    $stmt->close();

    // If not an admin, check if it's a customer
    $sql = "SELECT customer_id, email, password_hash FROM customers WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($customer_id, $db_email, $db_password_hash);
        $stmt->fetch();

        // Verify password for customer
        if (password_verify($password, $db_password_hash)) { // Fixed variable
            $_SESSION["loggedin"] = true;
            $_SESSION["user_id"] = $customer_id;
            $_SESSION["email"] = $db_email;
            $_SESSION["role"] = "Customer";

            // Reset last_logged_out when logging in
            $resetLogout = "UPDATE adminusers SET last_logged_out = NULL WHERE admin_id = ?";
            $stmtReset = $conn->prepare($resetLogout);
            $stmtReset->bind_param("i", $admin_id);
            $stmtReset->execute();
            $stmtReset->close();


            header("Location: customerside/homepage.php"); // Redirect to customer page
            exit;
        }
    }

    $stmt->close();
    $conn->close();
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
        <label class="block text-sm font-medium text-gray-700">Email</label>
        <input type="email" name="email" required
               class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Password</label>
        <div class="relative">
          <input type="password" name="password" id="password" required
                 class="mt-1 w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent pr-10" />
          <button type="button" onclick="togglePassword()"
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-sm text-gray-500 hover:text-pink-500 focus:outline-none">
            Show
          </button>
        </div>
      </div>

      <div class="pt-4">
        <input type="submit" value="Login"
               class="w-full bg-pink-500 text-white py-2 rounded-md font-semibold hover:bg-pink-600 transition-colors" />
      </div>
    </form>

    <!-- Sign Up button -->
    <div class="mt-6 text-center">
      <p class="text-sm text-gray-600">Don't have an account?</p>
      <a href="signup.php" class="inline-block mt-2 px-4 py-2 bg-white border border-pink-500 text-pink-600 rounded-md font-semibold hover:bg-pink-50 transition-colors">
        Sign Up
      </a>
    </div>
  </div>

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
