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

// Fetch categories with optional search
if ($search !== "") {
    $query = "SELECT * FROM categories WHERE category_name LIKE ? OR category_code LIKE ?";
    $stmt = $conn->prepare($query);
    $like = "%" . $search . "%";
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT * FROM categories";
    $result = $conn->query($query);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Category</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Poppins', sans-serif; }
    [x-cloak] { display: none !important; }
  </style>
</head>

<body class="bg-gray-100 font-poppins text-sm transition-all duration-300">

<div class="flex h-screen"
     x-data="{ 
       showAddModal: false, 
       showEditModal: false, 
       showDeleteModal: false,
       userMenu: false, 
       productMenu: true,
       selectedCategory: { id: null, code: '', name: '' }
     }"
     @keydown.escape.window="showAddModal = true; showEditModal = true; showDeleteModal = true">

  <!-- Sidebar -->
  <div class="w-64 bg-white shadow-md min-h-screen">
    <div class="p-4">
      <div class="flex items-center space-x-4">
        <img src="logo2.png" alt="Logo" class="rounded-full w-12 h-12" />
        <h2 class="text-lg font-semibold">SevenDwarfs</h2>
      </div>

      <div class="mt-4 flex items-center space-x-4">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
        <div>
          <h3 class="text-sm font-semibold"><?= htmlspecialchars($admin_name) ?></h3>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role) ?></p>
        </div>
      </div>
    </div>

    <nav class="mt-6">
      <ul>
        <li class="px-4 py-2 hover:bg-gray-200">
          <a href="dashboard.php" class="flex items-center"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
        </li>

        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-users-cog mr-2"></i>User Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
          </div>
        </li>
        <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li class="py-1 hover:text-pink-600"><a href="users.php" class="flex items-center"><i class="fas fa-user mr-2"></i>User</a></li>
          <li class="py-1 hover:text-pink-600"><a href="customers.php" class="flex items-center"><i class="fas fa-users mr-2"></i>Customer</a></li>
        </ul>

        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-box-open mr-2"></i>Product Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
          </div>
        </li>
        <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li class="py-1 bg-pink-100 text-pink-600 rounded"><a href="categories.php" class="flex items-center"><i class="fas fa-tags mr-2"></i>Category</a></li>
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

<li class="px-4 py-2 hover:bg-gray-200">
  <a href="suppliers.php" class="flex items-center">
    <i class="fas fa-industry mr-2"></i>Suppliers
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
  <div class="flex-1 p-6 space-y-6 transition-all duration-300 font-poppins">

    <!-- Header -->
    <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
      <h1 class="text-2xl font-semibold">Category</h1>
    </div>

    <div class="w-full bg-white p-6 rounded-b-2xl shadow">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-extrabold text-gray-800">Category List</h2>
        <button
          @click="showAddModal = true"
          class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md px-4 py-2"
          type="button"
        >
          <i class="fas fa-plus"></i> Add Categories
        </button>
      </div>

      <!-- Search -->
      <div class="bg-white rounded-md shadow-sm p-4 overflow-x-auto">
        <form method="GET" class="flex items-center gap-2 text-sm text-gray-700 mb-4">
          <label for="search" class="whitespace-nowrap">Search:</label>
          <input
            id="search"
            name="search"
            type="text"
            value="<?= htmlspecialchars($search) ?>"
            class="border border-gray-300 rounded-md text-gray-700 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 px-2 py-1"
          />
          <button
            type="submit"
            class="ml-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1 rounded text-sm"
          >
            Search
          </button>
        </form>

        <!-- Category Table -->
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category ID</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Category Name</th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($row['category_code']) ?></td>
                <td class="px-4 py-2 text-sm text-gray-700"><?= htmlspecialchars($row['category_name']) ?></td>
                <td class="px-4 py-2 text-sm">
                    <button 
                      @click="showEditModal = true; selectedCategory = { id: '<?= $row['category_id'] ?>', code: '<?= $row['category_code'] ?>', name: '<?= htmlspecialchars($row['category_name']) ?>' }" 
                      class="text-blue-600 hover:underline mr-3">
                      <i class="fas fa-edit"></i> Edit
                    </button>

                    <button 
                      @click="showDeleteModal = true; selectedCategory = { id: '<?= $row['category_id'] ?>', code: '<?= $row['category_code'] ?>', name: '<?= htmlspecialchars($row['category_name']) ?>' }" 
                      class="text-red-600 hover:underline">
                      <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Category Modal -->
    <div
      x-show="showAddModal"
      x-cloak
      x-transition
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      >
      <div
        @click.away="showAddModal = false"
        class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 space-y-4"
      >
        <h2 class="text-lg font-semibold text-gray-700">Add New Category</h2>

        <form action="add_category.php" method="POST">
          <div class="mb-4">
            <label for="category_name" class="block text-sm font-medium text-gray-700">Category Name</label>
            <input
              type="text"
              name="category_name"
              id="category_name"
              required
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>

          <div class="flex justify-end gap-2">
            <button
              type="button"
              @click="showAddModal = false"
              class="px-4 py-2 text-sm text-gray-600 bg-gray-200 rounded hover:bg-gray-300"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="px-4 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700"
            >
              Save
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Category Modal -->
<div x-show="showEditModal" x-cloak x-transition 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div @click.away="showEditModal = false"
       class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 space-y-4">

    <h2 class="text-lg font-semibold text-gray-700">Edit Category</h2>

    <form action="edit_category.php" method="POST">
      <input type="hidden" name="category_id" :value="selectedCategory.id">

      <div class="mb-4">
        <label for="edit_category_name" class="block text-sm font-medium text-gray-700">Category Name</label>
        <input type="text" name="category_name" id="edit_category_name"
               x-model="selectedCategory.name"
               required
               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
      </div>

      <div class="flex justify-end gap-2">
        <button type="button" @click="showEditModal = false"
                class="px-4 py-2 text-sm text-gray-600 bg-gray-200 rounded hover:bg-gray-300">
          Cancel
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm text-white bg-indigo-600 rounded hover:bg-indigo-700">
          Update
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Category Modal -->
<div x-show="showDeleteModal" x-cloak x-transition 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div @click.away="showDeleteModal = false"
       class="bg-white rounded-lg shadow-lg w-full max-w-sm p-6 space-y-4">

    <h2 class="text-lg font-semibold text-gray-700">Delete Category</h2>
    <p class="text-sm text-gray-600">
      Are you sure you want to delete <strong x-text="selectedCategory.name"></strong>?
    </p>

    <form action="delete_category.php" method="POST">
      <input type="hidden" name="category_id" :value="selectedCategory.id">

      <div class="flex justify-end gap-2 mt-4">
        <button type="button" @click="showDeleteModal = false"
                class="px-4 py-2 text-sm text-gray-600 bg-gray-200 rounded hover:bg-gray-300">
          Cancel
        </button>
        <button type="submit"
                class="px-4 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700">
          Delete
        </button>
      </div>
    </form>
  </div>
</div>


  </div>
</div>
</body>
</html>
