<?php
require 'conn.php'; // Include your database connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username   = $_POST['username'];
    $first_name = $_POST['first_name'];
    $last_name  = $_POST['last_name'];
    $email      = $_POST['email'];
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id    = 2; // default role (e.g., normal admin)
    $status_id  = 1; // default active

    // Insert into adminusers
    $sql = "INSERT INTO adminusers (username, first_name, last_name, admin_email, password_hash, role_id, status_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $username, $first_name, $last_name, $email, $password, $role_id, $status_id);

    if ($stmt->execute()) {
        header("Location: login.php"); // redirect to login
        exit();
    } else {
        echo "Error: " . $stmt->error;
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
  <title>Sign Up</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Load Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
</head>
<body class="bg-gradient-to-r from-pink-100 to-pink-200 min-h-screen flex items-center justify-center">
  <div class="bg-white shadow-xl rounded-2xl p-10 w-full max-w-xl transition-transform duration-300 transform hover:scale-105">

    <!-- Logo -->
    <div class="flex justify-center mb-6">
      <img src="logo.png" alt="Seven Dwarfs Logo" class="h-24 w-auto shadow-md" />
    </div>

    <h2 class="text-2xl font-bold text-center text-pink-600 mb-4">Sign Up</h2>

    <form method="post" class="space-y-4" onsubmit="return validatePasswords();">
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700">First Name</label>
      <input type="text" name="first_name" required
             class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-pink-400" />
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Last Name</label>
      <input type="text" name="last_name" required
             class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-pink-400" />
    </div>
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Username</label>
    <input type="text" name="username" required
           class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-pink-400" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Email</label>
    <input type="email" name="email" required
           class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-pink-400" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Password</label>
    <input type="password" name="password" id="password" required
           class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-pink-400" />
  </div>

  <div>
    <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
    <input type="password" name="confirm_password" id="confirm_password" required
           class="mt-1 w-full px-4 py-2 border rounded-md focus:ring-2 focus:ring-pink-400" />
  </div>

  <div class="pt-4">
    <input type="submit" value="Sign Up"
           class="w-full bg-pink-500 text-white py-2 rounded-md font-semibold hover:bg-pink-600" />
  </div>

<div class="text-center mt-4">
        <p class="text-sm text-gray-600">Already have an account?</p>
        <a href="login.php" class="inline-block mt-2 px-4 py-2 bg-white border border-pink-500 text-pink-600 rounded-md font-semibold hover:bg-pink-50 transition-colors">
          Log In Here
        </a>
      </div>

</form>


  </div>

  <script>
    function togglePassword(fieldId, button) {
      const input = document.getElementById(fieldId);
      if (input.type === "password") {
        input.type = "text";
        button.textContent = "Hide";
      } else {
        input.type = "password";
        button.textContent = "Show";
      }
    }

    function validatePasswords() {
      const password = document.getElementById("password").value;
      const confirm = document.getElementById("confirm_password").value;

      if (password !== confirm) {
        alert("Passwords do not match.");
        return false;
      }
      return true;
    }
  </script>
</body>
</html>
