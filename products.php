<?php
session_start();
require 'conn.php'; // Database connection

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details
if ($admin_id) {
    $query = "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name
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

// 🔹 Fetch categories
$categories = [];
$res = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

// 🔹 Selected category filter
$selectedCategory = $_GET['category'] ?? 'all';

// 🔹 Fetch products
$sql = "
    SELECT 
        p.product_id,
        p.product_name,
        p.description,
        p.price_id,
        p.image_url,
        c.category_name,
        GROUP_CONCAT(DISTINCT col.color ORDER BY col.color SEPARATOR ', ') AS colors,
        GROUP_CONCAT(DISTINCT sz.size ORDER BY sz.size SEPARATOR ', ') AS sizes
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock st ON p.product_id = st.product_id
    LEFT JOIN colors col ON st.color_id = col.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
";

if ($selectedCategory !== 'all') {
    $sql .= " WHERE p.category_id = ?";
}

$sql .= " GROUP BY p.product_id ORDER BY p.product_id DESC";

$stmt = $conn->prepare($sql);
if ($selectedCategory !== 'all') {
    $stmt->bind_param("i", $selectedCategory);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    // 🧠 Normalize image data
    $imageList = [];
    $raw = trim($row['image_url'] ?? '');

    if ($raw && str_starts_with($raw, '[')) {
        // JSON format
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $imageList = $decoded;
    } elseif ($raw) {
        // Comma-separated format
        $imageList = array_filter(array_map('trim', explode(',', $raw)));
    }

    // Build full paths
    $displayImages = [];
    if (!empty($imageList)) {
        foreach ($imageList as $img) {
            $img = trim($img);
            if (!str_contains($img, 'uploads/')) {
                $img = 'uploads/products/' . $img;
            }
            $displayImages[] = htmlspecialchars($img);
        }
    } else {
        $displayImages[] = 'uploads/products/default.png';
    }

    $row['display_images'] = $displayImages;
    $products[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Products</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
</head>

<body class="bg-gray-100 font-poppins text-sm">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <div class="w-64 bg-white shadow-md" x-data="{ userMenu: false, productMenu: true }">
    <div class="p-4">
      <div class="flex items-center space-x-4">
        <img src="logo2.png" alt="Logo" class="rounded-full w-12 h-12" />
        <h2 class="text-lg font-semibold">SevenDwarfs</h2>
      </div>

      <div class="mt-4 flex items-center space-x-4">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
        <div>
          <h3 class="text-sm font-semibold"><?= htmlspecialchars($admin_name); ?></h3>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-6">
      <ul>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="dashboard.php"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-users-cog mr-2"></i>User Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
          </div>
        </li>
        <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li class="py-1 hover:text-pink-600"><a href="manage_users.php"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
          <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a>
          <li class="py-1 hover:text-pink-600"><a href="customers.php"><i class="fas fa-users mr-2"></i>Customer</a></li>
        </ul>

        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-box-open mr-2"></i>Product Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
          </div>
        </li>
        <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li class="py-1 hover:text-pink-600"><a href="categories.php"><i class="fas fa-tags mr-2"></i>Category</a></li>
          <li class="py-1 bg-pink-100 text-pink-600 rounded"><a href="products.php"><i class="fas fa-box mr-2"></i>Product</a></li>
          <li class="py-1 hover:text-pink-600"><a href="inventory.php"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
          <li class="py-1 hover:text-pink-600"><a href="stock_management.php"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
        </ul>

        <li class="px-4 py-2 hover:bg-gray-200"><a href="orders.php"><i class="fas fa-shopping-cart mr-2"></i>Orders</a></li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="suppliers.php"><i class="fas fa-industry mr-2"></i>Suppliers</a></li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
      </ul>
    </nav>
  </div>

  <!-- Main Content -->
  <div class="flex-1 p-6 space-y-6">
    <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
      <h1 class="text-2xl font-semibold">Products</h1>
    </div>

    <div class="bg-white p-6 rounded-b-2xl shadow-md space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <form method="GET" id="categoryForm" class="flex items-center space-x-2">
          <label class="text-gray-700 font-medium">Category:</label>
          <select name="category" onchange="document.getElementById('categoryForm').submit()" class="p-2 border rounded-lg focus:ring-2 focus:ring-pink-500">
            <option value="all">All</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= $cat['category_id']; ?>" <?= ($selectedCategory == $cat['category_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($cat['category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <a href="add_product.php" class="flex items-center gap-2 bg-pink-300 hover:bg-pink-500 text-white text-sm font-medium rounded-md px-4 py-2 transition">
          <i class="fas fa-plus"></i> Add Product
        </a>
      </div>

      <!-- Product Table -->
      <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200 shadow-md rounded-xl overflow-hidden">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Images</th>
              <th class="px-4 py-3 text-left">Product</th>
              <th class="px-4 py-3 text-left">Description</th>
              <th class="px-4 py-3 text-left">Price</th>
              <th class="px-4 py-3 text-left">Category</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="text-gray-700 text-sm">
            <?php if (!empty($products)): ?>
              <?php foreach ($products as $product): ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="px-4 py-3">
                    <div class="flex flex-wrap gap-2" x-data="{ open: false, imageSrc: '' }">
                      <?php foreach ($product['display_images'] as $img): ?>
                        <img src="<?= $img ?>" 
                             alt="Product Image" 
                             class="inline-block w-14 h-14 rounded-lg border shadow-sm object-cover cursor-pointer hover:scale-105 transition-transform duration-200"
                             @click="imageSrc = '<?= $img ?>'; open = true"
                             onerror="this.src='uploads/products/default.png';">
                      <?php endforeach; ?>

                      <!-- Modal -->
                      <div 
                        x-show="open" 
                        x-transition 
                        @click="open = false"
                        class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
                        <div class="relative bg-white rounded-xl shadow-lg p-4 max-w-md w-full">
                          <button @click="open = false" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800 text-xl font-bold">&times;</button>
                          <img :src="imageSrc" alt="Preview" class="w-full h-auto rounded-lg object-contain">
                        </div>
                      </div>
                    </div>
                  </td>

                  <td class="px-4 py-3 font-medium"><?= htmlspecialchars($product['product_name']); ?></td>
                  <td class="px-4 py-3">
                    <?= htmlspecialchars($product['description']); ?>
                    <div class="text-xs text-gray-500 mt-1">
                      <strong>Colors:</strong> <?= $product['colors'] ?: '—'; ?><br>
                      <strong>Sizes:</strong> <?= $product['sizes'] ?: '—'; ?>
                    </div>
                  </td>
                  <td class="px-4 py-3">₱<?= number_format($product['price_id'], 2); ?></td>
                  <td class="px-4 py-3"><?= htmlspecialchars($product['category_name']); ?></td>
                  <td class="px-4 py-3">
                    <div class="flex gap-2">
                      <a href="edit_product.php?id=<?= $product['product_id']; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded-lg transition text-xs">Edit</a>
                      <a href="delete_product.php?id=<?= $product['product_id']; ?>" onclick="return confirm('Are you sure you want to delete this product?')" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg transition text-xs">Delete</a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" class="text-center text-gray-500 py-6">No products available</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
