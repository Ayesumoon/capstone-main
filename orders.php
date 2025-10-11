
<?php
session_start();
require 'conn.php'; // Include database connection

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Get admin info
if ($admin_id) {
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
    $stmt->close();
}

// 🔹 Fetch order statuses for filter dropdown
$status_query = "SELECT order_status_id, order_status_name FROM order_status";
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

// Status filter
if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $where[]   = "o.order_status_id = ?";
    $params[]  = intval($_GET['status']);
    $types    .= "i";
}

// Date filter
if (!empty($_GET['date'])) {
    $where[]   = "DATE(o.created_at) = ?";
    $params[]  = $_GET['date'];
    $types    .= "s";
}

// 🔹 Main query: Fetch orders with grouped products
$sql = "
    SELECT 
        o.order_id,
        c.first_name,
        c.last_name,
        o.total_amount,
        os.order_status_name AS order_status,
        pm.payment_method_name AS payment_method,
        o.created_at,
        GROUP_CONCAT(DISTINCT p.product_name SEPARATOR ', ') AS products
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
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Orders | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; }
  </style>
</head>
<body class="bg-gray-100 text-sm">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <div class="w-64 bg-white shadow-lg" x-data="{ userMenu: false, productMenu: false }">
    <div class="p-4 border-b">
      <div class="flex items-center gap-3">
        <img src="logo2.png" alt="Logo" class="w-12 h-12 rounded-full">
        <h2 class="text-lg font-semibold text-pink-600">SevenDwarfs</h2>
      </div>
      <div class="mt-4 flex items-center gap-3">
        <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
        <div>
          <h3 class="text-sm font-medium"><?= htmlspecialchars($admin_name); ?></h3>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-4">
      <ul class="space-y-1">
        <li class="px-4 py-2 hover:bg-gray-200"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
        <!-- User Management -->
        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-users-cog mr-2"></i>User Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
          </div>
        </li>
        <ul x-show="userMenu" x-transition x-cloak class="pl-8 text-sm text-gray-700 space-y-1">
          <li><a href="manage_users.php" class="block py-1 hover:text-pink-600"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
          <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a>
          <li><a href="customers.php" class="block py-1 hover:text-pink-600"><i class="fas fa-users mr-2"></i>Customer</a></li>
        </ul>

        <!-- Product Management -->
        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-box-open mr-2"></i>Product Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
          </div>
        </li>
        <ul x-show="productMenu" x-transition x-cloak class="pl-8 text-sm text-gray-700 space-y-1">
          <li><a href="categories.php" class="block py-1 hover:text-pink-600"><i class="fas fa-tags mr-2"></i>Category</a></li>
          <li><a href="products.php" class="block py-1 hover:text-pink-600"><i class="fas fa-box mr-2"></i>Product</a></li>
          <li><a href="inventory.php" class="block py-1 hover:text-pink-600"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
          <li><a href="stock_management.php" class="block py-1 hover:text-pink-600"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
        </ul>

        <!-- Orders -->
        <li class="px-4 py-2 bg-pink-100 text-pink-600 rounded">
          <a href="orders.php" class="flex items-center"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
        </li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="suppliers.php" class="flex items-center"><i class="fas fa-industry mr-2"></i>Suppliers</a></li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="logout.php" class="flex items-center"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a></li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="flex-1 p-6 space-y-6">
    <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
      <h1 class="text-2xl font-semibold">Admin Orders</h1>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white p-4 rounded-b-2xl shadow-md flex items-center gap-4">
      <label for="status" class="text-sm font-medium text-gray-700">Status:</label>
      <select name="status" id="status" onchange="this.form.submit()" class="p-2 border rounded focus:ring-pink-500 focus:outline-none text-sm">
        <option value="all" <?= ($_GET['status'] ?? 'all') === 'all' ? 'selected' : '' ?>>All</option>
        <?= $status_options ?>
      </select>

      <label for="date" class="text-sm font-medium text-gray-700">Date:</label>
      <input type="date" name="date" id="date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" onchange="this.form.submit()" class="p-2 border rounded focus:ring-pink-500 focus:outline-none text-sm">
    </form>

    <!-- Orders Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full bg-white shadow rounded-lg text-sm table-auto">
        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left">Order ID</th>
            <th class="px-4 py-3 text-left">Customer</th>
            <th class="px-4 py-3 text-left">Products</th>
            <th class="px-4 py-3 text-left">Total</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Payment</th>
            <th class="px-4 py-3 text-left">Date</th>
          </tr>
        </thead>
        <tbody class="text-gray-700">
          <?php if (!empty($orders)): ?>
            <?php foreach ($orders as $order): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-2 border-b"><?= $order['order_id']; ?></td>
                <td class="px-4 py-2 border-b"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                <td class="px-4 py-2 border-b"><?= htmlspecialchars($order['products']); ?></td>
                <td class="px-4 py-2 border-b">₱<?= number_format($order['total_amount'], 2); ?></td>
                <td class="px-4 py-2 border-b"><?= htmlspecialchars($order['order_status']); ?></td>
                <td class="px-4 py-2 border-b"><?= htmlspecialchars($order['payment_method']); ?></td>
                <td class="px-4 py-2 border-b"><?= htmlspecialchars($order['created_at']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" class="text-center text-gray-500 py-6">No orders found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
