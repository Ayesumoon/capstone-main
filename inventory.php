<?php
session_start();
require 'conn.php'; 

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details
if ($admin_id) {
    $query = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
              FROM adminusers a LEFT JOIN roles r ON a.role_id = r.role_id WHERE a.admin_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
}

// 🔹 Fetch dropdowns (Categories, Colors, Sizes)
$categories = [];
$resultCategories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = $resultCategories->fetch_assoc()) $categories[] = $row;

$colors = [];
$resultColors = $conn->query("SELECT color_id, color FROM colors ORDER BY color ASC");
while ($row = $resultColors->fetch_assoc()) $colors[] = $row;

$sizes = [];
$resultSizes = $conn->query("SELECT size_id, size FROM sizes ORDER BY size ASC");
while ($row = $resultSizes->fetch_assoc()) $sizes[] = $row;

// 🔹 Selected filters
$selectedCategory = $_GET['category'] ?? 'all';
$selectedColor    = $_GET['color'] ?? 'all';
$selectedSize     = $_GET['size'] ?? 'all';

// 🔹 Build Query
$sqlProducts = "
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        col.color,
        sz.size,
        st.current_qty AS total_stock,
        p.created_at,
        s.supplier_name
    FROM stock st
    INNER JOIN products p ON st.product_id = p.product_id
    INNER JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN colors col ON st.color_id = col.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
";

// 🔹 Dynamic WHERE filters
$where  = ["1=1"];
$params = [];
$types  = "";

if ($selectedCategory !== 'all') {
    $where[] = "c.category_name = ?";
    $params[] = $selectedCategory;
    $types   .= "s";
}
if ($selectedColor !== 'all') {
    $where[] = "col.color = ?";
    $params[] = $selectedColor;
    $types   .= "s";
}
if ($selectedSize !== 'all') {
    $where[] = "sz.size = ?";
    $params[] = $selectedSize;
    $types   .= "s";
}

if ($where) {
    $sqlProducts .= " WHERE " . implode(" AND ", $where);
}

// 🔹 ORDER BY NEWEST TO OLDEST (Descending)
// We sort by Created Date DESC, then Product Name as a secondary sort
$sqlProducts .= " ORDER BY p.created_at DESC, p.product_name ASC";

// 🔹 Execute query
$stmt = $conn->prepare($sqlProducts);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultProducts = $stmt->get_result();

// 🔹 Collect inventory
$inventory = [];
if ($resultProducts && $resultProducts->num_rows > 0) {
    while ($row = $resultProducts->fetch_assoc()) {
        $inventory[] = $row;
    }
}

$stmt->close();
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventory | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />

  <style>
    :root {
      --rose: #e5a5b2;
      --rose-hover: #d48b98;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
      color: #374151;
    }
    .active-link {
      background-color: #fef3f5;
      color: var(--rose);
      font-weight: 600;
      border-radius: 0.5rem;
    }
    .sidebar {
      box-shadow: 2px 0 6px rgba(0,0,0,0.05);
    }
  </style>
</head>

