<?php
session_start();
require 'conn.php'; // Include database connection

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

$inventory = [];
$categories = [];

// Fetch categories from the database
$sqlCategories = "SELECT category_id, category_name FROM categories";
$resultCategories = $conn->query($sqlCategories);

if ($resultCategories === false) {
    die("Error in SQL query: " . $conn->error);
}

if ($resultCategories->num_rows > 0) {
    while ($row = $resultCategories->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get selected category from the dropdown
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';

// Fetch product inventory data with supplier and category filtering
$sqlProducts = "
    SELECT 
        p.product_id, 
        p.product_name, 
        c.category_name, 
        p.stocks, 
        p.price_id,
        s.supplier_name,
        p.supplier_price
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
";

if ($selectedCategory !== 'all') {
    $sqlProducts .= " WHERE c.category_name = ?";
}

$stmt = $conn->prepare($sqlProducts);

if ($selectedCategory !== 'all') {
    $stmt->bind_param("s", $selectedCategory);
}

$stmt->execute();
$resultProducts = $stmt->get_result();

if ($resultProducts === false) {
    die("Error in SQL query: " . $conn->error);
}

if ($resultProducts->num_rows > 0) {
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
          <!-- User Management -->
          <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
            <div class="flex items-center justify-between">
              <span class="flex items-center">
                <i class="fas fa-users-cog mr-2"></i>User Management
              </span>
              <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
            </div>
          </li>
          <ul x-show="userMenu" x-transition x-cloak class="pl-8 text-sm text-gray-700 space-y-1">
            <li><a href="users.php" class="block py-1 hover:text-pink-600"><i class="fas fa-user mr-2"></i>User</a></li>
            <li><a href="user_types.php" class="block py-1 hover:text-pink-600"><i class="fas fa-id-badge mr-2"></i>Type</a></li>
            <li><a href="user_status.php" class="block py-1 hover:text-pink-600"><i class="fas fa-toggle-on mr-2"></i>Status</a></li>
            <li><a href="customers.php" class="block py-1 hover:text-pink-600"><i class="fas fa-users mr-2"></i>Customer</a></li>
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
          <ul x-show="productMenu" x-transition x-cloak class="pl-8 text-sm text-gray-700 space-y-1">
            <li><a href="categories.php" class="block py-1 hover:text-pink-600"><i class="fas fa-tags mr-2"></i>Category</a></li>
            <li><a href="products.php" class="block py-1 hover:text-pink-600"><i class="fas fa-box mr-2"></i>Product</a></li>
            <li><a href="inventory.php" class="block py-1 bg-pink-100 text-pink-600 rounded"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
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
    <div class="flex-1 p-6 overflow-auto">
      <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
        <h1 class="text-xl font-bold">Inventory Management</h1>
      </div>
      <div class="bg-white p-4 rounded-b shadow-md mb-6">
        <form method="GET" action="inventory.php" class="flex items-center gap-2">
          <label for="category" class="font-medium text-sm">Category:</label>
          <select name="category" id="category" onchange="this.form.submit()" class="border rounded-md p-2 text-sm">
            <option value="all">All</option>
            <?php foreach ($categories as $category) { ?>
              <option value="<?php echo $category['category_name']; ?>" <?php echo ($selectedCategory == $category['category_name']) ? 'selected' : ''; ?>>
                <?php echo $category['category_name']; ?>
              </option>
            <?php } ?>
          </select>
        </form>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm text-sm">
          <thead class="bg-gray-100 text-gray-700">
            <tr>
              <th class="px-4 py-3 border text-left">Product ID</th>
              <th class="px-4 py-3 border text-left">Product Code</th>
              <th class="px-4 py-3 border text-left">Category</th>
              <th class="px-4 py-3 border text-left">Price</th>
              <th class="px-4 py-3 border text-left">Supplier</th>
              <th class="px-4 py-3 border text-left">Supplier Price</th>
              <th class="px-4 py-3 border text-left">Revenue</th>
              <th class="px-4 py-3 border text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            if (!empty($inventory)) { 
              foreach ($inventory as $item) {
                $status = ($item['stocks'] > 20) ? "In Stock" : (($item['stocks'] > 0) ? "Low Stock" : "Out of Stock");
                $supplier_price = isset($item['supplier_price']) ? floatval($item['supplier_price']) : 0;
                $price = floatval($item['price_id']);
                $revenue = ($price - $supplier_price) * intval($item['stocks']);
            ?>
            <tr class="hover:bg-gray-50 transition-all">
              <td class="px-4 py-2 border"><?php echo $item['product_id']; ?></td>
              <td class="px-4 py-2 border"><?php echo $item['product_name']; ?></td>
              <td class="px-4 py-2 border"><?php echo $item['category_name']; ?></td>
              <td class="px-4 py-2 border">₱<?php echo number_format($item['price_id'], 2); ?></td>
              <td class="px-4 py-2 border"><?php echo isset($item['supplier_name']) ? htmlspecialchars($item['supplier_name']) : 'No supplier'; ?></td>
              <td class="px-4 py-2 border">₱<?php echo number_format($supplier_price, 2); ?></td>
              <td class="px-4 py-2 border text-green-600 font-medium">₱<?php echo number_format($revenue, 2); ?></td>
              <td class="px-4 py-2 border font-semibold capitalize <?php echo ($status === 'In Stock') ? 'text-green-600' : (($status === 'Low Stock') ? 'text-yellow-600' : 'text-red-600'); ?>">
                <?php echo $status; ?>
              </td>
            </tr>
            <?php }} else { ?>
            <tr>
              <td colspan="9" class="text-center px-4 py-4 text-gray-500 border">No products found</td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
