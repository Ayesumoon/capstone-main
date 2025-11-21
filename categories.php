<?php
// Database connection
require 'conn.php';

session_start();

// Default admin values
$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details
if ($admin_id) {
    $query = "
        SELECT 
            CONCAT(first_name, ' ', last_name) AS full_name, 
            r.role_name 
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
    ";
    $adminStmt = $conn->prepare($query);
    $adminStmt->bind_param("i", $admin_id);
    $adminStmt->execute();
    $result = $adminStmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $adminStmt->close();
}

// Get search term
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

// 🔹 Fetch categories (Sorted Newest to Oldest)
if ($search !== "") {
    // Search Mode: Filter by name/code AND Sort DESC
    $query = "SELECT * FROM categories 
              WHERE category_name LIKE ? OR category_code LIKE ? 
              ORDER BY category_id DESC"; // Changed to DESC
    
    $stmt = $conn->prepare($query);
    $like = "%" . $search . "%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Default Mode: Sort Newest to Oldest
    $query = "SELECT * FROM categories ORDER BY category_id DESC"; // Changed to DESC
    $result = $conn->query($query);
}

// 🔹 Optional: Count total categories for a summary (useful for reports)
$total_categories = $result->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Categories | Seven Dwarfs Boutique</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --rose: #e59ca8;
      --rose-hover: #d27b8c;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
    }
    [x-cloak] { display: none !important; }
  </style>
</head>

<body class="text-sm text-gray-700">
<div class="flex h-screen"
     x-data="{ 
       showAddModal: false, 
       showEditModal: false, 
       showDeleteModal: false,
       userMenu: false, 
       productMenu: true,
       selectedCategory: { id: null, code: '', name: '' }
     }">

  <!-- 🌸 Sidebar -->
  <aside class="w-64 bg-white shadow-md min-h-screen" x-data="{ open: true }">
    <div class="p-4 border-b">
      <div class="flex items-center space-x-3">
        <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
        <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
      </div>
    </div>

    <div class="p-4 border-b flex items-center space-x-3">
      <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
      <div>
        <p class="font-semibold"><?= htmlspecialchars($admin_name) ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role) ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition">
        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
      </a>

      <!-- User Management -->
      <div>
        <button @click="userMenu = !userMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-gray-100 transition">
          <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>

        <ul x-show="userMenu" x-transition class="pl-8 space-y-1 mt-1 text-sm">
          <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Users</a></li>
          <li><a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Roles</a></li>
        </ul>
      </div>

      <!-- Product Management -->
      <div>
        <button @click="productMenu = !productMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-gray-100 transition">
          <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>

        <ul x-show="productMenu" x-transition class="pl-8 space-y-1 mt-1 text-sm">
          <li><a href="categories.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md"><i class="fas fa-tags mr-2"></i>Category</a></li>
          <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Products</a></li>
          <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
          <li><a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>
      <a href="suppliers.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-md transition"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
    </nav>
  </aside>

  <!-- 🌼 Main Content -->
  <main class="flex-1 p-8 overflow-auto">

    <!-- Header -->
    <header class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow">
      <h1 class="text-2xl font-semibold">Category Management</h1>
    </header>

    <section class="bg-white p-6 rounded-b-2xl shadow-md">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Category List</h2>
        <button @click="showAddModal = true" class="flex items-center gap-2 bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-md shadow transition">
          <i class="fas fa-plus"></i> Add Category
        </button>
      </div>

      <!-- Search Bar -->
      <form method="GET" class="flex items-center gap-2 mb-5">
        <label for="search" class="text-gray-600 font-medium">Search:</label>
        <input id="search" name="search" type="text" value="<?= htmlspecialchars($search) ?>" 
          class="border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--rose)] outline-none">
        <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-3 py-2 rounded-md transition">Search</button>
      </form>

      <!-- Category Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Category ID</th>
              <th class="px-4 py-3 text-left">Category Name</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-2"><?= htmlspecialchars($row['category_code']) ?></td>
                <td class="px-4 py-2"><?= htmlspecialchars($row['category_name']) ?></td>
                <td class="px-4 py-2 space-x-2">
                  <button 
    @click="showEditModal = true; selectedCategory = { 
        id: '<?= $row['category_id'] ?>', 
        code: '<?= $row['category_code'] ?>', 
        name: '<?= htmlspecialchars($row['category_name']) ?>' 
    }"
    class="text-blue-600 hover:text-blue-800 p-2"
    title="Edit"
>
    <i class="fas fa-edit"></i>
</button>

<button 
    @click="showDeleteModal = true; selectedCategory = { 
        id: '<?= $row['category_id'] ?>', 
        name: '<?= htmlspecialchars($row['category_name']) ?>' 
    }"
    class="text-red-600 hover:text-red-800 p-2"
    title="Delete"
>
    <i class="fas fa-trash-alt"></i>
</button>

                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Add Modal -->
    <div x-show="showAddModal" x-cloak x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div @click.away="showAddModal = false" class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-700">Add Category</h2>
        <form action="add_category.php" method="POST">
          <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
          <input type="text" name="category_name" required class="w-full border rounded-md p-2 mb-4 focus:ring-2 focus:ring-[var(--rose)]">
          <div class="flex justify-end space-x-3">
            <button type="button" @click="showAddModal = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-[var(--rose)] text-white rounded hover:bg-[var(--rose-hover)]">Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Modal -->
    <div x-show="showEditModal" x-cloak x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div @click.away="showEditModal = false" class="bg-white rounded-lg shadow-lg w-full max-w-md p-6">
        <h2 class="text-lg font-semibold mb-4 text-gray-700">Edit Category</h2>
        <form action="edit_category.php" method="POST">
          <input type="hidden" name="category_id" :value="selectedCategory.id">
          <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
          <input type="text" name="category_name" x-model="selectedCategory.name" required class="w-full border rounded-md p-2 mb-4 focus:ring-2 focus:ring-[var(--rose)]">
          <div class="flex justify-end space-x-3">
            <button type="button" @click="showEditModal = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-[var(--rose)] text-white rounded hover:bg-[var(--rose-hover)]">Update</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Modal -->
    <div x-show="showDeleteModal" x-cloak x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div @click.away="showDeleteModal = false" class="bg-white rounded-lg shadow-lg w-full max-w-sm p-6">
        <h2 class="text-lg font-semibold mb-3 text-gray-700">Delete Category</h2>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete <strong x-text="selectedCategory.name"></strong>?</p>
        <form action="delete_category.php" method="POST">
          <input type="hidden" name="category_id" :value="selectedCategory.id">
          <div class="flex justify-end space-x-3">
            <button type="button" @click="showDeleteModal = false" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>
</body>
</html>
