<?php
include 'conn.php';

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
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet"/>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
    }
  </style>
 </head>
 <body class="bg-gray-100 text-sm">
  <div class="flex h-screen">
   <!-- Sidebar -->
<div class="w-64 bg-white shadow-md min-h-screen" x-data="{ userMenu: false, productMenu: false }">
  <div class="p-4">
    <div class="flex items-center space-x-4">
      <img alt="Logo" class="rounded-full" height="50" src="logo2.png" width="50" />
      <div>
        <h2 class="text-lg font-semibold">SevenDwarfs</h2>
      </div>
    </div>
    <div class="mt-4">
      <div class="flex items-center space-x-4">
        <img alt="Admin profile" class="rounded-full" height="40" src="newID.jpg" width="40" />
        <div>
    <h3 class="text-sm font-semibold">
        <?= htmlspecialchars($username ?? 'Admin') ?>
    </h3>
    <p class="text-xs text-gray-500">
        <?= htmlspecialchars($role_name ?? 'Admin') ?>
    </p>
</div>

      </div>
    </div>
  </div>

  <nav class="mt-6">
    <ul class="space-y-1">
      <!-- Dashboard -->
      <li class="px-4 py-2 bg-pink-100 text-pink-600">
        <a href="dashboard.php" class="flex items-center space-x-2">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <!-- User Management -->
<li class="px-4 py-2 rounded-md hover:bg-pink-100 hover:text-pink-600 transition-all duration-200 cursor-pointer" @click="userMenu = !userMenu">
  <div class="flex items-center justify-between">
    <div class="flex items-center space-x-2">
      <i class="fas fa-users-cog"></i>
      <span>User Management</span>
    </div>
    <i class="fas fa-chevron-down" :class="{ 'rotate-180': userMenu }"></i>
  </div>
</li>
<ul x-show="userMenu" x-transition.duration.300ms class="pl-8 text-sm text-gray-700 space-y-1 overflow-hidden">
  <li class="py-1">
    <a href="users.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-user"></i>
      <span>User</span>
    </a>
  </li>
  <li class="py-1">
    <a href="user_types.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-id-badge"></i>
      <span>Type</span>
    </a>
  </li>
  <li class="py-1">
    <a href="user_status.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-toggle-on"></i>
      <span>Status</span>
    </a>
  </li>
  <li class="py-1">
    <a href="customers.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-users"></i>
      <span>Customer</span>
    </a>
  </li>
</ul>

<!-- Product Management -->
<li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
  <div class="flex items-center justify-between">
    <div class="flex items-center space-x-2">
      <i class="fas fa-box-open"></i>
      <span>Product Management</span>
    </div>
    <i class="fas fa-chevron-down" :class="{ 'rotate-180': productMenu }"></i>
  </div>
</li>
<ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
  <li class="py-1">
    <a href="categories.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-tags"></i>
      <span>Category</span>
    </a>
  </li>
  <li class="py-1">
    <a href="products.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-box"></i>
      <span>Product</span>
    </a>
  </li>
  <li class="py-1">
    <a href="inventory.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-warehouse"></i>
      <span>Inventory</span>
    </a>
  </li>
  <li class="py-1 hover:text-pink-600"><a href="stock_management.php" class="flex items-center"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
</ul>

      <!-- Other Links -->
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="orders.php" class="flex items-center space-x-2">
          <i class="fas fa-shopping-cart"></i>
          <span>Orders</span>
        </a>
      </li>
      </li>

        <li class="px-4 py-2 hover:bg-gray-200">
          <a href="suppliers.php" class="flex items-center">
            <i class="fas fa-industry mr-2"></i>Suppliers
          </a>
        </li>
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="payandtransac.php" class="flex items-center space-x-2">
          <i class="fas fa-money-check-alt"></i>
          <span>Payment & Transactions</span>
        </a>
      </li>

      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="storesettings.php" class="flex items-center space-x-2">
          <i class="fas fa-cog"></i>
          <span>Store Settings</span>
        </a>
      </li>

      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="logout.php" class="flex items-center space-x-2">
          <i class="fas fa-sign-out-alt"></i>
          <span>Log out</span>
        </a>
      </li>
    </ul>
  </nav>
</div>
   <!-- Main Content -->
   <div class="flex-1 flex flex-col">
    <!-- Header -->
     
<header class="bg-pink-300 p-4 flex items-center justify-between shadow-md rounded-bl-2xl">
     <div class="flex items-center space-x-4">
      <button class="text-white text-2xl">
      <h1 class="text-xl font-bold">Dashboard</h1>
       </i>
     </div>
     <div class="flex items-center space-x-4">
      <button class="text-white text-xl">
       <i class="fas fa-envelope">
       </i>
      </button>
      <div class="relative">
  <button class="text-white text-xl focus:outline-none" onclick="toggleNotifDropdown()">
    <i class="fas fa-bell"></i>
    <?php if ($totalNotif > 0): ?>
    <span class="absolute -top-2 -right-2 bg-red-600 text-white text-xs rounded-full px-1.5"><?= $totalNotif ?></span>
    <?php endif; ?>
  </button>

  <!-- Dropdown -->
