<?php
require 'conn.php';
session_start();

// Fetch logged-in user details
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $query = "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
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

// Get selected category
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Fetch categories
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");

// Fetch products for stock-in modal
$products = [];
$product_query = $conn->query("SELECT product_id, product_name FROM products ORDER BY product_name ASC");
while ($p = $product_query->fetch_assoc()) {
    $products[] = $p;
}

// Build stock query
$stock_query = "
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        col.color,
        sz.size,
        COALESCE(st.current_qty, 0) AS current_qty,
        MAX(sup.supplier_name) AS supplier_name,
        MAX(si.date_added) AS date_added
    FROM products p
    INNER JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock st ON p.product_id = st.product_id
    LEFT JOIN colors col ON st.color_id = col.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
    LEFT JOIN stock_in si ON si.stock_id = st.stock_id
    LEFT JOIN suppliers sup ON si.supplier_id = sup.supplier_id
    " . ($selected_category ? "WHERE p.category_id = $selected_category" : "") . "
    GROUP BY p.product_id, p.product_name, c.category_name, col.color, sz.size, st.current_qty
    ORDER BY date_added DESC
";
$result_stock = $conn->query($stock_query);

// buffer rows so we can scan + display
$stock_rows = [];
$lowStock = false;
$outStock = false;
if ($result_stock && $result_stock->num_rows > 0) {
    while ($r = $result_stock->fetch_assoc()) {
        $qty = (int)$r['current_qty'];
        if ($qty == 0) $outStock = true;
        if ($qty > 0 && $qty <= 20) $lowStock = true;
        $stock_rows[] = $r;
    }
}

// Fetch suppliers for modal
$supplier_list = [];
$supplier_query = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
while ($sup = $supplier_query->fetch_assoc()) {
    $supplier_list[] = $sup;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { poppins: ['Poppins', 'sans-serif'] },
          colors: { primary: '#ec4899' }
        }
      }
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <style>[x-cloak]{ display:none !important; }</style>
</head>
<body class="bg-gray-100 font-poppins text-sm" 
      x-data="{ userMenu:false, productMenu:true, stockInOpen:false }">

