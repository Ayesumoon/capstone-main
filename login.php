<?php
session_start();
require 'conn.php'; // Database connection

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login"]) && isset($_POST["password"])) {
    $login_input = trim($_POST["login"]); // can be username OR email
    $password = $_POST["password"];

    // 🔹 1. Check if it's an admin (username OR email)
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

            // 🔹 Update last_logged_in
            $updateLogin = "UPDATE adminusers 
                            SET last_logged_in = NOW(), last_logged_out = NULL 
                            WHERE admin_id = ?";
            $stmtUpdate = $conn->prepare($updateLogin);
            $stmtUpdate->bind_param("i", $admin_id);
            $stmtUpdate->execute();
            $stmtUpdate->close();

            // 🔹 Get the role name from roles table
            $roleQuery = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
            $roleQuery->bind_param("i", $role_id);
            $roleQuery->execute();
            $roleResult = $roleQuery->get_result();
            $roleRow = $roleResult->fetch_assoc();
            $role_name = $roleRow['role_name'] ?? 'Unknown';
            $roleQuery->close();

            // 🔹 Insert into system_logs (properly stores role_id)
            $log_sql = "INSERT INTO system_logs (user_id, username, role_id, action) 
                        VALUES (?, ?, ?, 'Login')";
            $stmtLog = $conn->prepare($log_sql);
            $stmtLog->bind_param("isi", $admin_id, $db_username, $role_id);
            $stmtLog->execute();
            $stmtLog->close();

            // 🔹 Redirect based on role
            if ($role_name === 'Admin') {
                header("Location: dashboard.php");
            } elseif ($role_name === 'Cashier') {
                header("Location: cashier_pos.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        }
    }
    $stmt->close();

    // 🔹 2. If not admin, check if it's a customer
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
    // Do NOT close $conn here, let the rest of the page use it

    $error = "Invalid username/email or password.";
}

// Forgot password handler
// (Removed as per request)
?>
<?php
// Close the connection at the end of the script
$conn->close();
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
  <div class="bg-white/80 shadow-2xl border border-pink-200 rounded-2xl p-10 w-full max-w-md backdrop-blur-lg transition-transform duration-300 transform hover:scale-105">
      <!-- Logo -->
      <div class="flex flex-col items-center mb-6">
        <img src="logo2.png" alt="Seven Dwarfs Logo" class="h-20 w-auto shadow-lg rounded-full border-4 border-pink-200 mb-2" />
        <h2 class="text-3xl font-extrabold text-center text-pink-600 mt-2 tracking-wide drop-shadow">Seven Dwarfs</h2>
      </div>
      <h3 class="text-xl font-semibold text-center text-gray-700 mb-6">Admin Login</h3>
      <?php if (!empty($error)) echo "<p class='text-red-500 text-center mb-4'>$error</p>"; ?>
      <form method="post" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username or Email</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-pink-400">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/>
                <path d="M12 16v2m0 0a6 6 0 1 1 0-12 6 6 0 0 1 0 12z"/>
              </svg>
            </span>
            <input type="text" name="login" required
              class="mt-1 w-full pl-10 px-4 py-2 border border-gray-300 rounded-md bg-white/90
                focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent transition" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-pink-400">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 17a5 5 0 0 0 5-5V9a5 5 0 0 0-10 0v3a5 5 0 0 0 5 5z"/>
                <path d="M12 17v2m0 0a6 6 0 1 1 0-12 6 6 0 0 1 0 12z"/>
              </svg>
            </span>
            <input type="password" name="password" id="password" required
              class="mt-1 w-full pl-10 px-4 py-2 border border-gray-300 rounded-md bg-white/90
                focus:outline-none focus:ring-2 focus:ring-pink-400 focus:border-transparent pr-10 transition" />
            <button type="button" onclick="togglePassword()"
              class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-pink-500 focus:outline-none">
              <svg id="eyeIcon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <!-- Forgot password link removed as per request -->
        </div>
        <div class="pt-2">
          <button type="submit"
            class="w-full bg-gradient-to-r from-pink-400 to-pink-500 text-white py-2 rounded-md font-semibold shadow-lg hover:scale-105 hover:from-pink-500 hover:to-pink-600 transition-all duration-200">
            Login
          </button>
        </div>
      </form>

   <!-- Forgot Password Modal removed as per request -->
  <script>
    function togglePassword() {
      const passwordInput = document.getElementById("password");
      const eyeIcon = document.getElementById("eyeIcon");
      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        eyeIcon.innerHTML = '<path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.77 21.77 0 0 1 5.06-7.94M1 1l22 22"/><circle cx="12" cy="12" r="3"/>';
      } else {
        passwordInput.type = "password";
        eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
      }
    }
  </script>
</body>
</html>