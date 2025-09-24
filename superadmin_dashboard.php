<?php
session_start();
require 'conn.php'; // <-- your DB connection file

// Check if logged in and is Super Admin (Owner = role_id = 1)
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$checkRole = $conn->prepare("SELECT role_id FROM adminusers WHERE admin_id = ?");
$checkRole->bind_param("i", $admin_id);
$checkRole->execute();
$roleRes = $checkRole->get_result()->fetch_assoc();
if (!$roleRes || $roleRes['role_id'] != 1) {
    // Not super admin
    header("Location: users.php");
    exit();
}

// Fetch counts
$totalUsers = $conn->query("SELECT COUNT(*) AS c FROM adminusers")->fetch_assoc()['c'];
$totalRoles = $conn->query("SELECT COUNT(*) AS c FROM roles")->fetch_assoc()['c'];
$totalLogs  = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c']; // using orders as logs for demo
$totalOrders = $conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];

// Fetch total sales (only completed transactions, order_status_id = 0 means Completed)
$totalSales = $conn->query("SELECT COALESCE(SUM(total), 0) AS s FROM transactions WHERE order_status_id = 0")->fetch_assoc()['s'];

// Sales by week and month (for pie chart)
// Orders breakdown by week
$ordersWeek = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(order_id) AS total 
    FROM orders 
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
    GROUP BY d
")->fetch_all(MYSQLI_ASSOC);

// Orders breakdown by month
$ordersMonth = $conn->query("
    SELECT DATE(created_at) AS d, COUNT(order_id) AS total 
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
    GROUP BY d
")->fetch_all(MYSQLI_ASSOC);

// Sales breakdown by week
$salesWeek = $conn->query("
    SELECT DATE(created_at) AS d, SUM(total_amount) AS total 
    FROM orders 
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) AND order_status_id = 0
    GROUP BY d
")->fetch_all(MYSQLI_ASSOC);

// Sales breakdown by month
$salesMonth = $conn->query("
    SELECT DATE(created_at) AS d, SUM(total_amount) AS total 
    FROM orders 
    WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND order_status_id = 0
    GROUP BY d
")->fetch_all(MYSQLI_ASSOC);

$salesWeekJson = json_encode($salesWeek);
$salesMonthJson = json_encode($salesMonth);

$ordersWeekJson = json_encode($ordersWeek);
$ordersMonthJson = json_encode($ordersMonth);

// Get Super Admin name
$admin = $conn->query("SELECT first_name, last_name FROM adminusers WHERE admin_id = $admin_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Dashboard</title>
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
    <!-- Top Header -->
    <header class="flex justify-between items-center mb-8">
      <div class="flex items-center space-x-4">
        <!-- Hamburger button -->
        <button id="toggleSidebar" class="text-pink-600 text-2xl focus:outline-none">
          ☰
        </button>
        <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
      </div>
      <div class="flex items-center space-x-4">
        <span class="text-gray-700">Welcome, <?php echo htmlspecialchars($admin['first_name'] . " " . $admin['last_name']); ?></span>
        <img src="https://i.pravatar.cc/40" alt="profile" class="rounded-full w-10 h-10 border-2 border-pink-500">
      </div>
    </header>

    <!-- Stats Section -->
    <section class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">Total Users</h2>
        <p class="text-3xl font-bold text-gray-800"><?php echo $totalUsers; ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">Active Roles</h2>
        <p class="text-3xl font-bold text-gray-800"><?php echo $totalRoles; ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">System Logs</h2>
        <p class="text-3xl font-bold text-gray-800"><?php echo $totalLogs; ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">Total Orders</h2>
        <p class="text-3xl font-bold text-gray-800"><?php echo $totalOrders; ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h2 class="text-gray-500 text-sm font-medium">Total Sales</h2>
        <p class="text-3xl font-bold text-gray-800">₱<?php echo number_format($totalSales, 2); ?></p>
      </div>
    </section>

   <section class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
  <!-- Sales Pie Chart -->
  <div class="bg-white p-6 rounded-xl shadow">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-gray-700">📊 Sales Breakdown</h2>
      <select id="salesFilter" class="border border-gray-300 rounded-lg p-2">
        <option value="week">This Week</option>
        <option value="month">This Month</option>
      </select>
    </div>
    <div class="flex justify-center">
      <canvas id="salesChart" width="250" height="250"></canvas>
    </div>
  </div>

  <!-- Orders Pie Chart -->
  <div class="bg-white p-6 rounded-xl shadow">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-gray-700">🛒 Orders Breakdown</h2>
      <select id="ordersFilter" class="border border-gray-300 rounded-lg p-2">
        <option value="week">This Week</option>
        <option value="month">This Month</option>
      </select>
    </div>
    <div class="flex justify-center">
      <canvas id="ordersChart" width="250" height="250"></canvas>
    </div>
  </div>
</section>


  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const salesWeek = <?php echo $salesWeekJson; ?>;
  const salesMonth = <?php echo $salesMonthJson; ?>;
  const ordersWeek = <?php echo $ordersWeekJson; ?>;
  const ordersMonth = <?php echo $ordersMonthJson; ?>;

  function prepareData(data, label) {
    return {
      labels: data.map(d => d.d),
      datasets: [{
        label: label,
        data: data.map(d => d.total),
        backgroundColor: [
          "#abd2e9ff", "#67afd8ff", "#3c91c2ff", "#1e6c99ff", "#0a4669ff"
        ]
      }]
    };
  }

  // Sales Chart
  const salesCtx = document.getElementById('salesChart').getContext('2d');
  let salesChart = new Chart(salesCtx, {
    type: 'pie',
    data: prepareData(salesWeek, "Sales"),
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  document.getElementById("salesFilter").addEventListener("change", function() {
    const selected = this.value === "week" ? salesWeek : salesMonth;
    salesChart.data = prepareData(selected, "Sales");
    salesChart.update();
  });

  // Orders Chart
  const ordersCtx = document.getElementById('ordersChart').getContext('2d');
  let ordersChart = new Chart(ordersCtx, {
    type: 'pie',
    data: prepareData(ordersWeek, "Orders"),
    options: {
      responsive: true,
      plugins: { legend: { position: 'bottom' } }
    }
  });

  document.getElementById("ordersFilter").addEventListener("change", function() {
    const selected = this.value === "week" ? ordersWeek : ordersMonth;
    ordersChart.data = prepareData(selected, "Orders");
    ordersChart.update();
  });

  // Sidebar toggle
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
