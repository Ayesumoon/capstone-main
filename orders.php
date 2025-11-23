<?php
session_start();
require 'conn.php';

// 🔹 Verify admin session
$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $query = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
              FROM adminusers a LEFT JOIN roles r ON a.role_id = r.role_id WHERE a.admin_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $stmt->close();
}

// 🔹 Fetch order statuses
$status_query = "SELECT order_status_id, order_status_name FROM order_status ORDER BY order_status_id ASC";
$status_result = $conn->query($status_query);
$status_options = '';
while ($status_row = $status_result->fetch_assoc()) {
    $selected = (($_GET['status'] ?? 'all') == $status_row['order_status_id']) ? "selected" : "";
    $status_options .= "<option value='{$status_row['order_status_id']}' $selected>{$status_row['order_status_name']}</option>";
}

// 🔹 Build filters
$where  = ["1=1"];
$params = [];
$types  = "";
$report_title = "All Orders Report";

// Status filter
if (isset($_GET['status']) && $_GET['status'] !== '' && $_GET['status'] !== 'all') {
    $status_id = (int) $_GET['status'];
    $where[] = "o.order_status_id = ?";
    $params[] = $status_id;
    $types .= "i";
}

// 🔹 Date range filter
$date_filter = $_GET['date_range'] ?? 'all';
switch ($date_filter) {
    case 'today':
        $where[] = "DATE(o.created_at) = CURDATE()";
        $report_title = "Daily Sales Report (" . date('M d, Y') . ")";
        break;
    case 'week':
        $where[] = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $report_title = "Weekly Sales Report (Week " . date('W, Y') . ")";
        break;
    case 'month':
        $where[] = "MONTH(o.created_at) = MONTH(CURDATE()) AND YEAR(o.created_at) = YEAR(CURDATE())";
        $report_title = "Monthly Sales Report (" . date('F Y') . ")";
        break;
    case 'year':
        $where[] = "YEAR(o.created_at) = YEAR(CURDATE())";
        $report_title = "Annual Sales Report (" . date('Y') . ")";
        break;
}

// 🔹 UPDATED MAIN QUERY
$sql = "
    SELECT 
    o.order_id, 
    o.total_amount,
    os.order_status_name AS order_status,
    pm.payment_method_name AS payment_method,
    o.created_at,

    
    GROUP_CONCAT(
        DISTINCT CONCAT(
            p.product_name, ' (', sz.size, ', ', c2.color, ') x', oi.qty
        ) ORDER BY oi.id SEPARATOR '<br>'
    ) AS purchased_items,

  
    GROUP_CONCAT(
        DISTINCT CONCAT(
            '[REFUND] ', p.product_name,
            ' (', sz.size, ', ', c2.color, ') x',
            (SELECT COUNT(*) FROM refunds r WHERE r.order_item_id = oi.id),
            ' — ₱',
            (SELECT SUM(refund_amount) FROM refunds r WHERE r.order_item_id = oi.id)
        ) ORDER BY oi.id SEPARATOR '<br>'
    ) AS refunded_items

FROM orders o
LEFT JOIN order_status os ON o.order_status_id = os.order_status_id
LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
LEFT JOIN stock s ON oi.stock_id = s.stock_id
LEFT JOIN products p ON s.product_id = p.product_id
LEFT JOIN sizes sz ON s.size_id = sz.size_id
LEFT JOIN colors c2 ON s.color_id = c2.color_id
LEFT JOIN refunds rf ON oi.id = rf.order_item_id
WHERE " . implode(" AND ", $where) . "
GROUP BY o.order_id
ORDER BY o.created_at DESC

";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// 🔹 Calculate summaries
$total_sales = 0;
$total_orders = count($orders);
$payment_counts = [];

