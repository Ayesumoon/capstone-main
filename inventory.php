<?php
session_start();
require 'conn.php'; // Include database connection

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details if logged in
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

// 🔹 Fetch categories
$categories = [];
$resultCategories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = $resultCategories->fetch_assoc()) {
    $categories[] = $row;
}

// 🔹 Fetch colors
$colors = [];
$resultColors = $conn->query("SELECT color_id, color FROM colors ORDER BY color ASC");
while ($row = $resultColors->fetch_assoc()) {
    $colors[] = $row;
}

// 🔹 Fetch sizes
$sizes = [];
$resultSizes = $conn->query("SELECT size_id, size FROM sizes ORDER BY size ASC");
while ($row = $resultSizes->fetch_assoc()) {
    $sizes[] = $row;
}

// 🔹 Selected filters
$selectedCategory = $_GET['category'] ?? 'all';
$selectedColor    = $_GET['color'] ?? 'all';
$selectedSize     = $_GET['size'] ?? 'all';

// 🔹 Build query (grouped per product)
$sqlProducts = "
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        GROUP_CONCAT(DISTINCT col.color ORDER BY col.color SEPARATOR ', ') AS colors,
        GROUP_CONCAT(DISTINCT sz.size ORDER BY sz.size SEPARATOR ', ') AS sizes,
        SUM(st.current_qty) AS total_stock,
        p.created_at,
        s.supplier_name
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN stock st ON p.product_id = st.product_id
    LEFT JOIN colors col ON st.color_id = col.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
";

// 🔹 Dynamic WHERE filters
$where  = [];
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

