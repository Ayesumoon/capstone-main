<?php
include 'conn.php';
include 'auth_session.php';

    $admin_id = $_SESSION['admin_id'] ?? null;
    $admin_name = "Admin";
    $admin_role = "Admin";

    if ($admin_id) {
        $query = "
            SELECT 
                CONCAT(first_name, ' ', last_name) AS full_name, 
                r.role_name 
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
    }

// === FILTER HANDLING ===
$filter = $_GET['filter'] ?? 'today';
$dateCondition = "DATE(created_at) = CURDATE()"; // default: today
if ($filter === 'month') {
    $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
}

// Metrics
$newOrders = $totalSales = $totalRevenue = 0;

// New orders based on filter
$ordersQuery = $conn->query("SELECT COUNT(*) AS new_orders FROM orders WHERE $dateCondition");
if ($row = $ordersQuery->fetch_assoc()) $newOrders = $row['new_orders'];

// Sales count (number of completed orders)
$salesQuery = $conn->query("SELECT COUNT(*) AS total_sales FROM orders WHERE $dateCondition");
if ($row = $salesQuery->fetch_assoc()) $totalSales = $row['total_sales'];

// Revenue (sum of order totals)
$revenueQuery = $conn->query("SELECT SUM(total_amount) AS revenue FROM orders WHERE $dateCondition");
if ($row = $revenueQuery->fetch_assoc()) $totalRevenue = $row['revenue'] ?? 0;

// Notifications
$newOrdersNotif = $lowStockNotif = 0;

