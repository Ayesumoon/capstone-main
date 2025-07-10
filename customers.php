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

    $sql .= " ORDER BY c.created_at DESC";

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customers</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            poppins: ['Poppins', 'sans-serif'],
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-100 font-poppins text-sm">
  <div class="flex h-screen overflow-hidden">

 <!-- Sidebar -->
<div class="w-64 bg-white shadow-lg min-h-screen" x-data="{ userMenu: false, productMenu: false }">
  <div class="p-4">
    <div class="flex items-center space-x-4">
      <img alt="Logo" class="rounded-full" height="50" src="logo2.png" width="50" />
      <h2 class="text-lg font-bold">SevenDwarfs</h2>
    </div>
    <div class="mt-4 flex items-center space-x-4">
      <img alt="Admin profile" class="rounded-full" height="40" src="newID.jpg" width="40" />
      <div>
        <h3 class="text-sm font-semibold"><?php echo htmlspecialchars($admin_name); ?></h3>
        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="mt-6">
    <ul>
      <li class="px-4 py-2 hover:bg-gray-100 transition"><a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
      <li class="px-4 py-2 hover:bg-gray-100 cursor-pointer transition" @click="userMenu = !userMenu">
        <div class="flex items-center justify-between">
          <span class="flex items-center"><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </div>
      </li>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1 overflow-hidden">
        <li class="py-1 hover:text-pink-600"><a href="users.php" class="flex items-center"><i class="fas fa-user mr-2"></i>User</a></li>
        <li class="py-1 hover:text-pink-600"><a href="user_types.php" class="flex items-center"><i class="fas fa-id-badge mr-2"></i>Type</a></li>
        <li class="py-1 hover:text-pink-600"><a href="user_status.php" class="flex items-center"><i class="fas fa-toggle-on mr-2"></i>Status</a></li>
        <li class="py-1 bg-pink-100 text-pink-600 rounded-md"><a href="customers.php" class="flex items-center space-x-2 hover:text-pink-600"><i class="fas fa-users"></i><span>Customer</span></a></li>
      </ul>

      <li class="px-4 py-2 hover:bg-gray-100 cursor-pointer transition" @click="productMenu = !productMenu">
        <div class="flex items-center justify-between">
          <span class="flex items-center"><i class="fas fa-box-open mr-2"></i>Product Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </div>
      </li>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1 overflow-hidden">
        <li class="py-1 hover:text-pink-600"><a href="categories.php" class="flex items-center"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li class="py-1 hover:text-pink-600"><a href="products.php" class="flex items-center"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li class="py-1 hover:text-pink-600"><a href="inventory.php" class="flex items-center"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
      </ul>

      <li class="px-4 py-2 hover:bg-gray-100 transition"><a href="orders.php" class="flex items-center"><i class="fas fa-shopping-cart mr-2"></i>Orders</a></li>
       <li class="px-4 py-2 hover:bg-gray-200"><a href="suppliers.php" class="flex items-center"><i class="fas fa-industry mr-2"></i>Suppliers</a></li>
      <li class="px-4 py-2 hover:bg-gray-100 transition"><a href="payandtransac.php" class="flex items-center"><i class="fas fa-money-check-alt mr-2"></i>Payment & Transactions</a></li>
      <li class="px-4 py-2 hover:bg-gray-100 transition"><a href="storesettings.php" class="flex items-center"><i class="fas fa-cog mr-2"></i>Store Settings</a></li>
      <li class="px-4 py-2 hover:bg-gray-100 transition"><a href="logout.php" class="flex items-center"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a></li>
    </ul>
  </nav>
</div>

<!-- Main Content -->
<div class="flex-1 p-6 overflow-auto">
  <div class="bg-pink-300 text-white p-5 rounded-t-xl shadow-md">
    <h1 class="text-2xl font-bold">Customers</h1>
  </div>
  <div class="bg-white p-6 rounded-b-xl shadow-md mb-6">
    <form method="GET" action="customers.php" class="flex justify-between items-center flex-wrap gap-2">
      <div>
        <label for="status" class="text-sm font-medium mr-2">Status:</label>
        <select name="status" id="status" onchange="this.form.submit()" class="border rounded-md p-2 text-sm">
  <option value="all" <?= (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'selected' : '' ?>>All</option>
  <option value="active" <?= (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : '' ?>>Active</option>
  <option value="inactive" <?= (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
</select>

      </div>
    </form>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full table-auto border border-gray-200 shadow text-sm rounded-lg overflow-hidden bg-white">
      <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
        <tr>
          <th class="px-4 py-3">Customer ID</th>
          <th class="px-4 py-3">Name</th>
          <th class="px-4 py-3">Email</th>
          <th class="px-4 py-3">Phone</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3">Registered</th>
          <th class="px-4 py-3">Actions</th>
        </tr>
      </thead>
      <tbody class="text-gray-700">
        <?php if (!empty($customers)) {
          foreach ($customers as $customer) { ?>
          <tr class="hover:bg-gray-50 transition">
            <td class="px-4 py-2 border"><?php echo $customer['customer_id']; ?></td>
            <td class="px-4 py-2 border"><?php echo htmlspecialchars($customer['name']); ?></td>
            <td class="px-4 py-2 border"><?php echo htmlspecialchars($customer['email']); ?></td>
            <td class="px-4 py-2 border"><?php echo htmlspecialchars($customer['phone']); ?></td>
            <td class="px-4 py-2 border font-semibold capitalize <?php echo strtolower($customer['status_name']) === 'active' ? 'text-green-600' : 'text-red-600'; ?>">
              <?php echo $customer['status_name']; ?>
            </td>
            <td class="px-4 py-2 border"><?php echo $customer['created_at']; ?></td>
            <td class="px-4 py-2 border">
              <a href="#" class="text-blue-500 hover:underline font-medium">View</a>
            </td>
          </tr>
        <?php }
        } else { ?>
          <tr>
            <td colspan="8" class="text-center px-4 py-6 text-gray-500">No customers found</td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
</div>
</div>
</body>
</html>
