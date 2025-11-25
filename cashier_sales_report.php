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
  WHERE a.role_id = 1
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

    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    
    /* Sidebar specific */
    .active-nav { background-color: #fce8eb; color: var(--rose); font-weight: 600; border-radius: 0.5rem; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Animations */
    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeInSlide 0.4s ease-out; }
  </style>
</head>

<body class="text-sm animate-fade-in">

<!-- Global State Wrapper -->
<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: false
     }" 
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

      <!-- User Management -->
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

      <!-- Product Management -->
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
          <li><a href="inventory.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Inventory</span></a></li>
          <li><a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'"><i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i><span x-show="sidebarOpen">Stock In</span></a></li>
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
      <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-industry w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Suppliers</span>
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

  <!-- 🌸 Main Content (Dynamic Margin) -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <!-- Header (Fixed Top) -->
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
            :class="sidebarOpen ? 'left-64' : 'left-20'">
      
      <div class="flex items-center gap-4">
          <!-- Toggle Button -->
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
             <i class="fas fa-bars text-xl"></i>
          </button>
          <div>
            <h1 class="text-xl font-semibold">Cashier Sales Overview</h1>
            <p class="text-xs text-white/80"><?= htmlspecialchars($date_label); ?></p>
          </div>
      </div>
    </header>

    <!-- 📄 Page Content -->
    <section class="p-6 space-y-6">

        <!-- Filters -->
        <form method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
            <label for="date_range" class="font-bold text-gray-700 text-xs uppercase">View sales for:</label>
            <select name="date_range" id="date_range" onchange="this.form.submit()"
              class="px-3 py-2 border rounded-lg text-sm bg-gray-50 cursor-pointer focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
              <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
              <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This Week</option>
              <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>This Month</option>
              <option value="year" <?= $date_filter === 'year' ? 'selected' : '' ?>>This Year</option>
              <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
            </select>
        </form>

        <!-- Main Content Card -->
        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">

          <!-- Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-green-50 p-6 rounded-xl text-center shadow-sm border border-green-100 transition hover:scale-[1.02]">
              <p class="text-gray-500 text-xs uppercase font-bold tracking-wider">Total Orders</p>
              <h2 class="text-3xl font-bold text-green-700 mt-2"><?= $total_orders; ?></h2>
            </div>
            <div class="bg-blue-50 p-6 rounded-xl text-center shadow-sm border border-blue-100 transition hover:scale-[1.02]">
              <p class="text-gray-500 text-xs uppercase font-bold tracking-wider">Total Sales</p>
              <h2 class="text-3xl font-bold text-blue-700 mt-2">₱<?= number_format($total_sales, 2); ?></h2>
            </div>
          </div>

          <!-- Sales Table -->
          <div class="overflow-x-auto rounded-lg border border-gray-100">
            <table class="min-w-full text-left text-sm text-gray-600">
              <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                <tr>
                  <th class="px-6 py-3 text-left">Cashier</th>
                  <th class="px-6 py-3 text-left">Total Orders</th>
                  <th class="px-6 py-3 text-left">Total Sales</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-700">
                <?php if (!empty($cashiers)): ?>
                  <?php foreach ($cashiers as $cashier): ?>
                    <tr class="hover:bg-gray-50 transition">
                      <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($cashier['cashier_name']); ?></td>
                      <td class="px-6 py-4 font-mono text-gray-600"><?= $cashier['total_orders']; ?></td>
                      <td class="px-6 py-4 font-bold text-green-700">₱<?= number_format($cashier['total_sales'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="3" class="text-center py-8 text-gray-500">No sales found for this period.</td></tr>
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