<div id="notifDropdown" class="hidden absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg z-50 transition-all">
    <ul class="divide-y divide-gray-200">
      <?php if ($newOrdersNotif > 0): ?>
      <li class="px-4 py-2 hover:bg-gray-100 text-sm">🛒 <?= $newOrdersNotif ?> new order(s)</li>
      <?php endif; ?>
      <?php if ($lowStockNotif > 0): ?>
      <li class="px-4 py-2 hover:bg-gray-100 text-sm">📦 <?= $lowStockNotif ?> low stock item(s)</li>
      <?php endif; ?>
      <?php if ($totalNotif === 0): ?>
      <li class="px-4 py-2 text-gray-500 text-sm">No notifications</li>
      <?php endif; ?>
    </ul>
  </div>
</div>

     </div>
    </header>
   <!-- Dashboard Content -->
<div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">

  <div class="bg-purple-500 hover:bg-purple-600 transition duration-300 text-white p-6 rounded-xl shadow-lg text-center transform hover:scale-105">
    <h2 class="text-lg font-semibold">New Orders</h2>
    <p class="text-3xl font-bold mt-2"><?= $newOrders ?></p>
  </div>

  <div class="bg-green-500 hover:bg-green-600 transition duration-300 text-white p-6 rounded-xl shadow-lg text-center transform hover:scale-105">
    <h2 class="text-lg font-semibold">Sales</h2>
    <p class="text-3xl font-bold mt-2"><?= $totalSales ?></p>
  </div>

  <div class="bg-yellow-500 hover:bg-yellow-600 transition duration-300 text-white p-6 rounded-xl shadow-lg text-center transform hover:scale-105">
    <h2 class="text-lg font-semibold">Revenue</h2>
    <p class="text-3xl font-bold mt-2">₱<?= number_format($totalRevenue, 2) ?></p>
  </div>

</div>

<!-- Filter -->
<div class="mt-6">
  <form method="GET" class="flex flex-wrap gap-2 items-center mb-4">
    <select name="filter" class="border p-2 rounded-md shadow-sm focus:ring-pink-400 focus:outline-none">
      <option value="today" <?= ($_GET['filter'] ?? 'today') === 'today' ? 'selected' : '' ?>>Today</option>
<option value="month" <?= ($_GET['filter'] ?? '') === 'month' ? 'selected' : '' ?>>This Month</option>

    </select>
    <button type="submit" class="bg-pink-300 text-white px-4 py-2 rounded-md hover:bg-pink-500 transition">Filter</button>
  </form>
</div>


<!-- Recent Orders -->
<div class="bg-white p-4 rounded-md shadow-md">
    <h2 class="text-lg font-semibold mb-4">Recent Orders</h2>
    <table class="w-full table-auto border-collapse text-sm">
  <thead class="bg-gray-200">
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

</div>

<!-- Chart.js -->
<div class="mt-6 bg-white p-6 rounded-xl shadow-lg">
  <h2 class="text-lg font-semibold mb-4">Order Trends (Last 7 Days)</h2>
  <div class="w-full" style="height:300px;"> <!-- smaller height -->
    <canvas id="ordersChart"></canvas>
  </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<canvas id="ordersChart"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let chart;
const ctx = document.getElementById('ordersChart').getContext('2d');

function loadChart(filter = 'today') {
  fetch("chart_data.php?filter=" + filter)
    .then(res => res.json())
    .then(res => {
      if (chart) chart.destroy(); // reset old chart
      chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: res.labels,
    datasets: [{
      label: 'Orders',
      data: res.data,
      borderColor: 'rgba(255, 99, 132, 1)',
      backgroundColor: 'rgba(255, 99, 132, 0.2)',
      fill: true,
      tension: 0.4
    }]
  },
  options: { 
    responsive: true,
    maintainAspectRatio: false  // allows custom height
  }
});

    });
}

// Initial load
loadChart("<?= $_GET['filter'] ?? 'today' ?>");

// Update when filter form changes
document.querySelector("select[name='filter']").addEventListener("change", function(){
  loadChart(this.value);
});
</script>

<script>
  function toggleNotifDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    dropdown.classList.toggle('hidden');
  }

  // Optional: Close when clicking outside
  window.addEventListener('click', function(e) {
    const notifBtn = document.querySelector('.fa-bell');
    const dropdown = document.getElementById('notifDropdown');
    if (!notifBtn.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.classList.add('hidden');
    }
  });
</script>

 </body>
</html>