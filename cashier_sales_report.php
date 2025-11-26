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

// 🔹 Fetch current logged-in admin info
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

// 🔹 Handle Filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$selected_cashier = $_GET['cashier_id'] ?? '';

// Build Query Conditions
$where_clauses = ["1=1"];
$params = [];
$types = "";

// Date Filter
if ($start_date && $end_date) {
    $where_clauses[] = "DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
    $types .= "ss";
    $date_label = "Sales from " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
} else {
    $date_label = "All Time Sales";
}

// Cashier Filter
if ($selected_cashier) {
    $where_clauses[] = "o.admin_id = ?";
    $params[] = $selected_cashier;
    $types .= "i";
} else {
    // Only show orders processed by admins/cashiers (admin_id IS NOT NULL)
    $where_clauses[] = "o.admin_id IS NOT NULL";
}

$where_sql = implode(" AND ", $where_clauses);

// 🔹 1. Fetch List of Cashiers for Dropdown
$cashier_sql = "SELECT admin_id, CONCAT(first_name, ' ', last_name) AS cashier_name FROM adminusers WHERE role_id = 1 ORDER BY first_name ASC";
$cashier_list = $conn->query($cashier_sql)->fetch_all(MYSQLI_ASSOC);

// 🔹 2. Calculate Total Sales for the Selected View
$total_sales_query = "SELECT COALESCE(SUM(total_amount), 0) as grand_total FROM orders o WHERE $where_sql";
$stmt_total = $conn->prepare($total_sales_query);
if (!empty($params)) {
    $stmt_total->bind_param($types, ...$params);
}
$stmt_total->execute();
$total_result = $stmt_total->get_result()->fetch_assoc();
$grand_total_sales = $total_result['grand_total'];
$stmt_total->close();

// 🔹 3. Fetch Detailed Transactions (Orders + Products + Status)
// We use GROUP_CONCAT to merge multiple items in one order row
$orders_sql = "
  SELECT
    o.order_id,
    o.created_at,
    o.total_amount,
    CONCAT(a.first_name, ' ', a.last_name) AS cashier_name,
    os.order_status_name,
    GROUP_CONCAT(
        CONCAT(p.product_name, ' (x', oi.qty, ')') 
        SEPARATOR '<br>'
    ) AS product_details
  FROM orders o
  LEFT JOIN adminusers a ON o.admin_id = a.admin_id
  LEFT JOIN order_status os ON o.order_status_id = os.order_status_id
  LEFT JOIN order_items oi ON o.order_id = oi.order_id
  LEFT JOIN products p ON oi.product_id = p.product_id
  WHERE $where_sql
  GROUP BY o.order_id
  ORDER BY o.created_at DESC
";