<div class="flex h-screen">
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

    <!-- Navigation -->
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
          <li class="py-1"><a href="customers.php" class="flex items-center space-x-2 hover:text-pink-600"><i class="fas fa-users"></i><span>Customer</span></a></li>
        </ul>
        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center"><i class="fas fa-box-open mr-2"></i>Product Management</span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
          </div>
        </li>
        <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li class="py-1 hover:text-pink-600"><a href="categories.php" class="flex items-center"><i class="fas fa-tags mr-2"></i>Category</a></li>
          <li class="py-1 hover:text-pink-600"><a href="products.php" class="flex items-center"><i class="fas fa-box mr-2"></i>Product</a></li>
          <li class="py-1 hover:text-pink-600"><a href="inventory.php" class="flex items-center"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
          <li class="py-1 bg-pink-100 text-pink-600 rounded"><a href="stock_management.php" class="flex items-center"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
        </ul>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="orders.php" class="flex items-center"><i class="fas fa-shopping-cart mr-2"></i>Orders</a></li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="refund_history.php" class="flex items-center"><i class="fas fa-undo-alt mr-2"></i>Refund History</a></li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="suppliers.php" class="flex items-center"><i class="fas fa-industry mr-2"></i>Suppliers</a></li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="storesettings.php" class="flex items-center"><i class="fas fa-cog mr-2"></i>Store Settings</a></li>
        <li class="px-4 py-2 hover:bg-gray-200"><a href="logout.php" class="flex items-center"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a></li>
      </ul>
    </nav>
  </div> 

  <!-- Main Content -->
  <div class="flex-1 p-6 space-y-6">
    <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
      <h1 class="text-2xl font-semibold">Stock Management</h1>
    </div>

    <!-- Flash Alerts -->
    <?php if ($outStock): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative animate-pulse" role="alert">
        <strong class="font-bold">⚠ Out of Stock:</strong>
        <span class="block sm:inline">Some products are completely out of stock!</span>
      </div>
    <?php endif; ?>
    <?php if ($lowStock): ?>
      <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative animate-pulse" role="alert">
        <strong class="font-bold">⚠ Low Stock:</strong>
        <span class="block sm:inline">Some products are running low (≤ 20 items left).</span>
      </div>
    <?php endif; ?>

    <!-- Category Filter -->
    <form method="GET" class="mb-4">
      <label class="block text-sm font-medium mb-1">Filter by Category:</label>
      <select name="category_id" class="border p-2 rounded" onchange="this.form.submit()">
        <option value="0">All Categories</option>
        <?php 
        $categories->data_seek(0);
        while ($cat=$categories->fetch_assoc()): ?>
          <option value="<?= $cat['category_id'] ?>" <?= ($cat['category_id']==$selected_category)?'selected':'' ?>>
            <?= htmlspecialchars($cat['category_name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </form>

    <!-- Stock In Button -->
    <button type="button"
      @click="stockInOpen = true"
      class="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm rounded-md px-4 py-2">
      <i class="fas fa-plus"></i> Stock In
    </button>

    <!-- Stock Table -->
    <div class="bg-white p-8 rounded-lg shadow w-full">
      <h2 class="text-lg font-semibold mb-3">Current Stock</h2>
      <table class="w-full border border-gray-300 text-sm">
        <thead>
          <tr class="border-b bg-gray-100">
            <th class="px-4 py-3 text-left">Product</th>
            <th class="px-4 py-3 text-left">Color</th>
            <th class="px-4 py-3 text-left">Size</th>
            <th class="px-4 py-3 text-left">Quantity</th>
            <th class="px-4 py-3 text-left">Supplier</th>
            <th class="px-4 py-3 text-left">Date Added</th>
          </tr>
        </thead>
        <tbody>
          <?php if(count($stock_rows) > 0): foreach($stock_rows as $row): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="px-4 py-3"><?= htmlspecialchars($row['product_name']) ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['color'] ?: '—') ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['size'] ?: '—') ?></td>
              <td class="px-4 py-3 font-semibold"><?= htmlspecialchars($row['current_qty']) ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['supplier_name'] ?? 'N/A') ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($row['date_added'] ?? 'N/A') ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center py-4 text-gray-500">No stock records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div x-show="stockInOpen" x-cloak
     class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
     x-transition:enter="ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
  <div @click.away="stockInOpen=false"
       class="bg-white p-6 rounded-lg shadow-lg w-1/3 transform transition-all"
       x-transition:enter="ease-out duration-300"
       x-transition:enter-start="opacity-0 scale-95"
       x-transition:enter-end="opacity-100 scale-100"
       x-transition:leave="ease-in duration-200"
       x-transition:leave-start="opacity-100 scale-100"
       x-transition:leave-end="opacity-0 scale-95">

    <h3 class="text-lg font-semibold mb-4">Stock In</h3>
    <form action="process_stock_in.php" method="POST" class="space-y-4">
      <!-- Product -->
      <div>
        <label class="block text-sm mb-1">Product</label>
        <select name="product_id" class="border w-full p-2 rounded" required>
          <option value="">Select Product</option>
          <?php foreach($products as $p): ?>
            <option value="<?= $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Color -->
      <div>
        <label class="block text-sm mb-1">Color</label>
        <select id="colorSelect" name="color_id" class="border w-full p-2 rounded" required>
          <option value="">Select Color</option>
          <?php 
          $colors = $conn->query("SELECT color_id,color FROM colors ORDER BY color ASC");
          while($c = $colors->fetch_assoc()): ?>
            <option value="<?= $c['color_id'] ?>"><?= htmlspecialchars($c['color']) ?></option>
          <?php endwhile; ?>
        </select>

        <!-- Add new color -->
        <div class="mt-2 flex gap-2">
          <input type="text" id="newColorInput" placeholder="New color" class="border p-2 rounded w-full">
          <button type="button" id="addColorBtn" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded">Add</button>
        </div>
        <p id="colorMessage" class="text-xs mt-1"></p>
      </div>

      <!-- Size -->
      <div>
        <label class="block text-sm mb-1">Size</label>
        <select name="size_id" class="border w-full p-2 rounded" required>
          <option value="">Select Size</option>
          <?php 
          $sizes = $conn->query("SELECT size_id,size FROM sizes ORDER BY size ASC");
          while($s = $sizes->fetch_assoc()): ?>
            <option value="<?= $s['size_id'] ?>"><?= htmlspecialchars($s['size']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Quantity -->
      <div>
        <label class="block text-sm mb-1">Quantity</label>
        <input type="number" name="quantity" min="1" class="border p-2 w-full rounded" required>
      </div>

      <!-- Supplier -->
      <div>
        <label class="block text-sm mb-1">Supplier</label>
        <select name="supplier_id" class="border w-full p-2 rounded" required>
          <option value="">Select Supplier</option>
          <?php foreach($supplier_list as $sup): ?>
            <option value="<?= $sup['supplier_id'] ?>"><?= htmlspecialchars($sup['supplier_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Actions -->
      <div class="flex justify-end space-x-2">
        <button type="button" @click="stockInOpen=false" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
// Handle adding new color
document.getElementById("addColorBtn").addEventListener("click", function () {
  const colorName = document.getElementById("newColorInput").value.trim();
  const msg = document.getElementById("colorMessage");
  if (!colorName) {
    msg.textContent = "Enter a color name.";
    msg.className = "text-red-500 text-xs";
    return;
  }
  fetch("add_color.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "color=" + encodeURIComponent(colorName)
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      const select = document.getElementById("colorSelect");
      const option = document.createElement("option");
      option.value = data.color_id;
      option.textContent = data.color;
      option.selected = true;
      select.appendChild(option);

      msg.textContent = "Color added!";
      msg.className = "text-green-500 text-xs";
      document.getElementById("newColorInput").value = "";
    } else {
      msg.textContent = data.message || "Error adding color.";
      msg.className = "text-red-500 text-xs";
    }
  })
  .catch(() => {
    msg.textContent = "Server error.";
    msg.className = "text-red-500 text-xs";
  });
});
</script>
</body>
</html>
