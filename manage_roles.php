<?php
session_start();
require 'conn.php';


// ✅ Restrict access to Super Admin only
if (!isset($_SESSION['admin_id']) || $_SESSION['role_id'] != 1) {
    header("Location: superadmin_login.php");
    exit();
}

// ✅ Fetch roles
$roles = $conn->query("SELECT * FROM roles ORDER BY role_id ASC")->fetch_all(MYSQLI_ASSOC);

// ✅ Fetch users with roles
$sql = "SELECT u.admin_id, u.username, u.admin_email, u.first_name, u.last_name, 
               r.role_name, u.role_id, u.status_id, u.last_logged_in, u.last_logged_out
        FROM adminusers u
        JOIN roles r ON u.role_id = r.role_id
        ORDER BY u.admin_id ASC";
$users = $conn->query($sql);

// ✅ Handle Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_role'])) {
    $role_id = intval($_POST['role_id']);
    $role_name = trim($_POST['role_name']);

    if ($role_id == 1) {
        $_SESSION['message'] = "Super Admin role cannot be renamed!";
    } else {
        $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
        $stmt->bind_param("si", $role_name, $role_id);
        $stmt->execute();
        $_SESSION['success'] = "Role updated successfully!";
        $stmt->close();
    }
    header("Location: manage_roles.php");
    exit();
}

// ✅ Handle Delete Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    $role_id = intval($_POST['role_id']);

    if ($role_id == 1) {
        $_SESSION['message'] = "Super Admin role cannot be deleted!";
    } else {
        $stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
        $stmt->bind_param("i", $role_id);
        $stmt->execute();
        $_SESSION['success'] = "Role deleted successfully!";
        $stmt->close();
    }
    header("Location: manage_roles.php");
    exit();
}

// ✅ Fetch roles
$roles = $conn->query("SELECT * FROM roles ORDER BY role_id ASC")->fetch_all(MYSQLI_ASSOC);

// ✅ Fetch users with roles
$sql = "SELECT u.admin_id, u.username, u.admin_email, u.first_name, u.last_name, 
               r.role_name, u.role_id, u.status_id, u.last_logged_in, u.last_logged_out
        FROM adminusers u
        JOIN roles r ON u.role_id = r.role_id
        ORDER BY u.admin_id ASC";

$users = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Roles & Users</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <aside id="sidebar" class="w-64 bg-white border-r border-gray-200 text-gray-700 flex flex-col transition-all duration-300 ease-in-out">
    <div class="px-6 py-4 text-2xl font-bold border-b border-gray-200 text-gray-800">
      Super Admin
    </div>
    <nav class="flex-1 px-4 py-6 space-y-2">
      <a href="superadmin_dashboard.php" class="block px-4 py-2 rounded-lg hover:bg-gray-100 hover:text-blue-600">📊 Dashboard</a>
      <a href="manage_users.php" class="block px-4 py-2 rounded-lg hover:bg-gray-100 hover:text-blue-600">👥 Manage Users</a>
      <a href="manage_roles.php" class="block px-4 py-2 rounded-lg bg-blue-50 text-blue-600 font-medium">🔑 Manage Roles</a>
      <a href="logs.php" class="block px-4 py-2 rounded-lg hover:bg-gray-100 hover:text-blue-600">📜 Logs</a>
    </nav>
    <div class="px-6 py-4 border-t border-gray-200">
      <a href="logout.php" class="w-full block text-center bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg font-medium text-white">
        🚪 Logout
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8 transition-all duration-300 ease-in-out">
    <header class="flex justify-between items-center mb-8">
      <div class="flex items-center space-x-4">
        <!-- Sidebar toggle -->
        <button id="toggleSidebar" class="text-gray-600 text-2xl focus:outline-none">☰</button>
        <h1 class="text-3xl font-bold text-gray-800">Manage Roles & Users</h1>
      </div>
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
    <div class="bg-white p-6 rounded-xl shadow-md">
      <h2 class="text-xl font-bold text-gray-700 mb-4">👥 Users & Roles</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200">
          <thead class="bg-gray-100 text-gray-700">
            <tr>
              <th class="px-4 py-2 text-left">ID</th>
              <th class="px-4 py-2 text-left">Username</th>
              <th class="px-4 py-2 text-left">Name</th>
              <th class="px-4 py-2 text-left">Email</th>
              <th class="px-4 py-2 text-left">Role</th>
              <th class="px-4 py-2 text-left">Status</th>
              <th class="px-4 py-2 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($row = $users->fetch_assoc()) { ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-2"><?= $row['admin_id']; ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['username']); ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['admin_email']); ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['role_name']); ?></td>
                <td class="px-4 py-2">
                  <?php if ($row['status_id'] == 1) { ?>
                    <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-700">Active</span>
                  <?php } else { ?>
                    <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-700">Inactive</span>
                  <?php } ?>
                </td>
                <td class="px-4 py-2 text-center space-x-2">
                  <button onclick="openEditModal(<?= $row['admin_id']; ?>, <?= $row['role_id']; ?>)" class="text-blue-500 hover:underline">Edit Role</button>
                  <?php if ($row['role_id'] != 1) { ?>
                    <button onclick="openDeleteModal(<?= $row['admin_id']; ?>)" class="text-red-500 hover:underline">Delete Role</button>
                  <?php } ?>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>


  <!-- Edit Role Modal -->
  <div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-96 shadow-lg">
      <h2 class="text-xl font-bold text-gray-700 mb-4">Edit User Role</h2>
      <form method="POST" action="update_user_role.php">
        <input type="hidden" name="admin_id" id="editAdminId">
        <select name="role_id" id="editRoleSelect"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-400 mb-4">
          <?php foreach ($roles as $role) { ?>
            <option value="<?= $role['role_id']; ?>"><?= htmlspecialchars($role['role_name']); ?></option>
          <?php } ?>
        </select>
        <div class="flex justify-end space-x-3">
          <button type="button" onclick="closeModal('editModal')"
                  class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Role Modal -->
  <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-96 shadow-lg">
      <h2 class="text-xl font-bold text-gray-700 mb-4">Delete User Role</h2>
      <p class="mb-4 text-gray-600">Are you sure you want to delete this user’s role?</p>
      <form method="POST" action="delete_user_role.php">
        <input type="hidden" name="admin_id" id="deleteAdminId">
        <div class="flex justify-end space-x-3">
          <button type="button" onclick="closeModal('deleteModal')"
                  class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const toggleBtn = document.getElementById("toggleSidebar");
    const sidebar = document.getElementById("sidebar");
    toggleBtn.addEventListener("click", () => {
      sidebar.classList.toggle("w-64");
      sidebar.classList.toggle("w-0");
      sidebar.classList.toggle("overflow-hidden");
    });

    function openEditModal(adminId, roleId) {
      document.getElementById("editAdminId").value = adminId;
      document.getElementById("editRoleSelect").value = roleId;
      openModal("editModal");
    }

    function openDeleteModal(adminId) {
      document.getElementById("deleteAdminId").value = adminId;
      openModal("deleteModal");
    }

    function openModal(id) {
      document.getElementById(id).classList.remove("hidden");
    }

    function closeModal(id) {
      document.getElementById(id).classList.add("hidden");
    }
  </script>
</body>
</html>
