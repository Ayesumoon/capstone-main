<?php
session_start();
require 'conn.php';

// Verify admin session
if (!isset($_SESSION["admin_id"])) {
  header("Location: login.php");
  exit;
}

$admin_id   = $_SESSION['admin_id'];
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin info
$query = "
  SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, r.role_name 
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
$stmt->close();

// 🔹 Date filter
$date_filter = $_GET['date_range'] ?? 'today';

switch ($date_filter) {
  case 'today':
    $date_condition = "DATE(o.created_at) = CURDATE()";
    $date_label = "Today's Sales";
    break;
  case 'week':
    $date_condition = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $date_label = "This Week's Sales";
    break;
  case 'month':
    $date_condition = "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
    $date_label = "This Month's Sales";
    break;
  case 'year':
    $date_condition = "YEAR(o.created_at) = YEAR(CURDATE())";
    $date_label = "This Year's Sales";
    break;
  default:
    $date_condition = "1=1";
    $date_label = "All Time Sales";
    break;
}

// 🔹 Fetch each cashier’s total sales
$sql = "
  SELECT 
      a.admin_id,
      CONCAT(a.first_name, ' ', a.last_name) AS cashier_name,
      COUNT(o.order_id) AS total_orders,
      COALESCE(SUM(o.total_amount), 0) AS total_sales
  FROM adminusers a
  LEFT JOIN orders o ON a.admin_id = o.admin_id AND $date_condition
  WHERE a.role_id = 0
  GROUP BY a.admin_id
  ORDER BY total_sales DESC
";
$result = $conn->query($sql);
$cashiers = $result->fetch_all(MYSQLI_ASSOC);

// 🔹 Summary totals
$total_orders = 0;
$total_sales = 0;
foreach ($cashiers as $c) {
  $total_orders += $c['total_orders'];
  $total_sales += $c['total_sales'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cashier Sales | Seven Dwarfs Boutique</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --rose: #e5a5b2;
      --rose-hover: #d48b98;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
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

<body class="text-sm text-gray-700" x-data="{ userMenu:false, productMenu:false }">
<div class="flex min-h-screen">

  <!-- 🌸 Sidebar -->
  <aside class="w-64 bg-white shadow-md min-h-screen" x-data="{ open: true }">
    <div class="p-4 border-b">
      <div class="flex items-center space-x-3">
        <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
        <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
      </div>
    </div>

    <div class="p-4 border-b flex items-center space-x-3">
      <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
      <div>
        <p class="font-semibold"><?= htmlspecialchars($admin_name) ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role) ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition">
        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
      </a>

      <!-- User Management -->
      <div>
        <button @click="userMenu = !userMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-gray-100 transition">
          <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>

        <ul x-show="userMenu" x-transition class="pl-8 space-y-1 mt-1 text-sm">
          <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
          <li><a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a></li>
          <li><a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customers</a></li>
        </ul>
      </div>

      <!-- Product Management -->
      <div>
        <button @click="productMenu = !productMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-gray-100 transition">
          <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>

        <ul x-show="productMenu" x-transition class="pl-8 space-y-1 mt-1 text-sm">
          <li><a href="categories.php" class="block py-1 hover:text-[var(--rose)"><i class="fas fa-tags mr-2"></i>Category</a></li>
          <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Products</a></li>
          <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
          <li><a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="cashier_sales_report.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>
      <a href="suppliers.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-md transition"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
    </nav>
  </aside>

  <!-- 🌼 Main Content -->
  <main class="flex-1 p-8 overflow-auto">

    <!-- Header -->
    <header class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow">
      <h1 class="text-2xl font-semibold">Cashier Sales Overview</h1>
      <p class="text-sm opacity-90"><?= htmlspecialchars($date_label); ?></p>
    </header>

    <section class="bg-white p-6 rounded-b-2xl shadow-md space-y-6">
      <!-- Filters -->
      <form method="GET" class="flex flex-wrap items-center gap-3">
        <label for="date_range" class="font-medium text-gray-700">View sales for:</label>
        <select name="date_range" id="date_range" onchange="this.form.submit()"
          class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--rose)] outline-none">
          <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
          <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This Week</option>
          <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>This Month</option>
          <option value="year" <?= $date_filter === 'year' ? 'selected' : '' ?>>This Year</option>
          <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
        </select>
      </form>

      <!-- Summary Cards -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-green-50 p-4 rounded-xl text-center shadow-sm">
          <p class="text-gray-500 text-xs uppercase">Total Orders</p>
          <h2 class="text-2xl font-bold text-green-700"><?= $total_orders; ?></h2>
        </div>
        <div class="bg-blue-50 p-4 rounded-xl text-center shadow-sm">
          <p class="text-gray-500 text-xs uppercase">Total Sales</p>
          <h2 class="text-2xl font-bold text-blue-700">₱<?= number_format($total_sales, 2); ?></h2>
        </div>
      </div>

      <!-- Sales Table -->
      <div class="overflow-x-auto mt-4">
        <table class="min-w-full border border-gray-200 rounded-lg text-sm">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Cashier</th>
              <th class="px-4 py-3 text-left">Total Orders</th>
              <th class="px-4 py-3 text-left">Total Sales</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <?php if (!empty($cashiers)): ?>
              <?php foreach ($cashiers as $cashier): ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="px-4 py-3 font-medium"><?= htmlspecialchars($cashier['cashier_name']); ?></td>
                  <td class="px-4 py-3"><?= $cashier['total_orders']; ?></td>
                  <td class="px-4 py-3 font-semibold text-green-700">₱<?= number_format($cashier['total_sales'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" class="text-center py-6 text-gray-500">No sales found for this period</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
