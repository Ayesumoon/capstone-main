<?php
session_start();
require 'conn.php';

// 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// 🧩 Fetch current admin details
$stmt = $conn->prepare("
    SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, r.role_name 
    FROM adminusers a 
    LEFT JOIN roles r ON a.role_id = r.role_id
    WHERE a.admin_id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_name = $admin['full_name'] ?? "Admin";
$admin_role = $admin['role_name'] ?? "Administrator";

// ✅ Handle Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_role'])) {
    $role_id = intval($_POST['role_id']);
    $role_name = trim($_POST['role_name']);

    // Admin = 2 → cannot rename
    if ($role_id == 2) {
        $_SESSION['message'] = "Admin role cannot be renamed!";
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

    if ($role_id == 2) { // Admin = 2 (protected)
        $_SESSION['message'] = "Admin role cannot be deleted!";
    } else {
        // Check if role is assigned to users
        $check = $conn->prepare("SELECT COUNT(*) FROM adminusers WHERE role_id = ?");
        $check->bind_param("i", $role_id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['message'] = "Cannot delete role assigned to users!";
        } else {
            $stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
            $stmt->bind_param("i", $role_id);
            $stmt->execute();
            $_SESSION['success'] = "Role deleted successfully!";
            $stmt->close();
        }
    }
    header("Location: manage_roles.php");
    exit();
}

// ✅ Fetch roles
$roles_query = $conn->query("SELECT * FROM roles ORDER BY role_id ASC");
$roles = $roles_query ? $roles_query->fetch_all(MYSQLI_ASSOC) : [];

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
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; }
.active { background-color: #fef2f4; color: #d37689; font-weight: 600; }
</style>
</head>
<body class="bg-gray-100 min-h-screen flex">

  
<!-- 🧭 Sidebar -->
<aside id="sidebar" class="w-64 bg-white shadow-lg flex flex-col justify-between transition-all duration-300">
  <div class="p-4">
    <div class="flex items-center space-x-3">
      <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
    </div>

    <div class="mt-4 flex items-center space-x-3 border-t pt-3">
      <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
      <div>
        <p class="font-semibold"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <nav class="mt-6 space-y-1" x-data="{ userMenu: true, productMenu: false }">
      <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>

      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100">
          <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <div x-show="userMenu" x-transition class="pl-8 space-y-1">
          <a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Manage Users</a>
          <a href="manage_roles.php" class="block py-1 active"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a>
          <a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customers</a>
        </div>
      </div>

      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100">
          <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
          <i class="fas fa-chevron-down" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <div x-show="productMenu" x-transition class="pl-8 space-y-1">
          <a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i>Category</a>
          <a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Products</a>
          <a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a>
          <a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a>
        </div>
      </div>

      <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
    </nav>
  </div>
</aside>

  <!-- Main Content -->
  <main class="flex-1 p-8 transition-all duration-300 ease-in-out">
    <header class="flex justify-between items-center mb-8">
      <div class="flex items-center space-x-4">
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
                  <?php if ($row['role_id'] != 2) { ?>
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

  <!-- Modals -->
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
          <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-96 shadow-lg">
      <h2 class="text-xl font-bold text-gray-700 mb-4">Delete User Role</h2>
      <p class="mb-4 text-gray-600">Are you sure you want to delete this user’s role?</p>
      <form method="POST" action="delete_user_role.php">
        <input type="hidden" name="admin_id" id="deleteAdminId">
        <div class="flex justify-end space-x-3">
          <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-300 rounded-lg">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openEditModal(adminId, roleId) {
      document.getElementById("editAdminId").value = adminId;
      document.getElementById("editRoleSelect").value = roleId;
      document.getElementById("editModal").classList.remove("hidden");
    }

    function openDeleteModal(adminId) {
      document.getElementById("deleteAdminId").value = adminId;
      document.getElementById("deleteModal").classList.remove("hidden");
    }

    function closeModal(id) {
      document.getElementById(id).classList.add("hidden");
    }
  </script>
</body>
</html>