<body class="bg-gray-50 font-poppins text-sm">
  <div class="flex h-screen overflow-hidden">
    <!-- 🧭 Sidebar -->
    <aside class="w-64 bg-white sidebar" x-data="{ userMenu: false, productMenu: true }">
      <div class="p-5 border-b">
        <div class="flex items-center space-x-3">
          <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10" />
          <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
        </div>
      </div>

      <div class="p-5 border-b flex items-center space-x-3">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
        <div>
          <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($admin_name); ?></p>
          <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="p-4 space-y-1">
        <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition">
          <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
        </a>

        <!-- User Management -->
        <div>
          <button @click="userMenu = !userMenu"
            class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
            <span><i class="fas fa-users-cog mr-2"></i> User Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': userMenu }"></i>
          </button>
          <div x-show="userMenu" x-transition class="pl-8 space-y-1 mt-1">
            <a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]">
              <i class="fas fa-user mr-2"></i>Users</a>
            <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]">
              <i class="fas fa-id-badge mr-2"></i>Roles</a>
          </div>
        </div>

        <!-- Product Management -->
        <div>
          <button @click="productMenu = !productMenu"
            class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
            <span><i class="fas fa-box-open mr-2"></i> Product Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200"
              :class="{ 'rotate-180': productMenu }"></i>
          </button>
          <div x-show="productMenu" x-transition class="pl-8 space-y-1 mt-1">
            <a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i> Category</a>
            <a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i> Product</a>
            <a href="inventory.php" class="block py-1 active-link"><i class="fas fa-warehouse mr-2"></i> Inventory</a>
            <a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i> Stock Management</a>
          </div>
        </div>

        <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition">
          <i class="fas fa-shopping-cart mr-2"></i> Orders</a>
                <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transitio"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>

        <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition">
          <i class="fas fa-industry mr-2"></i> Suppliers</a>

      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

        <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-md transition">
          <i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
      </nav>
    </aside>

    <!-- 🌸 Main Content -->
    <main class="flex-1 p-8 overflow-auto bg-gray-50">
      <!-- Header -->
      <div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm">
        <h1 class="text-2xl font-semibold">📦 Inventory Management</h1>
      </div>

      <!-- Filter Section -->
      <section class="bg-white p-6 rounded-b-2xl shadow mb-6">
        <form method="GET" action="inventory.php" class="flex flex-wrap items-center gap-6">
          <div>
            <label for="category" class="font-medium text-gray-700 text-sm">Category:</label>
            <select name="category" id="category" onchange="this.form.submit()" 
              class="p-2 border rounded-lg focus:ring-2 focus:ring-[var(--rose)] text-sm">
              <option value="all">All</option>
              <?php foreach ($categories as $category) { ?>
                <option value="<?php echo $category['category_name']; ?>" <?php echo ($selectedCategory == $category['category_name']) ? 'selected' : ''; ?>>
                  <?php echo $category['category_name']; ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div>
            <label for="color" class="font-medium text-gray-700 text-sm">Color:</label>
            <select name="color" id="color" onchange="this.form.submit()" 
              class="p-2 border rounded-lg focus:ring-2 focus:ring-[var(--rose)] text-sm">
              <option value="all">All</option>
              <?php foreach ($colors as $color) { ?>
                <option value="<?php echo $color['color']; ?>" <?php echo ($selectedColor == $color['color']) ? 'selected' : ''; ?>>
                  <?php echo $color['color']; ?>
                </option>
              <?php } ?>
            </select>
          </div>

          <div>
            <label for="size" class="font-medium text-gray-700 text-sm">Size:</label>
            <select name="size" id="size" onchange="this.form.submit()" 
              class="p-2 border rounded-lg focus:ring-2 focus:ring-[var(--rose)] text-sm">
              <option value="all">All</option>
              <?php foreach ($sizes as $size) { ?>
                <option value="<?php echo $size['size']; ?>" <?php echo ($selectedSize == $size['size']) ? 'selected' : ''; ?>>
                  <?php echo $size['size']; ?>
                </option>
              <?php } ?>
            </select>
          </div>
        </form>
      </section>

      <!-- Table -->
<div class="overflow-x-auto">
  <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm text-sm">
    <thead class="bg-gray-100 text-gray-700 uppercase text-xs font-semibold">
      <tr>
        <th class="px-4 py-3 text-left">Product Name</th>
        <th class="px-4 py-3 text-left">Category</th>
        <th class="px-4 py-3 text-center">Color</th>
        <th class="px-4 py-3 text-center">Size</th>
        <th class="px-4 py-3 text-center">Stock</th>
        <th class="px-4 py-3 text-center">Status</th>
        <th class="px-4 py-3 text-right">Created At</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-gray-100">
      <?php 
        if (!empty($inventory)) { 
          foreach ($inventory as $item) {
            $stock = (int)$item['total_stock'];
            
            // Determine Status Color
            if ($stock == 0) {
                $status_label = "Out of Stock";
                $status_class = "bg-red-100 text-red-700 border-red-200";
            } elseif ($stock < 0) { // You can adjust this threshold
                $status_label = "Low Stock";
                $status_class = "bg-yellow-100 text-yellow-700 border-yellow-200";
            } else {
                $status_label = "In Stock";
                $status_class = "bg-green-100 text-green-700 border-green-200";
            }
      ?>
      <tr class="hover:bg-gray-50 transition duration-150">
        <!-- Product Name -->
        <td class="px-4 py-3 font-medium text-gray-900">
            <?php echo htmlspecialchars($item['product_name']); ?>
        </td>

        <!-- Category -->
        <td class="px-4 py-3 text-gray-600">
            <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs border">
                <?php echo htmlspecialchars($item['category_name']); ?>
            </span>
        </td>

        <!-- 🎨 Color (Badge Style) -->
        <td class="px-4 py-3 text-center">
            <?php if (!empty($item['color'])): ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-gray-200 shadow-sm bg-white text-gray-800">
                    <!-- Optional: Add a small colored dot if you want -->
                    <span class="w-2 h-2 mr-1.5 rounded-full bg-gray-400"></span>
                    <?php echo htmlspecialchars($item['color']); ?>
                </span>
            <?php else: ?>
                <span class="text-gray-400">—</span>
            <?php endif; ?>
        </td>

        <!-- 📏 Size (Square Badge Style) -->
        <td class="px-4 py-3 text-center">
            <?php if (!empty($item['size'])): ?>
                <span class="inline-block w-8 h-8 leading-8 text-center rounded bg-white border border-gray-300 text-xs font-bold text-gray-700 shadow-sm">
                    <?php echo htmlspecialchars($item['size']); ?>
                </span>
            <?php else: ?>
                <span class="text-gray-400">—</span>
            <?php endif; ?>
        </td>

        <!-- Stock Count -->
        <td class="px-4 py-3 text-center font-bold text-gray-700">
            <?php echo $stock; ?>
        </td>

        <!-- Status -->
        <td class="px-4 py-3 text-center">
            <span class="px-2 py-1 rounded-md text-xs font-semibold border <?php echo $status_class; ?>">
                <?php echo $status_label; ?>
            </span>
        </td>

        <!-- Date -->
        <td class="px-4 py-3 text-right text-gray-500 text-xs">
            <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
        </td>
      </tr>
      <?php 
          }
        } else { 
      ?>
      <tr>
        <td colspan="7" class="text-center px-4 py-8 text-gray-400">
            <i class="fas fa-box-open text-2xl mb-2 block"></i>
            No inventory items found matching your filters.
        </td>
      </tr>
      <?php } ?>
    </tbody>
  </table>
</div>
    </main>
  </div>
</body>
</html>