$sqlProducts .= " GROUP BY p.product_id ORDER BY p.product_id DESC";

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
  <title>Inventory</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            poppins: ['Poppins', 'sans-serif'],
          },
          colors: {
            primary: '#ec4899',
          }
        }
      }
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 font-sans">
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <div class="w-64 bg-white shadow-md overflow-y-auto" x-data="{ userMenu: false, productMenu: false }">
      <div class="p-4 border-b">
        <div class="flex items-center space-x-4">
          <img src="logo2.png" alt="Logo" class="rounded-full w-12 h-12"/>
          <h2 class="text-lg font-semibold">SevenDwarfs</h2>
        </div>
        <div class="mt-4 flex items-center space-x-4">
          <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10"/>
          <div>
            <h3 class="text-sm font-semibold"><?php echo htmlspecialchars($admin_name); ?></h3>
            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
          </div>
        </div>
      </div>
      <!-- Navigation -->
      <nav class="mt-4">
        <ul class="space-y-1">
          <li class="px-4 py-2 hover:bg-gray-200">
            <a href="dashboard.php" class="flex items-center">
              <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
            </a>
          </li>
          <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
            <div class="flex items-center justify-between">
              <span class="flex items-center">
                <i class="fas fa-users-cog mr-2"></i>User Management
              </span>
              <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
            </div>
          </li>
          <ul x-show="userMenu" x-transition x-cloak class="pl-8 text-sm text-gray-700 space-y-1">
            <li><a href="manage_users.php" class="block py-1 hover:text-pink-600"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
            <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a>
            <li><a href="customers.php" class="block py-1 hover:text-pink-600"><i class="fas fa-users mr-2"></i>Customer</a></li>
          </ul>
          <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
            <div class="flex items-center justify-between">
              <span class="flex items-center">
                <i class="fas fa-box-open mr-2"></i>Product Management
              </span>
              <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
            </div>
          </li>
          <ul x-show="productMenu" x-transition x-cloak class="pl-8 text-sm text-gray-700 space-y-1">
            <li><a href="categories.php" class="block py-1 hover:text-pink-600"><i class="fas fa-tags mr-2"></i>Category</a></li>
            <li><a href="products.php" class="block py-1 hover:text-pink-600"><i class="fas fa-box mr-2"></i>Product</a></li>
            <li><a href="inventory.php" class="block py-1 bg-pink-100 text-pink-600 rounded"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
            <li class="py-1 hover:text-pink-600"><a href="stock_management.php" class="flex items-center"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
          </ul>
          <li class="px-4 py-2 hover:bg-gray-200">
            <a href="orders.php" class="flex items-center"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
          </li>
          <li class="px-4 py-2 hover:bg-gray-200">
            <a href="suppliers.php" class="flex items-center"><i class="fas fa-industry mr-2"></i>Suppliers</a>
          </li>
          <li class="px-4 py-2 hover:bg-gray-200">
            <a href="logout.php" class="flex items-center"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a>
          </li>
        </ul>
      </nav>
    </div>

    <!-- Main Content -->
    <div class="flex-1 p-6 overflow-auto">
      <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
        <h1 class="text-xl font-bold">Inventory Management</h1>
      </div>

      <div class="bg-white p-4 rounded-b shadow-md mb-6">
        <form method="GET" action="inventory.php" class="flex flex-wrap items-center gap-4">
          <div>
            <label for="category" class="font-medium text-sm">Category:</label>
            <select name="category" id="category" onchange="this.form.submit()" class="border rounded-md p-2 text-sm">
              <option value="all">All</option>
              <?php foreach ($categories as $category) { ?>
                <option value="<?php echo $category['category_name']; ?>" <?php echo ($selectedCategory == $category['category_name']) ? 'selected' : ''; ?>>
                  <?php echo $category['category_name']; ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <div>
            <label for="color" class="font-medium text-sm">Color:</label>
            <select name="color" id="color" onchange="this.form.submit()" class="border rounded-md p-2 text-sm">
              <option value="all">All</option>
              <?php foreach ($colors as $color) { ?>
                <option value="<?php echo $color['color']; ?>" <?php echo ($selectedColor == $color['color']) ? 'selected' : ''; ?>>
                  <?php echo $color['color']; ?>
                </option>
              <?php } ?>
            </select>
          </div>
          <div>
            <label for="size" class="font-medium text-sm">Size:</label>
            <select name="size" id="size" onchange="this.form.submit()" class="border rounded-md p-2 text-sm">
              <option value="all">All</option>
              <?php foreach ($sizes as $size) { ?>
                <option value="<?php echo $size['size']; ?>" <?php echo ($selectedSize == $size['size']) ? 'selected' : ''; ?>>
                  <?php echo $size['size']; ?>
                </option>
              <?php } ?>
            </select>
          </div>
        </form>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm text-sm">
          <thead class="bg-gray-100 text-gray-700">
            <tr>
              <th class="px-4 py-3 border text-left">Product Name</th>
              <th class="px-4 py-3 border text-left">Category</th>
              <th class="px-4 py-3 border text-left">Colors</th>
              <th class="px-4 py-3 border text-left">Sizes</th>
              <th class="px-4 py-3 border text-left">Created At</th>
              <th class="px-4 py-3 border text-left">Stock</th>
              <th class="px-4 py-3 border text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if (!empty($inventory)) { 
              foreach ($inventory as $item) {
                $stock = (int)$item['total_stock'];
                $status = ($stock > 20) ? "In Stock" : (($stock > 0) ? "Low Stock" : "Out of Stock");
            ?>
            <tr class="hover:bg-gray-50 transition-all">
              <td class="px-4 py-2 border"><?php echo $item['product_name']; ?></td>
              <td class="px-4 py-2 border"><?php echo $item['category_name']; ?></td>
              <td class="px-4 py-2 border"><?php echo $item['colors'] ?: '—'; ?></td>
              <td class="px-4 py-2 border"><?php echo $item['sizes'] ?: '—'; ?></td>
              <td class="px-4 py-2 border"><?php echo $item['created_at']; ?></td>
              <td class="px-4 py-2 border text-center"><?php echo $stock; ?></td>
              <td class="px-4 py-2 border font-semibold capitalize <?php echo ($status === 'In Stock') ? 'text-green-600' : (($status === 'Low Stock') ? 'text-yellow-600' : 'text-red-600'); ?>">
                <?php echo $status; ?>
              </td>
            </tr>
            <?php }} else { ?>
            <tr>
              <td colspan="7" class="text-center px-4 py-4 text-gray-500 border">No products found</td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
