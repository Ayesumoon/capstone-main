<?php
session_start();
require 'conn.php';

// ✅ Restrict to Super Admin only
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    header("Location: superadmin_login.php");
    exit();
}

// ✅ Fetch login/logout logs
$loginLogs = $conn->query("SELECT admin_id, username, last_logged_in, last_logged_out 
                           FROM adminusers ORDER BY last_logged_in DESC");

// ✅ Fetch activity logs
$actionLogs = $conn->query("SELECT log_id, user_id, username, role_id, action, created_at 
                            FROM system_logs ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Logs</title>
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
      <a href="system_logs.php" class="block px-4 py-2 rounded-lg bg-pink-700">📜 Logs</a>
    </nav>
    <div class="px-6 py-4 border-t border-pink-500">
      <a href="superadmin_logout.php" class="block w-full text-center bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg font-medium">
        🚪 Logout
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8 transition-all duration-300 ease-in-out">
    <!-- Top Header -->
    <header class="flex justify-between items-center mb-8">
      <div class="flex items-center space-x-4">
        <button id="toggleSidebar" class="text-pink-600 text-2xl focus:outline-none">☰</button>
        <h1 class="text-3xl font-bold text-gray-800">System Logs</h1>
      </div>
      <div class="flex items-center space-x-4">
        <span class="text-gray-700">Welcome, <?= htmlspecialchars($_SESSION['username']); ?></span>
        <img src="https://i.pravatar.cc/40" alt="profile" class="rounded-full w-10 h-10 border-2 border-pink-500">
      </div>
    </header>

    <!-- Login/Logout Logs -->
    <div class="bg-white p-6 rounded-xl shadow-lg mb-8">
      <h2 class="text-xl font-bold text-pink-600 mb-4">👤 User Login/Logout Logs</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-pink-500 text-white">
            <tr>
              <th class="px-4 py-2 text-left">User ID</th>
              <th class="px-4 py-2 text-left">Username</th>
              <th class="px-4 py-2 text-left">Last Login</th>
              <th class="px-4 py-2 text-left">Last Logout</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($row = $loginLogs->fetch_assoc()) { ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-2"><?= $row['admin_id']; ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['username']); ?></td>
                <td class="px-4 py-2 text-sm"><?= $row['last_logged_in'] ?? '—'; ?></td>
                <td class="px-4 py-2 text-sm"><?= $row['last_logged_out'] ?? '—'; ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Action Logs -->
    <div class="bg-white p-6 rounded-xl shadow-lg">
      <h2 class="text-xl font-bold text-pink-600 mb-4">⚡ User Actions</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-pink-500 text-white">
            <tr>
              <th class="px-4 py-2 text-left">Log ID</th>
              <th class="px-4 py-2 text-left">User ID</th>
              <th class="px-4 py-2 text-left">Username</th>
              <th class="px-4 py-2 text-left">Role</th>
              <th class="px-4 py-2 text-left">Action</th>
              <th class="px-4 py-2 text-left">Timestamp</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($row = $actionLogs->fetch_assoc()) { ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-2"><?= $row['log_id']; ?></td>
                <td class="px-4 py-2"><?= $row['user_id']; ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['username']); ?></td>
                <td class="px-4 py-2"><?= ($row['role_id'] == 1) ? 'Super Admin' : 'Admin'; ?></td>
                <td class="px-4 py-2 text-sm"><?= htmlspecialchars($row['action']); ?></td>
                <td class="px-4 py-2 text-sm"><?= $row['created_at']; ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- Sidebar Toggle Script -->
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
