<?php
session_start();
require 'conn.php';

// ----------------------------
// Orders Page (Admin) - Full UI (Option C)
// Includes revenue cards, best sellers, revenue trend, and printable report.
// ----------------------------

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// Get admin info
if ($admin_id) {
    $q = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name
          FROM adminusers a
          LEFT JOIN roles r ON a.role_id = r.role_id
          WHERE a.admin_id = ?";
    $stmt = $conn->prepare($q);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $stmt->close();
}

// Fetch order_status options
$status_query = "SELECT order_status_id, order_status_name FROM order_status ORDER BY order_status_id ASC";
$status_result = $conn->query($status_query);
$status_options = '';
while ($s = $status_result->fetch_assoc()) {
    $sel = (($_GET['status'] ?? 'all') == $s['order_status_id']) ? "selected" : "";
    $status_options .= "<option value='{$s['order_status_id']}' $sel>{$s['order_status_name']}</option>";
}

// Build filters
$where = ["1=1"];
$params = [];
$types = "";

// Status filter
if (isset($_GET['status']) && $_GET['status'] !== '' && $_GET['status'] !== 'all') {
    $status_id = (int) $_GET['status'];
    $where[] = "o.order_status_id = ?";
    $params[] = $status_id;
    $types .= "i";
}

// Date range
$date_filter = $_GET['date_range'] ?? 'all';
switch ($date_filter) {
    case 'today':
        $where[] = "DATE(o.created_at) = CURDATE()";
        break;
    case 'week':
        $where[] = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $where[] = "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
        break;
    case 'year':
        $where[] = "YEAR(o.created_at) = YEAR(CURDATE())";
        break;
    case 'custom':
        // optional: accept start & end params (not implemented here)
        break;
}

// Main orders query (group products)
$sql = "
    SELECT
      o.order_id,
      c.first_name,
      c.last_name,
      o.total_amount,
      os.order_status_name AS order_status,
      pm.payment_method_name AS payment_method,
      o.created_at,
      GROUP_CONCAT(DISTINCT p.product_name ORDER BY p.product_name SEPARATOR ', ') AS products
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN order_status os ON o.order_status_id = os.order_status_id
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN stock s ON oi.stock_id = s.stock_id
    LEFT JOIN products p ON s.product_id = p.product_id
    WHERE " . implode(" AND ", $where) . "
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
";
$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$orders = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Total revenue for the same filters
$rev_sql = "SELECT COALESCE(SUM(o.total_amount),0) AS total_revenue FROM orders o WHERE " . implode(" AND ", $where);
$rev_stmt = $conn->prepare($rev_sql);
if (!empty($params)) $rev_stmt->bind_param($types, ...$params);
$rev_stmt->execute();
$rev_res = $rev_stmt->get_result()->fetch_assoc();
$total_revenue = $rev_res['total_revenue'] ?? 0;
$rev_stmt->close();

// Revenue cards (Today/Week/Month/Year) - independent quick counts (screen + print)
$card_periods = [
    'today' => "DATE(o.created_at) = CURDATE()",
    'week'  => "YEARWEEK(o.created_at,1) = YEARWEEK(CURDATE(),1)",
    'month' => "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())",
    'year'  => "YEAR(o.created_at) = YEAR(CURDATE())",
];
$cards = [];
foreach ($card_periods as $k => $cond) {
    $csql = "SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_amount),0) AS revenue FROM orders o WHERE $cond";
    $c = $conn->query($csql)->fetch_assoc();
    $cards[$k] = $c;
}

// Best-selling products using the same filters as orders
$best_sql = "
    SELECT p.product_id, p.product_name, SUM(oi.qty) AS qty_sold, COALESCE(SUM(oi.qty * oi.price),0) AS revenue
    FROM order_items oi
    INNER JOIN orders o ON oi.order_id = o.order_id
    INNER JOIN stock s ON oi.stock_id = s.stock_id
    INNER JOIN products p ON s.product_id = p.product_id
    WHERE " . implode(" AND ", $where) . "
    GROUP BY p.product_id
    ORDER BY qty_sold DESC
    LIMIT 10
";
$best_stmt = $conn->prepare($best_sql);
if (!empty($params)) $best_stmt->bind_param($types, ...$params);
$best_stmt->execute();
$best_res = $best_stmt->get_result();
$best_sellers = $best_res->fetch_all(MYSQLI_ASSOC);
$best_stmt->close();

// Revenue trend (daily). If 'all' used, use last 30 days by default.
$trend_where = $where;
if ($date_filter === 'all') {
    $trend_where[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)";
}
$trend_sql = "
    SELECT DATE(o.created_at) AS d, COALESCE(SUM(o.total_amount),0) AS revenue
    FROM orders o
    WHERE " . implode(" AND ", $trend_where) . "
    GROUP BY DATE(o.created_at)
    ORDER BY DATE(o.created_at) ASC
