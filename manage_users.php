<?php
session_start();
require 'conn.php';

// 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// 🔹 Fetch current admin details (Including role_id for permissions)
$stmt = $conn->prepare("
    SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, 
           r.role_name, a.role_id 
    FROM adminusers a 
    LEFT JOIN roles r ON a.role_id = r.role_id
    WHERE a.admin_id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? "Admin";
$admin_role = $admin['role_name'] ?? "Administrator";
$admin_role_id = $admin['role_id'] ?? 0; // Needed for the delete button check

// 🔎 Search Logic
$search = $_GET['search'] ?? '';
$search_query = "";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $search_query = " AND (u.username LIKE '%$s%' OR u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.admin_email LIKE '%$s%') ";
}

// 🧩 Fetch users with Search Filter
$query = "
    SELECT u.admin_id, u.username, u.admin_email, 
           CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
           r.role_name, s.status_id, s.status_name
    FROM adminusers u
    LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN status s ON u.status_id = s.status_id
    WHERE 1=1 $search_query
    ORDER BY u.admin_id ASC
";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | Seven Dwarfs</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root { --rose: #e898a8; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; }
    
    /* Exact colors from your screenshot reference */
    .bg-theme-pink { background-color: #e898a8; }
    .bg-theme-pink-hover { background-color: #d68294; }
    .text-theme-pink { color: #e898a8; }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #e898a8; border-radius: 3px; }
</style>
</head>

<body class="flex h-screen text-sm text-gray-700">

<!-- 🌸 Sidebar -->
  <!-- Fixed: Added userMenu and productMenu to x-data so dropdowns work -->
  <aside class="w-64 bg-white shadow-md min-h-screen flex flex-col z-20 shrink-0" 
         x-data="{ userMenu: true, productMenu: false }">
    
    <div class="p-4 border-b">
      <div class="flex items-center space-x-3">
        <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
        <h2 class="text-lg font-bold text-theme-pink">SevenDwarfs</h2>
      </div>
    </div>

    <div class="p-4 border-b flex items-center space-x-3">
      <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full border">
      <div>
        <p class="font-semibold"><?= htmlspecialchars($admin_name) ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role) ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition">
        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
      </a>

      <!-- User Management -->
      <div>
        <button @click="userMenu = !userMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-pink-50 hover:text-theme-pink transition">
          <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>

        <div x-show="userMenu" x-collapse class="pl-8 mt-1 space-y-1">
          <a href="manage_users.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md"><i class="fas fa-user mr-2"></i>Users</a>
          <a href="manage_roles.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-id-badge mr-2"></i>Roles</a>
        </div>
      </div>

      <!-- Product Management -->
      <div>
        <button @click="productMenu = !productMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-pink-50 hover:text-theme-pink transition">
          <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>

        <div x-show="productMenu" x-collapse class="pl-8 mt-1 space-y-1">
          <a href="categories.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-tags mr-2"></i>Category</a>
          <a href="products.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-box mr-2"></i>Products</a>
          <a href="inventory.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-warehouse mr-2"></i>Inventory</a>
          <a href="stock_management.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-boxes mr-2"></i>Stocks</a>
        </div>
      </div>

      <a href="orders.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>
      <a href="suppliers.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-pink-50 hover:text-theme-pink rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

      <a href="logout.php" class="block px-4 py-2 text-red-500 hover:bg-red-50 rounded-md transition mt-4"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
    </nav>
  </aside>

<!-- 🌸 Main Content Area -->
<main class="flex-1 p-8 overflow-y-auto h-screen bg-white">
    
    <!-- 🎴 The Card Container -->
    <div class="w-full bg-white rounded-lg shadow-sm border border-gray-100 flex flex-col h-auto min-h-[600px]">
        
        <!-- 🟥 Card Header (Rounded Top) -->
        <div class="bg-theme-pink px-6 py-4 rounded-t-lg">
            <h1 class="text-xl font-bold text-white tracking-wide">Manage Users</h1>
        </div>

        <!-- 📄 Card Body -->
        <div class="p-6">

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="mb-6 p-3 bg-red-100 text-red-700 rounded-md border border-red-200">
                    <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-6 p-3 bg-green-100 text-green-700 rounded-md border border-green-200">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- 1️⃣ Title & Add Button Row -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0">User List</h2>
                
                <a href="add_user.php" class="bg-theme-pink hover:bg-theme-pink-hover text-white px-5 py-2 rounded shadow-sm transition flex items-center gap-2 font-medium">
                    <i class="fas fa-plus text-xs"></i> Add User
                </a>
            </div>

            <!-- 2️⃣ Search Row (Added back to match Category Management) -->
            <div class="mb-6">
                <form method="GET" action="" class="flex items-center gap-3">
                    <label for="search" class="font-bold text-gray-700 text-sm">Search:</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search); ?>" 
                           class="border border-gray-300 rounded px-3 py-1.5 w-64 focus:outline-none focus:border-pink-400"
                           placeholder="">
                    <button type="submit" class="bg-theme-pink hover:bg-theme-pink-hover text-white px-5 py-1.5 rounded transition shadow-sm">
                        Search
                    </button>
                </form>
            </div>

            <!-- 3️⃣ Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm border-collapse">
                    <thead class="bg-gray-100 text-gray-600 uppercase text-xs font-bold border-t border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left tracking-wider">Username</th>
                            <th class="px-4 py-3 text-left tracking-wider">Name</th>
                            <th class="px-4 py-3 text-left tracking-wider">Email</th>
                            <th class="px-4 py-3 text-left tracking-wider">Role</th>
                            <th class="px-4 py-3 text-left tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left tracking-wider w-32">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-pink-50/20 transition duration-150">
                                    <td class="px-4 py-4 text-gray-700 font-medium"><?= htmlspecialchars($user['username']); ?></td>
                                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars($user['full_name']); ?></td>
                                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars($user['admin_email']); ?></td>
                                    <td class="px-4 py-4 text-gray-600"><?= htmlspecialchars($user['role_name']); ?></td>
                                    <td class="px-4 py-4">
                                        <?php if ($user['status_id'] == 1): ?>
                                            <span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-semibold">Active</span>
                                        <?php else: ?>
                                            <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-semibold">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 flex gap-3">
                                        <!-- Edit -->
                                        <a href="edit_user.php?id=<?= $user['admin_id']; ?>" 
                                           class="bg-blue-500 text-white p-1.5 rounded hover:bg-blue-600 transition w-7 h-7 flex items-center justify-center">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>

                                        <?php if ($admin_role_id == 2 && $user['admin_id'] != $admin_id): ?>
                                            <!-- Delete -->
                                            <a href="delete_user.php?id=<?= $user['admin_id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this user?')" 
                                               class="bg-red-500 text-white p-1.5 rounded hover:bg-red-600 transition w-7 h-7 flex items-center justify-center">
                                                <i class="fas fa-trash text-xs"></i>
                                            </a>
                                            <!-- Toggle Status -->
                                            <a href="toggle_user.php?id=<?= $user['admin_id']; ?>&status=<?= $user['status_id']==1?2:1; ?>" 
                                               class="bg-yellow-500 text-white p-1.5 rounded hover:bg-yellow-600 transition w-7 h-7 flex items-center justify-center"
                                               title="Toggle Status">
                                                <i class="fas <?= $user['status_id']==1 ? 'fa-user-slash' : 'fa-user-check'; ?> text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    No users found matching your search.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- End Table Div -->

        </div> 
        <!-- End Card Body -->
    </div>
    <!-- End Card Container -->
</main>

</body>
</html>