$stmt_orders = $conn->prepare($orders_sql);
if (!empty($params)) {
    $stmt_orders->bind_param($types, ...$params);
}
$stmt_orders->execute();
$orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_orders->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cashier Sales | Seven Dwarfs Boutique</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    [x-cloak] { display: none !important; }
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    .active-nav { background-color: #fce8eb; color: var(--rose); font-weight: 600; border-radius: 0.5rem; }
  </style>
</head>

<body class="text-sm">

<div class="flex min-h-screen" 
     x-data="{ sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, userMenu: false, productMenu: false }" 
     x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

  <!-- 🌸 Sidebar (Dynamic Width) -->
  <aside 
    class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out no-scrollbar overflow-y-auto overflow-x-hidden"
    :class="sidebarOpen ? 'w-64' : 'w-20'"
  >
    <!-- Logo -->
    <div class="p-5 border-b flex items-center h-20 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10 flex-shrink-0" />
        <h2 class="text-lg font-bold text-[var(--rose)] whitespace-nowrap overflow-hidden transition-all duration-300" 
            x-show="sidebarOpen" x-transition.opacity>SevenDwarfs</h2>
    </div>

    <!-- Admin Profile -->
    <div class="p-5 border-b flex items-center h-24 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10 flex-shrink-0" />
      <div x-show="sidebarOpen" x-transition.opacity class="whitespace-nowrap overflow-hidden">
        <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-tachometer-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
      </a>

      <!-- User Management Dropdown -->
      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-users-cog w-5 text-center text-lg"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap">User Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <ul x-show="userMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden transition-all" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li><a href="manage_users.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-user w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Users</span></a></li>
          <li><a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-user-tag w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Roles</span></a></li>
        </ul>
      </div>

      <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-industry w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Suppliers</span>
      </a>

      <!-- Product Management Dropdown -->
      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-box-open w-5 text-center text-lg"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap">Product Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <ul x-show="productMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li><a href="categories.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-tags w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Category</span></a></li>
          <li><a href="products.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-box w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Product</span></a></li>
          <li><a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Stock In</span></a></li>
          <li><a href="inventory.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Inventory</span></a></li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-shopping-cart w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Orders</span>
      </a>
      <a href="cashier_sales_report.php" class="block px-4 py-3 active-nav flex items-center transition-all duration-300" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-chart-line w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Cashier Sales</span>
      </a>
      <a href="system_logs.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-file-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">System Logs</span>
      </a>
      <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <!-- Header -->
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
            :class="sidebarOpen ? 'left-64' : 'left-20'">
      <div class="flex items-center gap-4">
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
             <i class="fas fa-bars text-xl"></i>
          </button>
          <div>
            <h1 class="text-xl font-semibold">Cashier Transactions</h1>
            <p class="text-xs text-white/80"><?= htmlspecialchars($date_label); ?></p>
          </div>
      </div>
    </header>

    <section class="p-6 space-y-6">

        <!-- 🔹 Top Section: Total Sales Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-gray-500 font-semibold uppercase text-xs tracking-wider">Total Sales</h2>
                <div class="text-3xl font-bold text-gray-800 mt-1">
                    ₱<?= number_format($grand_total_sales, 2); ?>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    <?php if($selected_cashier): ?>
                        For selected cashier
                    <?php else: ?>
                        Across all cashiers
                    <?php endif; ?>
                </p>
            </div>
            <div class="h-12 w-12 bg-green-100 rounded-full flex items-center justify-center text-green-600">
                <i class="fas fa-coins text-xl"></i>
            </div>
        </div>

        <!-- 🔹 Automated Filter Bar (Cashier Dropdown + Dates) -->
        <form method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-wrap items-end gap-4">
            
            <!-- Cashier Select -->
            <div class="flex flex-col gap-1">
                <label for="cashier_id" class="font-bold text-gray-700 text-xs uppercase">Select Cashier:</label>
                <div class="relative">
                    <select name="cashier_id" id="cashier_id" onchange="this.form.submit()"
                        class="pl-3 pr-8 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)] w-48 appearance-none">
                        <option value="">All Cashiers</option>
                        <?php foreach ($cashier_list as $c): ?>
                            <option value="<?= $c['admin_id']; ?>" <?= $selected_cashier == $c['admin_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['cashier_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-gray-500">
                        <i class="fas fa-chevron-down text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Start Date -->
            <div class="flex flex-col gap-1">
                <label for="start_date" class="font-bold text-gray-700 text-xs uppercase">Start Date:</label>
                <input type="date" name="start_date" id="start_date"
                  value="<?= htmlspecialchars($start_date); ?>"
                  onchange="this.form.submit()"
                  class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" />
            </div>

            <!-- End Date -->
            <div class="flex flex-col gap-1">
                <label for="end_date" class="font-bold text-gray-700 text-xs uppercase">End Date:</label>
                <input type="date" name="end_date" id="end_date"
                  value="<?= htmlspecialchars($end_date); ?>"
                  onchange="this.form.submit()"
                  class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" />
            </div>

            <!-- Reset Button -->
            <div class="pb-0.5">
                <a href="cashier_sales_report.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-semibold hover:bg-gray-300 transition text-sm flex items-center gap-2">
                    <i class="fas fa-undo"></i> Reset
                </a>
            </div>
            
            <!-- Hidden Submit for Enter Key support -->
            <button type="submit" class="hidden">Filter</button>
        </form>

        <!-- 🔹 Detailed Transaction Table -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
            <h2 class="text-lg font-bold mb-4 text-gray-700 flex items-center gap-2">
                <i class="fas fa-list text-[var(--rose)]"></i> Transaction History
            </h2>
            
            <div class="overflow-x-auto rounded-lg border border-gray-100">
              <table class="min-w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                  <tr>
                    <th class="px-6 py-3 text-left">Order ID</th>
                    <th class="px-6 py-3 text-left">Date & Time</th>
                    <th class="px-6 py-3 text-left">Cashier</th>
                    <th class="px-6 py-3 text-left w-1/3">Products Detail</th>
                    <th class="px-6 py-3 text-center">Status</th>
                    <th class="px-6 py-3 text-right">Total Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                  <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                      <tr class="hover:bg-gray-50 transition valign-top">
                        <td class="px-6 py-4 font-mono font-semibold text-gray-800">#<?= htmlspecialchars($order['order_id']); ?></td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900"><?= date('M d, Y', strtotime($order['created_at'])); ?></div>
                            <div class="text-xs text-gray-400"><?= date('h:i A', strtotime($order['created_at'])); ?></div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?= htmlspecialchars($order['cashier_name'] ?? 'Unknown'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs leading-relaxed text-gray-500">
                            <?= ($order['product_details']) ? $order['product_details'] : '<span class="italic text-gray-400">No items</span>'; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php 
                                $status = strtolower($order['order_status_name'] ?? ''); 
                                $statusClass = match($status) {
                                    'completed' => 'bg-green-100 text-green-700',
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                    'refunded' => 'bg-purple-100 text-purple-700',
                                    'shipped' => 'bg-blue-100 text-blue-700',
                                    default => 'bg-gray-100 text-gray-600'
                                };
                            ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold <?= $statusClass; ?>">
                                <?= htmlspecialchars($order['order_status_name'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right font-bold text-gray-800">
                            ₱<?= number_format($order['total_amount'], 2); ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="6" class="text-center py-10 text-gray-500 italic">No transactions found for the selected criteria.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
        </div>

    </section>

  </main>
</div>

</body>
</html>