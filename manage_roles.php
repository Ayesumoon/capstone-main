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
  <title>Manage Roles & Users | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --rose: #e59ca8;
      --rose-hover: #d27b8c;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
      color: #374151;
    }
    .active {
      background-color: #fce8eb;
      color: var(--rose);
      font-weight: 600;
      border-radius: 0.5rem;
    }
  </style>
</head>
<body class="min-h-screen flex bg-gray-100">

<!-- 🧭 Sidebar -->
<aside class="w-64 bg-white shadow-md flex flex-col justify-between" x-data="{ userMenu: true, productMenu: false }">
  <div class="p-5 border-b">
    <div class="flex items-center space-x-3">
      <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
    </div>
  </div>

  <div class="p-5 border-b flex items-center space-x-3">
    <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
    <div>
      <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
      <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 p-4 space-y-1">
    <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition">
      <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
    </a>

    <!-- User Management -->
    <div>
      <button @click="userMenu = !userMenu"
        class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
        <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
      </button>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1 mt-1">
        <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
        <li><a href="manage_roles.php" class="block py-1 active"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a></li>
        <li><a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customers</a></li>
      </ul>
    </div>

    <!-- Product Management -->
    <div>
      <button @click="productMenu = !productMenu"
        class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
        <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
      </button>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1 mt-1">
        <li><a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Products</a></li>
        <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
        <li><a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
      </ul>
    </div>

    <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
    <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

    <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-md transition"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
  </nav>
</aside>

<!-- 🧩 Main Content -->
<main class="flex-1 p-8 transition-all duration-300">
  <header class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Manage Roles</h1>
  </header>

  <!-- Flash Messages -->
  <?php if (isset($_SESSION['message'])): ?>
    <div class="mb-4 px-4 py-2 rounded bg-red-100 text-red-700 font-medium shadow-sm">
      <?= $_SESSION['message']; unset($_SESSION['message']); ?>
    </div>
  <?php endif; ?>
  <?php if (isset($_SESSION['success'])): ?>
    <div class="mb-4 px-4 py-2 rounded bg-green-100 text-green-700 font-medium shadow-sm">
      <?= $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
  <?php endif; ?>

  <!-- Users Table -->
  <section class="bg-white p-6 rounded-xl shadow-md">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold text-gray-700">👥 Users & Roles</h2>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left">ID</th>
            <th class="px-4 py-3 text-left">Username</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Email</th>
            <th class="px-4 py-3 text-left">Role</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <?php while ($row = $users->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-3"><?= $row['admin_id']; ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['username']); ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['first_name'] . " " . $row['last_name']); ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['admin_email']); ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['role_name']); ?></td>
              <td class="px-4 py-3">
                <?php if ($row['status_id'] == 1): ?>
                  <span class="px-2 py-1 text-xs rounded bg-green-100 text-green-700">Active</span>
                <?php else: ?>
                  <span class="px-2 py-1 text-xs rounded bg-red-100 text-red-700">Inactive</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-center space-x-3">
                <button onclick="openEditModal(<?= $row['admin_id']; ?>, <?= $row['role_id']; ?>)" class="text-blue-500 hover:text-blue-700 font-medium">Edit Role</button>
                <?php if ($row['role_id'] != 2): ?>
                  <button onclick="openDeleteModal(<?= $row['admin_id']; ?>)" class="text-red-500 hover:text-red-700 font-medium">Delete Role</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<!-- ✏️ Edit Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-xl shadow-lg w-96">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Edit User Role</h2>
    <form method="POST" action="update_user_role.php">
      <input type="hidden" name="admin_id" id="editAdminId">
      <select name="role_id" id="editRoleSelect" class="w-full border border-gray-300 rounded-lg p-2 mb-4 focus:ring-2 focus:ring-[var(--rose)]">
        <?php foreach ($roles as $role): ?>
          <option value="<?= $role['role_id']; ?>"><?= htmlspecialchars($role['role_name']); ?></option>
        <?php endforeach; ?>
      </select>
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-[var(--rose)] text-white rounded-lg hover:bg-[var(--rose-hover)] transition">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- 🗑️ Delete Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-xl shadow-lg w-96">
    <h2 class="text-xl font-semibold text-gray-800 mb-3">Delete User Role</h2>
    <p class="mb-4 text-gray-600">Are you sure you want to delete this user’s role?</p>
    <form method="POST" action="delete_user_role.php">
      <input type="hidden" name="admin_id" id="deleteAdminId">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
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
