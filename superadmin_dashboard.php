<?php
session_start();
require 'conn.php'; // <-- your DB connection file

// Check if logged in and is Super Admin (Owner = role_id = 1)
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$checkRole = $conn->prepare("SELECT role_id FROM adminusers WHERE admin_id = ?");
$checkRole->bind_param("i", $admin_id);
$checkRole->execute();
$roleRes = $checkRole->get_result()->fetch_assoc();
if (!$roleRes || $roleRes['role_id'] != 1) {
    // Not super admin
    header("Location: users.php");
    exit();
}

// Fetch counts
$totalUsers = $conn->query("SELECT COUNT(*) AS c FROM adminusers")->fetch_assoc()['c'];
$totalRoles = $conn->query("SELECT COUNT(*) AS c FROM roles")->fetch_assoc()['c'];
$totalLogs  = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c']; // using orders as logs for demo

// Get Super Admin name
$admin = $conn->query("SELECT first_name, last_name FROM adminusers WHERE admin_id = $admin_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex min-h-screen">

  <!-- Sidebar -->
  <aside id="sidebar" class="w-64 bg-pink-600 text-white flex flex-col transition-all duration-300 ease-in-out">
    <div class="px-6 py-4 text-2xl font-bold border-b border-pink-500">
      Super Admin
    </div>
    <nav class="flex-1 px-4 py-6 space-y-3">
      <a href="superadmin_dashboard.php" class="block px-4 py-2 rounded-lg hover:bg-pink-500">📊 Dashboard</a>
      <a href="manage_users.php" class="block px-4 py-2 rounded-lg hover:bg-pink-500">👥 Manage Users</a>
      <a href="manage_roles.php" class="block px-4 py-2 rounded-lg hover:bg-pink-500">🔑 Manage Roles</a>
      <a href="system_settings.php" class="block px-4 py-2 rounded-lg hover:bg-pink-500">⚙️ System Settings</a>
      <a href="logs.php" class="block px-4 py-2 rounded-lg hover:bg-pink-500">📜 Logs</a>
    </nav>
    <div class="px-6 py-4 border-t border-pink-500">
      <a href="logout.php" class="w-full inline-block text-center bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg font-medium">
        🚪 Logout
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8 transition-all duration-300 ease-in-out">
    <!-- Top Header -->
    <header class="flex justify-between items-center mb-8">
      <div class="flex items-center space-x-4">
        <!-- Hamburger button -->
        <button id="toggleSidebar" class="text-pink-600 text-2xl focus:outline-none">
          ☰
        </button>
        <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
      </div>
      <div class="flex items-center space-x-4">
        <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($admin['first_name'] . " " . $admin['last_name']); ?></span>
        <img src="https://i.pravatar.cc/40" alt="profile" class="rounded-full w-10 h-10 border-2 border-pink-500">
      </div>
    </header>

    <!-- Stats Section -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">Total Users</h2>
        <p class="text-3xl font-bold text-gray-800"><?php echo $totalUsers; ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">Active Roles</h2>
        <p class="text-3xl font-bold text-gray-800"><?php echo $totalRoles; ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">System Logs</h2>
        <p class="text-3xl font-bold text-gray-800"><?php echo $totalLogs; ?></p>
      </div>
    </section>

    <!-- Management Panels -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4">👥 Manage Users</h2>
        <p class="text-gray-600 mb-4">Add, edit, deactivate, or delete users in the system.</p>
        <a href="manage_users.php" class="bg-pink-500 text-white px-4 py-2 rounded-lg hover:bg-pink-600 inline-block">
          Go to Users
        </a>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4">🔑 Manage Roles</h2>
        <p class="text-gray-600 mb-4">Define and assign roles with specific permissions.</p>
        <a href="manage_roles.php" class="bg-pink-500 text-white px-4 py-2 rounded-lg hover:bg-pink-600 inline-block">
          Go to Roles
        </a>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4">⚙️ System Settings</h2>
        <p class="text-gray-600 mb-4">Configure system preferences, policies, and security.</p>
        <a href="system_settings.php" class="bg-pink-500 text-white px-4 py-2 rounded-lg hover:bg-pink-600 inline-block">
          Go to Settings
        </a>
      </div>

      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-bold text-gray-700 mb-4">📜 System Logs</h2>
        <p class="text-gray-600 mb-4">Track all system activities and login history.</p>
        <a href="logs.php" class="bg-pink-500 text-white px-4 py-2 rounded-lg hover:bg-pink-600 inline-block">
          View Logs
        </a>
      </div>
    </section>
  </main>

  <script>
    const toggleBtn = document.getElementById("toggleSidebar");
    const sidebar = document.getElementById("sidebar");

    toggleBtn.addEventListener("click", () => {
      sidebar.classList.toggle("w-64");
      sidebar.classList.toggle("w-0");
      sidebar.classList.toggle("overflow-hidden");
    });
  </script>
</body>
</html>