foreach ($orders as $order) {
    $total_sales += $order['total_amount'];

    $pm = $order['payment_method'] ?? 'N/A';
    if (!isset($payment_counts[$pm])) $payment_counts[$pm] = 0;
    $payment_counts[$pm] += $order['total_amount'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Orders Report | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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

/* 🖨 PRINT STYLES */
@media print {
  @page { margin: 10mm; size: landscape; } /* Landscape for better table view */
  
  body, main { 
    background: white !important; 
    color: black !important; 
    margin: 0 !important; 
    padding: 0 !important; 
    width: 100% !important;
  }
  
  /* Hide UI elements */
  aside, .no-print, .filters, .print-btn { 
    display: none !important; 
  }

  /* Header Styling */
  .report-header {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid var(--rose);
    padding-bottom: 10px;
    margin-bottom: 20px;
  }
  
  /* Summary Box Styling for Print */
  .summary-box {
    display: flex !important;
    justify-content: space-between;
    background-color: #fdf2f4 !important; /* Light pink bg for print */
    border: 1px solid #eccace;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    -webkit-print-color-adjust: exact; /* Force background color */
  }
  
  .summary-item {
    text-align: center;
    flex: 1;
    border-right: 1px solid #eccace;
  }
  .summary-item:last-child { border-right: none; }

  /* Table Styling */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px; 
  }
  th, td {
    border: 1px solid #ddd;
    padding: 6px;
    text-align: left;
  }
  th {
    background-color: #f3f4f6 !important;
    -webkit-print-color-adjust: exact;
    font-weight: bold;
  }
}
</style>
</head>

<body class="text-sm" x-data="{ userMenu:false, productMenu:false }">
<div class="flex min-h-screen">

    <!-- 🧭 Sidebar -->
    <aside class="w-64 bg-white sidebar" x-data="{ userMenu: false, productMenu: true }">
      <div class="p-5 border-b">
        <div class="flex items-center space-x-3">
          <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10" />
          <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
        </div>
      </div>

      <div class="p-5 border-b flex items-center space-x-3">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
        <div>
          <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($admin_name); ?></p>
          <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="p-4 space-y-1">
        <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition">
          <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
        </a>

        <!-- User Management -->
        <div>
          <button @click="userMenu = !userMenu"
            class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
            <span><i class="fas fa-users-cog mr-2"></i> User Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': userMenu }"></i>
          </button>
          <div x-show="userMenu" x-transition class="pl-8 space-y-1 mt-1">
            <a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]">
              <i class="fas fa-user mr-2"></i>Users</a>
            <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]">
              <i class="fas fa-id-badge mr-2"></i>Roles</a>
          </div>
        </div>

        <!-- Product Management -->
        <div>
          <button @click="productMenu = !productMenu"
            class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
            <span><i class="fas fa-box-open mr-2"></i> Product Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': productMenu }"></i>
          </button>
          <div x-show="productMenu" x-transition class="pl-8 space-y-1 mt-1">
            <a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i> Category</a>
            <a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i> Product</a>
            <a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i> Inventory</a>
            <a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i> Stock Management</a>
          </div>
        </div>

        <a href="orders.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md">
          <i class="fas fa-shopping-cart mr-2"></i> Orders</a>
                <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transitio"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>

        <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition">
          <i class="fas fa-industry mr-2"></i> Suppliers</a>

      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

        <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-md transition">
          <i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
      </nav>
    </aside>

 <!-- Main Content -->
<main class="flex-1 p-8 bg-gray-50 space-y-6 overflow-auto w-full">

<!-- Top Bar -->
<div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm flex justify-between items-center no-print">
  <h1 class="text-2xl font-semibold">Order Management</h1>
  <button onclick="window.print()" class="print-btn bg-white text-[var(--rose)] px-4 py-2 rounded-md shadow hover:bg-gray-100 font-bold transition">
    <i class="fas fa-print mr-2"></i> Generate Printable Report
  </button>
</div>