";
$trend_stmt = $conn->prepare($trend_sql);
if (!empty($params)) $trend_stmt->bind_param($types, ...$params);
$trend_stmt->execute();
$trend_res = $trend_stmt->get_result();
$trend_rows = $trend_res->fetch_all(MYSQLI_ASSOC);
$trend_stmt->close();

// Build arrays for chart
$dates = [];
$revenues = [];
if ($date_filter === 'all') {
    $start = new DateTime(); $start->sub(new DateInterval('P29D'));
    $period = new DatePeriod($start, new DateInterval('P1D'), 30);
    $map = [];
    foreach ($trend_rows as $r) $map[$r['d']] = (float)$r['revenue'];
    foreach ($period as $dt) {
        $key = $dt->format('Y-m-d');
        $dates[] = $key;
        $revenues[] = isset($map[$key]) ? (float)$map[$key] : 0.0;
    }
} else {
    foreach ($trend_rows as $r) {
        $dates[] = $r['d'];
        $revenues[] = (float)$r['revenue'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Admin Orders | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--rose:#e5a5b2;--rose-hover:#d48b98}
body{font-family:'Poppins',sans-serif;background:#f8fafc;color:#374151}
.sidebar{width:264px;background:#fff;border-right:1px solid #eef2f6;position:fixed;left:0;top:0;height:100vh;padding:1rem;box-shadow:0 0 0 rgba(0,0,0,0)}
.main{margin-left:280px;padding:28px}
.header{background:linear-gradient(90deg,var(--rose) 0%, #fff 100%);padding:18px;border-radius:12px;display:flex;justify-content:space-between;align-items:center}
.card{background:#fff;border-radius:12px;padding:12px;border:1px solid #f3e8ea}
.grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
@media (max-width:1100px){.grid-4{grid-template-columns:repeat(2,1fr)}.main{margin-left:20px;padding:16px}.sidebar{display:none}}
.print-hide{display:block}
.print-only{display:none}
@media print{.print-hide{display:none !important}.print-only{display:block !important}.no-print{display:none !important}body{background:white}}
.table-sm th, .table-sm td{padding:10px;border-bottom:1px solid #f3f3f3}
</style>
</head>
<body>
<div class="flex min-h-screen">
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
          <a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Users</a>
          <a href="manage_roles.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md"><i class="fas fa-id-badge mr-2"></i>Roles</a>
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
  <!-- Main -->
  <main class="main">
    <!-- Header -->
    <div class="header mb-6">
      <div>
        <h1 class="text-2xl font-semibold">Orders</h1>
      </div>
      <div class="flex items-center gap-3">
        <button onclick="prepareReportAndPrint()" class="px-4 py-2 bg-white rounded shadow print-hide">Generate Report</button>
      </div>
    </div>

    <!-- Revenue cards (on-screen) -->
    <div class="grid-4 mb-6">
      <div class="card">
        <div class="text-xs text-gray-500">Today</div>
        <div class="text-xl font-bold text-[var(--rose)]">₱<?= number_format($cards['today']['revenue'] ?? 0, 2) ?></div>
        <div class="text-xs text-gray-500">Orders: <?= (int)($cards['today']['orders_count'] ?? 0) ?></div>
      </div>
      <div class="card">
        <div class="text-xs text-gray-500">This Week</div>
        <div class="text-xl font-bold text-[var(--rose)]">₱<?= number_format($cards['week']['revenue'] ?? 0, 2) ?></div>
        <div class="text-xs text-gray-500">Orders: <?= (int)($cards['week']['orders_count'] ?? 0) ?></div>
      </div>
      <div class="card">
        <div class="text-xs text-gray-500">This Month</div>
        <div class="text-xl font-bold text-[var(--rose)]">₱<?= number_format($cards['month']['revenue'] ?? 0, 2) ?></div>
        <div class="text-xs text-gray-500">Orders: <?= (int)($cards['month']['orders_count'] ?? 0) ?></div>
      </div>
      <div class="card">
        <div class="text-xs text-gray-500">This Year</div>
        <div class="text-xl font-bold text-[var(--rose)]">₱<?= number_format($cards['year']['revenue'] ?? 0, 2) ?></div>
        <div class="text-xs text-gray-500">Orders: <?= (int)($cards['year']['orders_count'] ?? 0) ?></div>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card mb-6 print-hide">
      <div class="flex gap-4 items-center">
        <div>
          <label class="block text-xs text-gray-600">Status</label>
          <select name="status" onchange="this.form.submit()" class="p-2 border rounded">
            <option value="all" <?= ($_GET['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
            <?= $status_options ?>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-600">Date</label>
          <select name="date_range" onchange="this.form.submit()" class="p-2 border rounded">
            <option value="all" <?= ($date_filter === 'all') ? 'selected' : '' ?>>All</option>
            <option value="today" <?= ($date_filter === 'today') ? 'selected' : '' ?>>Today</option>
            <option value="week" <?= ($date_filter === 'week') ? 'selected' : '' ?>>This Week</option>
            <option value="month" <?= ($date_filter === 'month') ? 'selected' : '' ?>>This Month</option>
            <option value="year" <?= ($date_filter === 'year') ? 'selected' : '' ?>>This Year</option>
          </select>
        </div>

        <div class="ml-auto">
          <div class="text-xs text-gray-500">Filtered Revenue</div>
          <div class="text-lg font-semibold text-[var(--rose)]">₱<?= number_format($total_revenue, 2) ?></div>
        </div>
      </div>
    </form>

    <!-- On-screen chart -->
    <div class="card mb-6">
      <h3 class="font-semibold mb-3">Revenue Trend</h3>
      <canvas id="screenChart" style="max-width:100%;height:240px;"></canvas>
    </div>

    <div class="grid-4 gap-6 mb-6">
      <!-- Best sellers -->
      <div class="card">
        <h3 class="font-semibold mb-3">Best-selling Products (Top 10)</h3>
        <div class="overflow-auto" style="max-height:320px">
          <table class="w-full table-sm text-sm">
            <thead>
              <tr>
                <th class="text-left">Product</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($best_sellers)): foreach ($best_sellers as $b): ?>
              <tr>
                <td><?= htmlspecialchars($b['product_name']) ?></td>
                <td class="text-right"><?= (int)$b['qty_sold'] ?></td>
                <td class="text-right">₱<?= number_format($b['revenue'], 2) ?></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="3" class="text-center text-gray-500 py-4">No sales for selected filters</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Orders table -->
      <div class="card col-span-3">
        <h3 class="font-semibold mb-3">Order List</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500">
              <tr>
                <th class="py-2 text-left">Order ID</th>
                <th class="py-2 text-left">Customer</th>
                <th class="py-2 text-left">Products</th>
                <th class="py-2 text-right">Total</th>
                <th class="py-2 text-left">Status</th>
                <th class="py-2 text-left">Payment</th>
                <th class="py-2 text-left">Date</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($orders)): foreach ($orders as $o): ?>
              <tr class="border-b hover:bg-gray-50">
                <td class="py-3"><?= $o['order_id'] ?></td>
                <td class="py-3"><?= htmlspecialchars($o['first_name'] . ' ' . $o['last_name']) ?></td>
                <td class="py-3"><?= htmlspecialchars($o['products']) ?></td>
                <td class="py-3 text-right font-semibold">₱<?= number_format($o['total_amount'],2) ?></td>
                <td class="py-3"><?= htmlspecialchars($o['order_status']) ?></td>
                <td class="py-3"><?= htmlspecialchars($o['payment_method']) ?></td>
                <td class="py-3"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($o['created_at']))) ?></td>
              </tr>
              <?php endforeach; else: ?>
              <tr><td colspan="7" class="text-center py-8 text-gray-500">No orders found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Print-only section: identical content but optimized for printing -->
    <div id="printArea" class="print-only">
      <div style="text-align:center;margin-bottom:12px">
        <img src="logo2.png" alt="Logo" style="width:64px;height:64px;margin:0 auto">
        <h2 style="margin-top:8px">Seven Dwarfs Boutique</h2>
        <p style="margin-top:4px;font-size:12px;color:#555">Report generated by <?= htmlspecialchars($admin_name) ?> — <?= date('F d, Y h:i A') ?></p>
        <p style="font-weight:700;margin-top:6px">Filtered Revenue: ₱<?= number_format($total_revenue,2) ?></p>
      </div>

      <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap">
        <div style="flex:1;min-width:150px;background:#fff;border-radius:8px;padding:8px;border:1px solid #eee;text-align:center">
          <div style="font-size:12px;color:#666">Today</div>
          <div style="font-weight:700">₱<?= number_format($cards['today']['revenue'] ?? 0,2) ?></div>
          <div style="font-size:12px;color:#666">Orders: <?= $cards['today']['orders_count'] ?? 0 ?></div>
        </div>
        <div style="flex:1;min-width:150px;background:#fff;border-radius:8px;padding:8px;border:1px solid #eee;text-align:center">
          <div style="font-size:12px;color:#666">This Week</div>
          <div style="font-weight:700">₱<?= number_format($cards['week']['revenue'] ?? 0,2) ?></div>
          <div style="font-size:12px;color:#666">Orders: <?= $cards['week']['orders_count'] ?? 0 ?></div>
        </div>
        <div style="flex:1;min-width:150px;background:#fff;border-radius:8px;padding:8px;border:1px solid #eee;text-align:center">
          <div style="font-size:12px;color:#666">This Month</div>
          <div style="font-weight:700">₱<?= number_format($cards['month']['revenue'] ?? 0,2) ?></div>
          <div style="font-size:12px;color:#666">Orders: <?= $cards['month']['orders_count'] ?? 0 ?></div>
        </div>
        <div style="flex:1;min-width:150px;background:#fff;border-radius:8px;padding:8px;border:1px solid #eee;text-align:center">
          <div style="font-size:12px;color:#666">This Year</div>
          <div style="font-weight:700">₱<?= number_format($cards['year']['revenue'] ?? 0,2) ?></div>
          <div style="font-size:12px;color:#666">Orders: <?= $cards['year']['orders_count'] ?? 0 ?></div>
        </div>
      </div>

      <div style="margin-bottom:12px">
        <h3 style="margin-bottom:6px">Revenue Trend</h3>
        <canvas id="printChart" width="900" height="300" style="max-width:100%"></canvas>
      </div>

      <div style="margin-bottom:12px">
        <h3 style="margin-bottom:6px">Best-selling Products (Top 10)</h3>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead><tr><th style="text-align:left;border-bottom:1px solid #ddd;padding:6px">Product</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:6px">Qty</th><th style="text-align:right;border-bottom:1px solid #ddd;padding:6px">Revenue</th></tr></thead>
          <tbody>
            <?php if(!empty($best_sellers)): foreach($best_sellers as $p): ?>
            <tr>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3"><?= htmlspecialchars($p['product_name']) ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3;text-align:right"><?= (int)$p['qty_sold'] ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3;text-align:right">₱<?= number_format($p['revenue'],2) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3" style="padding:12px;text-align:center;color:#666">No sales for the selected filters</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div>
        <h3 style="margin-bottom:8px">Orders</h3>
        <table style="width:100%;border-collapse:collapse;font-size:12px">
          <thead>
            <tr>
              <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd">Order ID</th>
              <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd">Customer</th>
              <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd">Products</th>
              <th style="text-align:right;padding:6px;border-bottom:1px solid #ddd">Total</th>
              <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd">Status</th>
              <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd">Payment</th>
              <th style="text-align:left;padding:6px;border-bottom:1px solid #ddd">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!empty($orders)): foreach($orders as $o): ?>
            <tr>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3"><?= $o['order_id'] ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3"><?= htmlspecialchars($o['first_name'].' '. $o['last_name']) ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3"><?= htmlspecialchars($o['products']) ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3;text-align:right">₱<?= number_format($o['total_amount'],2) ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3"><?= htmlspecialchars($o['order_status']) ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3"><?= htmlspecialchars($o['payment_method']) ?></td>
              <td style="padding:6px;border-bottom:1px solid #f3f3f3"><?= htmlspecialchars(date('M d, Y h:i A', strtotime($o['created_at'])) ) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" style="padding:12px;text-align:center;color:#666">No orders found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>

  </main>
</div>

<script>
// Chart data
const labels = <?= json_encode($dates) ?>;
const data = <?= json_encode($revenues) ?>;

// render chart on screen
function renderScreenChart(){
  const ctx = document.getElementById('screenChart').getContext('2d');
  if (window._screenChart) window._screenChart.destroy();
  window._screenChart = new Chart(ctx, {
    type: 'line',
    data: { labels: labels, datasets: [{ label: 'Revenue', data: data, fill: true, tension:0.2, borderColor:'#d48b98', backgroundColor:'rgba(212,139,152,0.12)', pointRadius:2 }] },
    options: { scales: { x: { display:true }, y: { beginAtZero:true } }, plugins:{ legend:{ display:false } } }
  });
}
function renderPrintChart(){
  const canvas = document.getElementById('printChart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  if (canvas._chart) canvas._chart.destroy();
  canvas._chart = new Chart(ctx, {
    type: 'line',
    data: { labels: labels, datasets: [{ label:'Revenue', data:data, fill:true, tension:0.2, borderColor:'#d48b98', backgroundColor:'rgba(212,139,152,0.12)', pointRadius:2 }] },
    options: { scales:{ x:{ display:true }, y:{ beginAtZero:true } }, plugins:{ legend:{ display:false } } }
  });
}

// Prepare report and print
function prepareReportAndPrint(){
  renderPrintChart();
  // small delay to allow chart render
  setTimeout(()=> window.print(), 600);
}

// initial screen chart
renderScreenChart();
</script>
</body>
</html>
