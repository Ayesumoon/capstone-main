<?php
session_start();
require 'conn.php'; // Make sure this connects to your database properly
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Payment & Transactions</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            poppins: ['Poppins', 'sans-serif'],
          },
          colors: {
            primary: '#ec4899', // pink-500
          }
        }
      }
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
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
          <h3 class="text-sm font-semibold"><?php echo htmlspecialchars($admin_name); ?></h3>
          <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
        </div>
      </div>
    </div>
  </div>
<!-- Navigation -->
  <nav class="mt-6">
    <ul>
      <!-- Dashboard -->
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="dashboard.php" class="flex items-center">
          <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
        </a>
      </li>

      <!-- User Management -->
      <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
        <div class="flex items-center justify-between">
          <span class="flex items-center">
            <i class="fas fa-users-cog mr-2"></i>User Management
          </span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </div>
      </li>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
        <li class="py-1 hover:text-pink-600"><a href="users.php" class="flex items-center"><i class="fas fa-user mr-2"></i>User</a></li>
        <li class="py-1 hover:text-pink-600"><a href="user_types.php" class="flex items-center"><i class="fas fa-id-badge mr-2"></i>Type</a></li>
        <li class="py-1 hover:text-pink-600"><a href="user_status.php" class="flex items-center"><i class="fas fa-toggle-on mr-2"></i>Status</a></li>
        <li class="py-1 hover:text-pink-600">
    <a href="customers.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-users"></i>
      <span>Customer</span>
    </a>
  </li>
      </ul>

      <!-- Product Management -->
      <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
        <div class="flex items-center justify-between">
          <span class="flex items-center">
            <i class="fas fa-box-open mr-2"></i>Product Management
          </span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </div>
      </li>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
        <li class="py-1 hover:text-pink-600"><a href="categories.php" class="flex items-center"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li class="py-1 hover:text-pink-600"><a href="products.php" class="flex items-center"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li class="py-1 hover:text-pink-600"><a href="inventory.php" class="flex items-center"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
       <li class="py-1 hover:text-pink-600"><a href="stock_management.php" class="flex items-center"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
      </ul>

      <!-- Other Pages -->
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="orders.php" class="flex items-center">
          <i class="fas fa-shopping-cart mr-2"></i>Orders
        </a>
      </li>
      </li>
        <li class="px-4 py-2 hover:bg-gray-200">
          <a href="suppliers.php" class="flex items-center">
            <i class="fas fa-industry mr-2"></i>Suppliers
          </a>
        </li>
      <li class="px-4 bg-pink-100 text-pink-600 rounded">
        <a href="payandtransac.php" class="flex items-center">
          <i class="fas fa-money-check-alt mr-2"></i>Payment & Transactions
        </a>
      </li>
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="storesettings.php" class="flex items-center">
          <i class="fas fa-cog mr-2"></i>Store Settings
        </a>
      </li>
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="logout.php" class="flex items-center">
          <i class="fas fa-sign-out-alt mr-2"></i>Log out
        </a>
      </li>
    </ul>
  </nav>
</div>

    <!-- Main Content -->
    <div class="flex-1 p-6 overflow-auto">
    <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
        <h1 class="text-xl font-bold">Payment & Transactions</h1>
      </div>

      <div class="bg-white p-6 rounded-b shadow-md">
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto border border-gray-200 shadow-md text-sm">
            <thead class="bg-gray-100 text-gray-600 text-left">
              <tr>
                <th class="px-4 py-3 border">Transaction ID</th>
                <th class="px-4 py-3 border">Order ID</th>
                <th class="px-4 py-3 border">Customer Name</th>
                <th class="px-4 py-3 border">Payment Method</th>
                <th class="px-4 py-3 border">Total</th>
                <th class="px-4 py-3 border">Payment Status</th>
                <th class="px-4 py-3 border">Date & Time</th>
                <th class="px-4 py-3 border">Actions</th>
              </tr>
            </thead>
            <tbody class="text-gray-700">
  <?php
  $sql = "
      SELECT 
          t.transaction_id,
          t.order_id,
          CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
          pm.payment_method_name,
          t.total,
          os.order_status_name,
          os.order_status_id,
          t.date_time
      FROM transactions t
      LEFT JOIN customers c ON t.customer_id = c.customer_id
      LEFT JOIN payment_methods pm ON t.payment_method_id = pm.payment_method_id
      LEFT JOIN order_status os ON t.order_status_id = os.order_status_id
      ORDER BY t.date_time DESC
  ";
  $result = $conn->query($sql);

  if ($result && $result->num_rows > 0) {
      while ($row = $result->fetch_assoc()) {
          // Fetch all order statuses for the dropdown
          $status_query = "SELECT * FROM order_status";
          $status_result = $conn->query($status_query);
          $status_options = '';
          while ($status = $status_result->fetch_assoc()) {
              $selected = ($row['order_status_id'] == $status['order_status_id']) ? 'selected' : '';
              $status_options .= "<option value='{$status['order_status_id']}' {$selected}>{$status['order_status_name']}</option>";
          }

          echo "<tr class='hover:bg-gray-50'>
              <td class='px-4 py-2 border'>{$row['transaction_id']}</td>
              <td class='px-4 py-2 border'>{$row['order_id']}</td>
              <td class='px-4 py-2 border'>{$row['customer_name']}</td>
              <td class='px-4 py-2 border'>{$row['payment_method_name']}</td>
              <td class='px-4 py-2 border'>$" . number_format($row['total'], 2) . "</td>
              <td class='px-4 py-2 border font-semibold text-blue-600'>
                  <form action='update_order_status.php' method='POST'>
                      <select name='order_status_id' class='border rounded px-2 py-1'>
                          {$status_options}
                      </select>
                      <input type='hidden' name='transaction_id' value='{$row['transaction_id']}'>
                      <button type='submit' class='bg-blue-500 text-white px-4 py-1 rounded'>Update</button>
                  </form>
              </td>
              <td class='px-4 py-2 border'>{$row['date_time']}</td>
              <td class='px-4 py-2 border'>
                  <a href='transaction_details.php?id={$row['transaction_id']}' class='text-blue-500 hover:underline'>View Details</a>
              </td>
          </tr>";
      }
  } else {
      echo "<tr><td colspan='8' class='text-center px-4 py-4 text-gray-500 border'>No transactions found.</td></tr>";
  }
  ?>
</tbody>

          </table>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