<!-- Filters -->
<form method="GET" class="bg-white p-5 rounded-b-2xl shadow flex flex-wrap items-center gap-4 filters no-print">
  <div class="flex items-center gap-2">
    <label class="font-medium text-gray-700">Filter Status:</label>
    <select name="status" onchange="this.form.submit()" class="p-2 border rounded focus:ring-[var(--rose)]">
      <option value="all" <?= ($_GET['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All Statuses</option>
      <?= $status_options ?>
    </select>
  </div>

  <div class="flex items-center gap-2">
    <label class="font-medium text-gray-700">Time Period:</label>
    <select name="date_range" onchange="this.form.submit()" class="p-2 border rounded focus:ring-[var(--rose)]">
      <option value="all" <?= ($date_filter === 'all') ? 'selected' : '' ?>>All Time</option>
      <option value="today" <?= ($date_filter === 'today') ? 'selected' : '' ?>>Today</option>
      <option value="week" <?= ($date_filter === 'week') ? 'selected' : '' ?>>This Week</option>
      <option value="month" <?= ($date_filter === 'month') ? 'selected' : '' ?>>This Month</option>
      <option value="year" <?= ($date_filter === 'year') ? 'selected' : '' ?>>This Year</option>
    </select>
  </div>
</form>

<!-- Orders Report -->
<div class="bg-white p-8 rounded-2xl shadow-sm">

  <!-- Summary Dashboard -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 p-4 bg-pink-50 rounded-lg border border-pink-100">
    <div class="text-center md:text-left">
        <p class="text-gray-500 text-xs uppercase">Total Orders</p>
        <p class="text-2xl font-bold"><?= number_format($total_orders) ?></p>
    </div>
    <div class="text-center">
        <p class="text-gray-500 text-xs uppercase">Total Sales</p>
        <p class="text-2xl font-bold text-[var(--rose)]">₱<?= number_format($total_sales, 2) ?></p>
    </div>
    <div class="text-center md:text-right">
        <p class="text-gray-500 text-xs uppercase">Payment Breakdown</p>
        <div class="text-xs mt-1">
            <?php foreach($payment_counts as $method => $amount): ?>
                <div><?= htmlspecialchars($method) ?>: <span class="font-semibold">₱<?= number_format($amount, 2) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
  </div>

  <!-- Orders Table -->
  <div class="overflow-x-auto">
    <table class="min-w-full border border-gray-200 text-sm">
      <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
        <tr>
          <th class="px-4 py-3 text-left">Order #</th>
          <th class="px-4 py-3 text-left">Date</th>
          <th class="px-4 py-3 text-left w-1/3">Order Details</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-right">Total</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-100 text-gray-700">
      <?php if (!empty($orders)): ?>
        <?php foreach ($orders as $order): ?>
        <tr class="hover:bg-gray-50">

          <td class="px-4 py-2 font-mono font-semibold text-gray-500">
            #<?= $order['order_id']; ?>
          </td>

          <td class="px-4 py-2 whitespace-nowrap">
            <?= date('M d, Y', strtotime($order['created_at'])); ?>
          </td>

          <td class="px-4 py-2 text-xs leading-5">

    <!-- Always show purchased items -->
    <strong class="text-gray-800">Purchased:</strong><br>
    <?= $order['purchased_items'] ?: '<em>No items recorded</em>' ?>

    <!-- Only show refunded section if refunds exist -->
    <?php if (!empty($order['refunded_items'])): ?>
        <br><br>
        <strong class="text-red-600">Refunded:</strong><br>
        <span class="text-red-600"><?= $order['refunded_items'] ?></span>
    <?php endif; ?>

</td>


          <td class="px-4 py-2">
            <span class="px-2 py-1 rounded text-xs font-semibold bg-gray-100 border border-gray-300">
              <?= htmlspecialchars($order['order_status']); ?>
            </span>
          </td>

          <td class="px-4 py-2 text-right font-bold">
            ₱<?= number_format($order['total_amount'], 2); ?>
          </td>

        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
            <td colspan="5" class="text-center py-6 text-gray-500">
                No orders found for this period.
            </td>
        </tr>
      <?php endif; ?>
      </tbody>
          <!-- Table Footer for Print -->
          <tfoot class="hidden print:table-row-group bg-gray-50 font-bold">
            <tr>
                <td colspan="5" class="px-4 py-2 text-right">GRAND TOTAL:</td>
                <td class="px-4 py-2 text-right">₱<?= number_format($total_sales, 2); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
      
      <!-- Print Footer -->
      <div class="hidden print:block mt-8 pt-8 border-t border-gray-300 flex justify-between text-xs text-gray-500">
        <p>Printed from Seven Dwarfs Admin Panel</p>
        <p>Page 1 of 1</p>
      </div>

    </div>
  </main>
</div>
</body>
</html>