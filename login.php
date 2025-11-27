<?php
ob_start(); // 🟢 1. Fixes header issues caused by spaces
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'conn.php';

// Check for spaces in conn.php
if (headers_sent($file, $line)) {
    die("⚠️ Error: Output started in $file on line $line. Remove spaces before <?php in that file.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["login"])) {
    $login_input = trim($_POST["login"]); 
    $password = $_POST["password"];

    echo "<div style='background:yellow; padding:10px; border:1px solid black; z-index:9999; position:relative;'>";
    echo "<strong>DEBUG MODE:</strong><br>";
    echo "Login Input: " . htmlspecialchars($login_input) . "<br>";

    // 🔹 1. Check Admin/Staff
    $sql = "SELECT admin_id, admin_email, username, password_hash, role_id 
            FROM adminusers 
            WHERE admin_email = ? OR username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $login_input, $login_input);
    $stmt->execute();
    $stmt->store_result();

    echo "Admin Match Found: " . ($stmt->num_rows > 0 ? "YES" : "NO") . "<br>";

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($admin_id, $db_email, $db_username, $db_password_hash, $role_id);
        $stmt->fetch();

        echo "Stored Hash: " . substr($db_password_hash, 0, 10) . "...<br>";
        echo "Role ID found: " . $role_id . "<br>";

        if (password_verify($password, $db_password_hash)) {
            echo "<span style='color:green'>✔ Password Verified!</span><br>";
            
            $_SESSION["loggedin"] = true;
            $_SESSION["admin_id"] = $admin_id;
            $_SESSION["role_id"] = $role_id;

            // ... (Logging Logic Skipped for Debug) ...

            if ($role_id == 2) {
                echo "Attempting Redirect to: <strong>dashboard.php</strong>";
                header("Location: dashboard.php");
                exit;
            } elseif ($role_id == 1) {
                echo "Attempting Redirect to: <strong>cashier_pos.php</strong>";
                header("Location: cashier_pos.php");
                exit;
            } else {
                echo "<span style='color:red'>❌ Role ID is not 1 or 2. It is $role_id. Script sends back to login.</span>";
            }
            exit;
        } else {
            echo "<span style='color:red'>❌ Admin Password Verification FAILED. Hash mismatch.</span><br>";
        }
    }
    $stmt->close();

    // 🔹 2. Check Customer
    $sql = "SELECT customer_id, email, password_hash FROM customers WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $login_input);
    $stmt->execute();
    $stmt->store_result();

    echo "Customer Match Found: " . ($stmt->num_rows > 0 ? "YES" : "NO") . "<br>";

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($customer_id, $db_email, $db_password_hash);
        $stmt->fetch();

        if (password_verify($password, $db_password_hash)) {
            echo "<span style='color:green'>✔ Customer Password Verified!</span><br>";
            echo "Attempting Redirect to: customerside/homepage.php";
            $_SESSION["loggedin"] = true;
            $_SESSION["role"] = "Customer";
            header("Location: customerside/homepage.php");
            exit;
        } else {
             echo "<span style='color:red'>❌ Customer Password Verification FAILED.</span><br>";
        }
    }

    echo "</div>"; // End Debug
    $stmt->close();
}
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
  <link rel="stylesheet" href="css/login.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-pink-200 via-white to-pink-400">
  <div class="glassmorphism-container w-full max-w-md p-8 rounded-3xl shadow-2xl border border-pink-200 backdrop-blur-lg transition-transform duration-300 hover:scale-105">
      <!-- Logo -->
      <div class="flex flex-col items-center mb-6">
        <img src="New logo.jpg" alt="Seven Dwarfs Logo" class="h-32 w-32 shadow-lg rounded-full border-4 border-pink-200 mb-2" />
        <h2 class="text-4xl font-extrabold text-center text-pink-600 mt-2 tracking-wide drop-shadow">Seven Dwarfs</h2>
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