// Orders in last 1 day
$newOrdersResult = $conn->query("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
if ($row = $newOrdersResult->fetch_assoc()) $newOrdersNotif = $row['count'];

// Low stock items
$lowStockResult = $conn->query("SELECT COUNT(*) as count FROM products WHERE stocks < 30");
if ($row = $lowStockResult->fetch_assoc()) $lowStockNotif = $row['count'];

$totalNotif = $newOrdersNotif + $lowStockNotif;

// Recent orders (same for all filters)
$recentOrders = $conn->query("
    SELECT o.order_id, o.total_amount, o.created_at, 
           COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Walk-in Customer') AS customer_name
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.customer_id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");


// Chart data
$chartQuery = $conn->query("
    SELECT DATE(created_at) AS order_date, COUNT(*) AS count
    FROM orders 
    WHERE $dateCondition
    GROUP BY DATE(created_at)
");

$chartLabels = $chartData = [];
while ($row = $chartQuery->fetch_assoc()) {
    $chartLabels[] = $row['order_date'];
    $chartData[] = $row['count'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --rose: #e59ca8; /* softer rose tone */
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

<body class="text-sm">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-md" x-data="{ userMenu: false, productMenu: false }">
    <div class="p-5 border-b">
      <div class="flex items-center space-x-3">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10" />
        <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
      </div>
    </div>

    <div class="p-5 border-b flex items-center space-x-3">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
      <div>
        <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 active flex items-center space-x-2 transition">
        <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
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
          <li><a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a></li>
          <li><a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customer</a></li>
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
          <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Product</a></li>
          <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
          <li><a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
            <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transitio"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>

      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-md transition"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col">
    <!-- Header -->
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl">
      <h1 class="text-xl font-semibold">Dashboard</h1>
      <div class="flex items-center gap-4">
        <button class="text-white text-lg"><i class="fas fa-envelope"></i></button>

        <!-- 🔔 Notification Bell -->
<div class="relative" x-data="{ open: false }" @click.outside="open = false">
  <button 
    class="relative text-white text-lg focus:outline-none transition hover:scale-110"
    @click="open = !open"
  >
    <i class="fas fa-bell"></i>
    <?php if ($totalNotif > 0): ?>
      <span class="absolute -top-1.5 -right-1.5 bg-red-600 text-white text-[10px] font-semibold rounded-full px-1.5 py-0.5 shadow">
        <?= $totalNotif ?>
      </span>
    <?php endif; ?>
  </button>

  <!-- Dropdown -->
  <div 
    x-show="open"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-2 scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
    x-transition:leave-end="opacity-0 translate-y-2 scale-95"
    class="absolute right-0 mt-3 w-72 bg-white rounded-xl shadow-lg ring-1 ring-gray-100 overflow-hidden z-50"
    style="display: none;"
  >
    <div class="p-3 border-b border-gray-100 flex justify-between items-center">
      <h3 class="text-gray-800 font-semibold text-sm">Notifications</h3>
      <button class="text-xs text-[var(--rose)] hover:underline" @click="open = false">Close</button>
    </div>
    
    <ul class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
      <?php if ($newOrdersNotif > 0): ?>
        <li class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50 transition">
          <span class="text-[var(--rose)] text-lg">🛒</span>
          <span class="text-sm text-gray-700"><?= $newOrdersNotif ?> new order<?= $newOrdersNotif > 1 ? 's' : '' ?></span>
        </li>
      <?php endif; ?>

      <?php if ($lowStockNotif > 0): ?>
        <li class="flex items-center gap-2 px-4 py-3 hover:bg-gray-50 transition">
          <span class="text-yellow-500 text-lg">📦</span>
          <span class="text-sm text-gray-700"><?= $lowStockNotif ?> low stock item<?= $lowStockNotif > 1 ? 's' : '' ?></span>
        </li>
      <?php endif; ?>

      <?php if ($totalNotif === 0): ?>
        <li class="px-4 py-3 text-center text-gray-500 text-sm">No notifications</li>
      <?php endif; ?>
    </ul>
  </div>
</div>

      </div>
    </header>

    <!-- Dashboard Stats -->
    <section class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="bg-purple-500 hover:bg-purple-600 text-white p-6 rounded-xl shadow-md text-center transition transform hover:scale-105">
        <h2 class="text-lg font-medium">New Orders</h2>
        <p class="text-3xl font-bold mt-2"><?= $newOrders ?></p>
      </div>

      <div class="bg-green-500 hover:bg-green-600 text-white p-6 rounded-xl shadow-md text-center transition transform hover:scale-105">
        <h2 class="text-lg font-medium">Sales</h2>
        <p class="text-3xl font-bold mt-2"><?= $totalSales ?></p>
      </div>

      <div class="bg-yellow-500 hover:bg-yellow-600 text-white p-6 rounded-xl shadow-md text-center transition transform hover:scale-105">
        <h2 class="text-lg font-medium">Revenue</h2>
        <p class="text-3xl font-bold mt-2">₱<?= number_format($totalRevenue, 2) ?></p>
      </div>
    </section>

    <!-- Filter -->
    <section class="px-6">
      <form method="GET" class="flex flex-wrap gap-2 items-center mb-4">
        <select name="filter" class="border p-2 rounded-md shadow-sm focus:ring-[var(--rose)] focus:outline-none">
          <option value="today" <?= ($_GET['filter'] ?? 'today') === 'today' ? 'selected' : '' ?>>Today</option>
          <option value="month" <?= ($_GET['filter'] ?? '') === 'month' ? 'selected' : '' ?>>This Month</option>
        </select>
        <button type="submit" class="bg-[var(--rose)] text-white px-4 py-2 rounded-md hover:bg-[var(--rose-hover)] transition">Filter</button>
      </form>
    </section>

    <!-- Recent Orders -->
    <section class="bg-white mx-6 p-6 rounded-xl shadow-md">
      <h2 class="text-lg font-semibold mb-4">Recent Orders</h2>
      <table class="w-full table-auto border-collapse text-sm">
        <thead class="bg-gray-100 text-gray-600">
          <tr>
            <th class="text-left px-4 py-2">Order ID</th>
            <th class="text-left px-4 py-2">Customer</th>
            <th class="text-left px-4 py-2">Amount</th>
            <th class="text-left px-4 py-2">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $recentOrders->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50 border-b">
              <td class="px-4 py-2"><?= $row['order_id'] ?></td>
              <td class="px-4 py-2"><?= $row['customer_name'] ?></td>
              <td class="px-4 py-2">₱<?= number_format($row['total_amount'], 2) ?></td>
              <td class="px-4 py-2"><?= $row['created_at'] ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </section>

    <!-- Chart -->
    <section class="mt-6 bg-white mx-6 p-6 rounded-xl shadow-md">
      <h2 class="text-lg font-semibold mb-4">Order Trends (Last 7 Days)</h2>
      <div class="w-full" style="height:300px;">
        <canvas id="ordersChart"></canvas>
      </div>
    </section>
  </main>
</div>

<script>
let chart;
const ctx = document.getElementById('ordersChart').getContext('2d');

function loadChart(filter = 'today') {
  fetch("chart_data.php?filter=" + filter)
    .then(res => res.json())
    .then(res => {
      if (chart) chart.destroy();
      chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: res.labels,
          datasets: [{
            label: 'Orders',
            data: res.data,
            borderColor: 'rgba(229,156,168,1)',
            backgroundColor: 'rgba(229,156,168,0.3)',
            fill: true,
            tension: 0.4
          }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    });
}
loadChart("<?= $_GET['filter'] ?? 'today' ?>");
document.querySelector("select[name='filter']").addEventListener("change", function(){
  loadChart(this.value);
});

// Notifications dropdown
function toggleNotifDropdown() {
  const dropdown = document.getElementById('notifDropdown');
  dropdown.classList.toggle('hidden');
}
window.addEventListener('click', function(e) {
  const notifBtn = document.querySelector('.fa-bell');
  const dropdown = document.getElementById('notifDropdown');
  if (!notifBtn.contains(e.target) && !dropdown.contains(e.target)) dropdown.classList.add('hidden');
});
</script>
</body>
</html>
