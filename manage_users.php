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
    SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, 
           r.role_name, a.role_id 
    FROM adminusers a 
    LEFT JOIN roles r ON a.role_id = r.role_id
    WHERE a.admin_id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? "Admin";
$admin_role = $admin['role_name'] ?? "Administrator";
$admin_role_id = $admin['role_id'] ?? 0;

// 🧩 Fetch all users with their roles and status
$query = "
    SELECT u.admin_id, u.username, u.admin_email, 
           CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
           r.role_name, r.role_id, 
           s.status_id, s.status_name
    FROM adminusers u
    LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN status s ON u.status_id = s.status_id
    ORDER BY u.admin_id ASC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { --rose: #d37689; --rose-hover: #b75f6f; }
body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; }
.active { background-color: #fef2f4; color: #d37689; font-weight: 600; }
</style>
</head>

<body class="flex h-screen text-sm text-gray-700">

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
      <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100">
        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
      </a>

      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100">
          <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <div x-show="userMenu" x-transition class="pl-8 space-y-1">
          <a href="manage_users.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md"><i class="fas fa-user mr-2"></i>Users</a>
          <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Roles</a>
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
            <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transitio"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>

      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-industry mr-2"></i>Suppliers</a>

      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

      <a href="logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
    </nav>
  </div>
</aside>

<!-- 🌸 Main Content -->
<main class="flex-1 p-6 overflow-auto">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-[var(--rose)]">👥 Manage Users</h1>
    <a href="add_user.php" class="bg-[var(--rose)] text-white px-4 py-2 rounded-lg shadow hover:bg-[var(--rose-hover)] transition flex items-center gap-2">
      <i class="fas fa-user-plus"></i> Add User
    </a>
  </div>

  <!-- ✅ Flash Messages -->
  <?php foreach (['message' => 'red', 'success' => 'green'] as $key => $color): ?>
    <?php if (isset($_SESSION[$key])): ?>
      <div class="mb-4 px-4 py-2 rounded bg-<?= $color; ?>-100 text-<?= $color; ?>-700 font-medium">
        <?= $_SESSION[$key]; unset($_SESSION[$key]); ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <!-- 📋 Users Table -->
  <div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full border border-gray-200 text-sm">
      <thead class="bg-gray-100 text-gray-700">
        <tr>
          <th class="px-4 py-2 text-left">Username</th>
          <th class="px-4 py-2 text-left">Name</th>
          <th class="px-4 py-2 text-left">Email</th>
          <th class="px-4 py-2 text-left">Role</th>
          <th class="px-4 py-2 text-left">Status</th>
          <th class="px-4 py-2 text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
  <?php while ($user = $result->fetch_assoc()): ?>
    <tr class="hover:bg-gray-50 transition <?= $user['status_id'] == 2 ? 'opacity-60' : ''; ?>">
      <td class="px-4 py-2"><?= htmlspecialchars($user['username']); ?></td>
      <td class="px-4 py-2"><?= htmlspecialchars($user['full_name']); ?></td>
      <td class="px-4 py-2"><?= htmlspecialchars($user['admin_email']); ?></td>
      <td class="px-4 py-2"><?= htmlspecialchars($user['role_name']); ?></td>
      <td class="px-4 py-2">
        <span class="px-2 py-1 text-xs rounded <?= $user['status_id'] == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
          <?= htmlspecialchars($user['status_name']); ?>
        </span>
      </td>

      <td class="px-4 py-2 text-center space-x-3">

  <!-- ✏️ Edit -->
  <a href="edit_user.php?id=<?= $user['admin_id']; ?>" 
     class="text-blue-600 hover:text-blue-800">
     <i class="fas fa-edit text-lg"></i>
  </a>

  <?php if ($admin_role_id == 2 && $user['admin_id'] != $admin_id): ?>

    <!-- 🔄 Activate / Deactivate -->
    <?php if ($user['status_id'] == 1): ?>
      <a href="toggle_user.php?id=<?= $user['admin_id']; ?>&status=2" 
         class="text-yellow-600 hover:text-yellow-800">
         <i class="fas fa-user-slash text-lg"></i>
      </a>
    <?php else: ?>
      <a href="toggle_user.php?id=<?= $user['admin_id']; ?>&status=1" 
         class="text-green-600 hover:text-green-800">
         <i class="fas fa-user-check text-lg"></i>
      </a>
    <?php endif; ?>

    <!-- 🗑️ Delete -->
    <a href="delete_user.php?id=<?= $user['admin_id']; ?>" 
       onclick="return confirm('Are you sure you want to delete this user?')" 
       class="text-red-600 hover:text-red-800">
       <i class="fas fa-trash text-lg"></i>
    </a>

  <?php endif; ?>

</td>

    </tr>
  <?php endwhile; ?>
</tbody>

    </table>
  </div>
</main>
</body>
</html>
