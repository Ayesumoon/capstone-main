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

$categories = [];
$products = [];

// Fetch categories from the database
$sqlCategories = "SELECT category_id, category_name FROM categories";
$resultCategories = $conn->query($sqlCategories);

if ($resultCategories === false) {
    die("Error fetching categories: " . $conn->error);
}

if ($resultCategories->num_rows > 0) {
    while ($row = $resultCategories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get selected category from the dropdown
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Fetch products with category filtering
$sqlProducts = "
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
";

if ($selectedCategory !== 'all') {
    $sqlProducts .= " WHERE p.category_id = ?";
}

$productStmt = $conn->prepare($sqlProducts);

if ($selectedCategory !== 'all') {
    $productStmt->bind_param("i", $selectedCategory);
}

$productStmt->execute();
$resultProducts = $productStmt->get_result();

if ($resultProducts->num_rows > 0) {
    while ($row = $resultProducts->fetch_assoc()) {
        $products[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Products</title>
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
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 font-poppins text-sm transition-all duration-300">

 <body class="bg-gray-100 text-sm">
  <div class="flex h-screen">
   <!-- Sidebar -->
<div class="w-64 bg-white shadow-md min-h-screen" x-data="{ userMenu: false, productMenu: true }">
  <div class="p-4">
    <!-- Logo & Brand -->
    <div class="flex items-center space-x-4">
      <img src="logo2.png" alt="Logo" class="rounded-full w-12 h-12" />
      <h2 class="text-lg font-semibold">SevenDwarfs</h2>
    </div>

    <!-- Admin Info -->
    <div class="mt-4 flex items-center space-x-4">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
      <div>
        <h3 class="text-sm font-semibold"><?php echo htmlspecialchars($admin_name); ?></h3>
        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
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
        <li class="py-1">
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
        <li class="py-1 bg-pink-100 text-pink-600 rounded"><a href="products.php" class="flex items-center"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li class="py-1 hover:text-pink-600"><a href="inventory.php" class="flex items-center"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
      </ul>

      <!-- Other Pages -->
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="orders.php" class="flex items-center">
          <i class="fas fa-shopping-cart mr-2"></i>Orders
        </a>
      </li>

      <li class="px-4 py-2 hover:bg-gray-200 ">
          <a href="suppliers.php" class="flex items-center">
            <i class="fas fa-industry mr-2"></i>Suppliers
          </a>
        </li>

      <li class="px-4 py-2 hover:bg-gray-200">
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
<div class="flex-1 p-6 space-y-6 transition-all duration-300 font-poppins">
  <!-- Header -->
  <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
    <h1 class="text-2xl font-semibold">Products</h1>
  </div>

  <!-- Filters & Controls -->
<div class="bg-white p-6 rounded-b-2xl shadow-md space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    
    <!-- Category Dropdown -->
    <form method="GET" action="products.php" id="categoryForm" class="flex items-center space-x-2">
      <label class="text-gray-700 font-medium">Category:</label>
      <select name="category" onchange="document.getElementById('categoryForm').submit()" class="p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 transition">
        <option value="all">All</option>
        <?php foreach ($categories as $category) { ?>
          <option value="<?= $category['category_id']; ?>" <?= ($selectedCategory == $category['category_id']) ? 'selected' : ''; ?>>
            <?= htmlspecialchars($category['category_name']); ?>
          </option>
        <?php } ?>
      </select>
    </form>

    <!-- Add Product Button -->
    <a href="add_product.php"
      class="flex items-center gap-2 bg-pink-300 hover:bg-pink-500 text-white text-sm font-medium rounded-md px-4 py-2 transition"
    >
      <i class="fas fa-plus"></i> Add Product
    </a>

  </div>
</div>

    <!-- Product Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full bg-white border border-gray-200 shadow-md rounded-xl overflow-hidden">
        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left">Image</th>
            <th class="px-4 py-3 text-left">Code</th>
            <th class="px-4 py-3 text-left">Description</th>
            <th class="px-4 py-3 text-left">Product ID</th>
            <th class="px-4 py-3 text-left">Price</th>
            <th class="px-4 py-3 text-left">Category</th>
            <th class="px-4 py-3 text-left">Actions</th>
          </tr>
        </thead>
        <tbody class="text-gray-700 text-sm">
          <?php if (!empty($products)) { foreach ($products as $product) { ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-3">
                <img src="<?= htmlspecialchars($product['image_url']); ?>" alt="Product" class="w-12 h-12 object-cover rounded-full border shadow-sm" />
              </td>
              <td class="px-4 py-3"><?= htmlspecialchars($product['product_name']); ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($product['description']); ?></td>
              <td class="px-4 py-3"><?= $product['product_id']; ?></td>
              <td class="px-4 py-3">₱<?= number_format($product['price_id'], 2); ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($product['category_name']); ?></td>
              <td class="px-4 py-3">
                <div class="flex gap-2">
                  <a href="edit_product.php?id=<?= $product['product_id']; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg transition text-xs">Edit</a>
                  <a href="delete_product.php?id=<?= $product['product_id']; ?>" onclick="return confirm('Are you sure you want to delete this product?')" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg transition text-xs">Delete</a>
                </div>
              </td>
            </tr>
          <?php }} else { ?>
            <tr>
              <td colspan="9" class="text-center text-gray-500 py-6">No products available</td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Optional Animation -->
<style>
  @keyframes fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
  }

  .animate-fade-in {
    animation: fade-in 0.3s ease-out;
  }
</style>
