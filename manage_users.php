<?php
session_start();
require 'conn.php';

// ✅ Restrict access to Super Admin only
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    header("Location: superadmin_login.php");
    exit();
}

// ✅ Fetch all users with their roles
$sql = "SELECT u.admin_id, u.username, u.admin_email, u.first_name, u.last_name, 
               r.role_name, u.status_id, u.last_logged_in, u.last_logged_out
        FROM adminusers u
        JOIN roles r ON u.role_id = r.role_id
        ORDER BY u.admin_id ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Users</title>
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
      <a href="manage_users.php" class="block px-4 py-2 rounded-lg bg-pink-500">👥 Manage Users</a>
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
    <!-- Header with toggle + back -->
    <header class="flex justify-between items-center mb-6">
      <div class="flex items-center space-x-4">
        <!-- Sidebar toggle -->
        <button id="toggleSidebar" class="text-pink-600 text-2xl focus:outline-none">☰</button>
        <h2 class="text-3xl font-bold text-pink-600">👥 Manage Users</h2>
      </div>
      <!-- Back button -->
      <a href="superadmin_dashboard.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium shadow hover:bg-gray-300 transition">
        ⬅ Back
      </a>
    </header>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['message'])) { ?>
      <div class="mb-4 px-4 py-2 rounded bg-red-100 text-red-700 font-medium">
        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
      </div>
    <?php } ?>
    <?php if (isset($_SESSION['success'])) { ?>
      <div class="mb-4 px-4 py-2 rounded bg-green-100 text-green-700 font-medium">
        <?= $_SESSION['success']; unset($_SESSION['success']); ?>
      </div>
    <?php } ?>

    <!-- Users Table -->
    <div class="overflow-x-auto bg-white p-6 rounded-xl shadow-lg">
      <table class="min-w-full border border-gray-200">
        <thead class="bg-pink-500 text-white">
          <tr>
            <th class="px-4 py-2 text-left">ID</th>
            <th class="px-4 py-2 text-left">Username</th>
            <th class="px-4 py-2 text-left">Name</th>
            <th class="px-4 py-2 text-left">Email</th>
            <th class="px-4 py-2 text-left">Status</th>

            <th class="px-4 py-2 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php while ($row = $result->fetch_assoc()) { ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-2"><?= $row['admin_id']; ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['username']); ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['admin_email']); ?></td>
              <td class="px-4 py-2">
                <?php if ($row['status_id'] == 1) { ?>
                  <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-700">Active</span>
                <?php } else { ?>
                  <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-700">Inactive</span>
                <?php } ?>
              </td>
    
              <td class="px-4 py-2 text-center space-x-2">
                <a href="edit_user.php?id=<?= $row['admin_id']; ?>" class="text-blue-500 hover:underline">Edit</a>
                <?php if ($row['status_id'] == 1) { ?>
                  <a href="toggle_user.php?id=<?= $row['admin_id']; ?>&status=0" class="text-yellow-500 hover:underline">Deactivate</a>
                <?php } else { ?>
                  <a href="toggle_user.php?id=<?= $row['admin_id']; ?>&status=1" class="text-green-500 hover:underline">Activate</a>
                <?php } ?>
                <a href="delete_user.php?id=<?= $row['admin_id']; ?>" onclick="return confirm('Are you sure you want to delete this user?')" class="text-red-500 hover:underline">Delete</a>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
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
