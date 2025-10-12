<?php
    session_start();
    require 'conn.php'; // Database connection

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

    $customers = [];

    // Get selected status filter from GET request
    $status_filter = $_GET['status'] ?? 'all';

    // Prepare SQL with filtering
    $sql = "
        SELECT c.customer_id, 
               CONCAT(c.first_name, ' ', c.last_name) AS name, 
               c.email, 
               c.phone, 
               s.status_name, 
               c.created_at 
        FROM customers c
        INNER JOIN status s ON c.status_id = s.status_id
    ";

    if ($status_filter === 'active') {
        $sql .= " WHERE LOWER(s.status_name) = 'active'";
    } elseif ($status_filter === 'inactive') {
        $sql .= " WHERE LOWER(s.status_name) = 'inactive'";
    }

    $sql .= " ORDER BY c.created_at ASC";

    $result = $conn->query($sql);

    if ($result === false) {
        die("Error in SQL query: " . $conn->error);
    }

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
    }

    $conn->close();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customers | Seven Dwarfs Boutique</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --rose: #e59ca8;
      --rose-hover: #d27b8c;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
      color: #374151;
    }
  </style>
</head>

<body class="font-poppins text-sm bg-gray-100">
  <div class="flex min-h-screen">

    <!-- 🌸 Sidebar -->
    <aside class="w-64 bg-white shadow-md" x-data="{ userMenu: true, productMenu: false }">
      <div class="p-4 border-b">
        <div class="flex items-center space-x-3">
          <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
          <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
        </div>
      </div>

      <div class="p-4 border-b flex items-center space-x-3">
        <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
        <div>
          <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 p-4 space-y-1">
        <a href="dashboard.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition">
          <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
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
            <li><a href="customers.php" class="block py-1 text-[var(--rose)] font-semibold bg-pink-50 rounded-md"><i class="fas fa-users mr-2"></i>Customers</a></li>
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
            <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Products</a></li>
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

    <!-- 🌼 Main Content -->
    <main class="flex-1 p-8 overflow-auto">
      <header class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Customers</h1>
  </header>

      <div class="bg-white p-6 rounded-b-2xl shadow-md mb-6">
        <form method="GET" action="customers.php" class="flex flex-wrap justify-between items-center gap-3">
          <div>
            <label for="status" class="text-sm font-medium mr-2 text-gray-700">Status:</label>
            <select name="status" id="status" onchange="this.form.submit()" 
              class="border border-gray-300 rounded-lg p-2 text-sm focus:ring-2 focus:ring-[var(--rose)] bg-white">
              <option value="all" <?= (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'selected' : '' ?>>All</option>
              <option value="active" <?= (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
        </form>
      </div>

      <!-- 🧾 Customer Table -->
      <div class="overflow-x-auto bg-white rounded-xl shadow">
        <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-left">Phone</th>
              <th class="px-4 py-3 text-left">Status</th>
              <th class="px-4 py-3 text-left">Registered</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 text-gray-700">
            <?php if (!empty($customers)): ?>
              <?php foreach ($customers as $customer): ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="px-4 py-2"><?= htmlspecialchars($customer['name']); ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($customer['email']); ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($customer['phone']); ?></td>
                  <td class="px-4 py-2 font-medium <?= strtolower($customer['status_name']) === 'active' ? 'text-green-600' : 'text-red-600'; ?>">
                    <?= htmlspecialchars($customer['status_name']); ?>
                  </td>
                  <td class="px-4 py-2"><?= htmlspecialchars($customer['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="text-center py-6 text-gray-500">No customers found</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>
