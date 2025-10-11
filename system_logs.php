<?php
session_start();
require 'conn.php';

// Verify if logged in
if (!isset($_SESSION["admin_id"])) {
  header("Location: login.php");
  exit;
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = "Admin";
$admin_role = "Admin";

// Fetch logged-in admin details
$query = "
  SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
  FROM adminusers a
  LEFT JOIN roles r ON a.role_id = r.role_id
  WHERE a.admin_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
  $admin_name = $row['full_name'];
  $admin_role = $row['role_name'] ?? 'Admin';
}

// Handle filters
$filter_role = $_GET['role'] ?? '';
$filter_action = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];
$types = '';

if (!empty($filter_role)) {
  $where[] = "l.role = ?";
  $params[] = $filter_role;
  $types .= 's';
}
if (!empty($filter_action)) {
  $where[] = "l.action = ?";
  $params[] = $filter_action;
  $types .= 's';
}
if (!empty($search)) {
  $where[] = "(a.username LIKE ? OR CONCAT(a.first_name, ' ', a.last_name) LIKE ?)";
  $searchTerm = "%$search%";
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $types .= 'ss';
}

$sql = "
  SELECT 
    l.log_id,
    l.user_id,
    l.role_id,
    l.action,
    l.created_at,
    CONCAT(a.first_name, ' ', a.last_name) AS full_name,
    a.username
  FROM system_logs l
  LEFT JOIN adminusers a ON l.user_id = a.admin_id
";

if (!empty($where)) {
  $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>System Logs | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet" />

  <style>
    :root {
      --rose: #e5a5b2;
      --rose-hover: #d48b98;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
      color: #374151;
    }
    [x-cloak] { display: none !important; }
    .active-link {
      background-color: #fef3f5;
      color: var(--rose);
      font-weight: 600;
      border-radius: 0.5rem;
    }
  </style>
</head>

<body class="text-sm" x-data="{ userMenu: false, productMenu: false }">
<div class="flex min-h-screen">

  <!-- 🧭 Sidebar -->
  <aside class="w-64 bg-white shadow-md">
    <div class="p-5 border-b">
      <div class="flex items-center gap-3">
        <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
        <h2 class="text-lg font-semibold text-[var(--rose)]">SevenDwarfs</h2>
      </div>
      <div class="mt-4 flex items-center gap-3">
        <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
        <div>
          <h3 class="text-sm font-semibold"><?= htmlspecialchars($admin_name); ?></h3>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>

      <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
      </button>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
        <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a>
        <li><a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customer</a></li>
      </ul>

      <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
      </button>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
        <li><a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
      </ul>

      <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 active-link"><i class="fas fa-file-alt mr-2"></i>System Logs</a>
      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded transition"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a>
    </nav>
  </aside>

  <!-- 🌸 Main Content -->
  <main class="flex-1 p-8 bg-gray-50 overflow-auto">
    <div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm flex justify-between items-center">
      <h1 class="text-2xl font-semibold">System Logs</h1>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white p-4 rounded-b-2xl shadow-md flex flex-wrap items-center gap-4 mt-0">
      <div>
        <label class="text-sm font-medium text-gray-700">Role:</label>
        <select name="role" class="border p-2 rounded focus:ring-1 focus:ring-[var(--rose)]">
          <option value="">All</option>
          <option value="Admin" <?= $filter_role === 'Admin' ? 'selected' : '' ?>>Admin</option>
          <option value="Cashier" <?= $filter_role === 'Cashier' ? 'selected' : '' ?>>Cashier</option>
        </select>
      </div>

      <div>
        <label class="text-sm font-medium text-gray-700">Action:</label>
        <select name="action" class="border p-2 rounded focus:ring-1 focus:ring-[var(--rose)]">
          <option value="">All</option>
          <option value="Login" <?= $filter_action === 'Login' ? 'selected' : '' ?>>Login</option>
          <option value="Logout" <?= $filter_action === 'Logout' ? 'selected' : '' ?>>Logout</option>
        </select>
      </div>

      <div class="flex items-center gap-2 ml-auto">
        <label for="search" class="text-sm text-gray-700">Search:</label>
        <input id="search" name="search" type="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search user..."
          class="border p-2 rounded focus:ring-1 focus:ring-[var(--rose)]">
      </div>

      <button type="submit" class="bg-[var(--rose)] text-white px-4 py-2 rounded hover:bg-[var(--rose-hover)] transition">Filter</button>
    </form>

    <!-- Logs Table -->
    <div class="mt-6 bg-white shadow rounded-lg overflow-x-auto">
      <table class="min-w-full text-sm text-gray-700">
        <thead class="bg-gray-100 text-gray-600 uppercase text-xs font-semibold">
          <tr>
            <th class="px-4 py-3 text-left">#</th>
            <th class="px-4 py-3 text-left">User</th>
            <th class="px-4 py-3 text-left">Role</th>
            <th class="px-4 py-3 text-left">Action</th>
            <th class="px-4 py-3 text-left">Date & Time</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if ($logs->num_rows > 0): ?>
            <?php while ($log = $logs->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-3"><?= htmlspecialchars($log['log_id']); ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($log['full_name'] ?: $log['username'] ?: 'Unknown'); ?></td>
                <td class="px-4 py-3"><?= htmlspecialchars($log['role_id']); ?></td>
                <td class="px-4 py-3 font-semibold <?= $log['action'] === 'Login' ? 'text-green-600' : 'text-red-500'; ?>">
                  <?= htmlspecialchars($log['action']); ?>
                </td>
                <td class="px-4 py-3"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at']))); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center py-6 text-gray-500">No logs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
