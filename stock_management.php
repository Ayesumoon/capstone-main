<?php
// stock_management.php
require 'conn.php';
session_start();

/**
 * Minimal helper for safe echoing
 */
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/* ---------------------------
   Fetch logged-in user details
   --------------------------- */
$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $sql = "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
        LIMIT 1
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $admin_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $admin_name = $row['full_name'] ?: $admin_name;
            $admin_role = $row['role_name'] ?: $admin_role;
        }
        $stmt->close();
    }
}

/* -----------------------------------------------------
   Auto-update stock current_qty from stock_in and orders
   -----------------------------------------------------
   NOTE: adjust order_status_id list to match "sold/completed"
         in your system if necessary.
*/
$updateStockSQL = "
    UPDATE stock s
    LEFT JOIN (
        SELECT stock_id, SUM(quantity) AS total_in
        FROM stock_in
        GROUP BY stock_id
    ) si ON s.stock_id = si.stock_id
    LEFT JOIN (
        SELECT oi.stock_id, SUM(oi.qty) AS total_sold
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.order_id
        -- adjust statuses as needed (these were used previously)
        WHERE o.order_status_id IN (2, 3, 4)
        GROUP BY oi.stock_id
    ) so ON s.stock_id = so.stock_id
    SET s.current_qty = GREATEST(COALESCE(si.total_in,0) - COALESCE(so.total_sold,0), 0)
";
@$conn->query($updateStockSQL); // ignore minor errors here; optional: check $conn->error

/* ----------------------
   Filters & inputs
   ---------------------- */
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

/* ----------------------
   Fetch categories
   ---------------------- */
$categories = [];
if ($res = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")) {
    while ($c = $res->fetch_assoc()) $categories[] = $c;
    $res->close();
}

/* -----------------------------------------------------
   Stock query (B: show only products that have stock rows)
   - Join products -> stock -> colors/sizes -> stock_in -> suppliers
   - Aggregates latest date_added and supplier name for display
   ----------------------------------------------------- */
$stock_rows = [];
$lowStock = false;
$outStock = false;

$stockQuery = "
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        col.color,
        sz.size,
        COALESCE(s.current_qty, 0) AS current_qty,
        MAX(sup.supplier_name) AS supplier_name,
        MAX(si.date_added) AS date_added
    FROM products p
    INNER JOIN stock s ON p.product_id = s.product_id   -- ensures only products with stock rows appear (Option B)
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN colors col ON s.color_id = col.color_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    LEFT JOIN stock_in si ON si.stock_id = s.stock_id
    LEFT JOIN suppliers sup ON si.supplier_id = sup.supplier_id
    " . ($selected_category ? "WHERE p.category_id = ? " : "") . "
    GROUP BY p.product_id, p.product_name, c.category_name, col.color, sz.size, s.current_qty
    ORDER BY date_added DESC, p.product_name ASC
";

if ($stmt = $conn->prepare($stockQuery)) {
    if ($selected_category) {
        $stmt->bind_param('i', $selected_category);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $qty = (int)$r['current_qty'];
        if ($qty === 0) $outStock = true;
        if ($qty > 0 && $qty <= 0) $lowStock = true;
        $stock_rows[] = $r;
    }
    $stmt->close();
}

/* ----------------------
   Fetch suppliers (for modal)
   ---------------------- */
$supplier_list = [];
if ($res = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC")) {
    while ($sup = $res->fetch_assoc()) $supplier_list[] = $sup;
    $res->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
  <style>
    :root { --rose: #e5a5b2; --rose-hover: #d48b98; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    [x-cloak] { display: none !important; }
    .active-link { background-color: #fef3f5; color: var(--rose); font-weight:600; border-radius: .5rem; }
  </style>
</head>

<body class="font-poppins text-sm" x-data="{ userMenu:false, productMenu:true, stockInOpen:false }">
<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-md min-h-screen">
    <div class="p-5 border-b">
      <div class="flex items-center space-x-3">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10" />
        <h2 class="text-lg font-semibold text-[var(--rose)]">SevenDwarfs</h2>
      </div>
      <div class="mt-4 flex items-center space-x-3">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
        <div>
          <h3 class="text-sm font-semibold text-gray-800"><?= e($admin_name) ?></h3>
          <p class="text-xs text-gray-500"><?= e($admin_role) ?></p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-100 transition"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>

      <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
      </button>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Users</a></li>
        <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Roles</a>
      </ul>

      <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
      </button>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
        <li><a href="stock_management.php" class="block py-1 active-link"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
      </ul>

      <a href="orders.php" class="block px-4 py-2 rounded hover:bg-gray-100 transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transitio"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>
      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>

      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded transition"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8 bg-gray-50 space-y-6 overflow-auto">

    <!-- Header -->
    <div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm">
      <h1 class="text-2xl font-semibold">Stock Management</h1>
    </div>

    <!-- Alerts -->
    <?php if ($outStock): ?>
      <div class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded-md shadow-sm">
        <strong class="font-semibold">⚠ Out of Stock:</strong> Some products are completely out of stock!
      </div>
    <?php endif; ?>
    <?php if ($lowStock): ?>
      <div class="bg-yellow-50 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-md shadow-sm">
        <strong class="font-semibold">⚠ Low Stock:</strong> Some products are running low (≤ 20 items left).
      </div>
    <?php endif; ?>

    <!-- Filter + Stock In Button -->
    <div class="flex flex-wrap justify-between items-center bg-white p-5 rounded-lg shadow">
      <form method="GET" class="flex items-center gap-2">
        <label class="font-medium text-sm text-gray-700">Filter by Category:</label>
        <select name="category_id" onchange="this.form.submit()" class="p-2 border rounded-md focus:ring-2 focus:ring-[var(--rose)]">
          <option value="0">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['category_id'] ?>" <?= ($cat['category_id']==$selected_category)?'selected':'' ?> >
              <?= e($cat['category_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <button type="button" @click="stockInOpen = true"
        class="flex items-center gap-2 bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white text-sm font-medium rounded-md px-4 py-2 shadow transition">
        <i class="fas fa-plus"></i> Stock In
      </button>
    </div>

    <!-- Stock Table -->
    <div class="bg-white p-6 rounded-2xl shadow">
      <h2 class="text-lg font-semibold mb-4 text-gray-800">Current Stock</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg text-sm">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Product</th>
              <th class="px-4 py-3 text-left">Color</th>
              <th class="px-4 py-3 text-left">Size</th>
              <th class="px-4 py-3 text-left">Quantity</th>
              <th class="px-4 py-3 text-left">Supplier</th>
              <th class="px-4 py-3 text-left">Date Added</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <?php if (count($stock_rows) > 0): foreach ($stock_rows as $row): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-3"><?= e($row['product_name']) ?></td>
                <td class="px-4 py-3"><?= e($row['color'] ?: '—') ?></td>
                <td class="px-4 py-3"><?= e($row['size'] ?: '—') ?></td>
                <td class="px-4 py-3 font-semibold"><?= (int)$row['current_qty'] ?></td>
                <td class="px-4 py-3"><?= e($row['supplier_name'] ?? 'N/A') ?></td>
                <td class="px-4 py-3"><?= e($row['date_added'] ?? 'N/A') ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" class="text-center py-6 text-gray-500">No stock records found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<!-- Stock In Modal -->
<div x-show="stockInOpen" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4" x-transition>
  <div @click.away="stockInOpen = false" class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 space-y-4">
    <h3 class="text-lg font-semibold text-gray-800">Stock In</h3>

    <form action="process_stock_in.php" method="POST" class="space-y-4">

      <!-- Supplier -->
      <div>
        <label class="block text-sm mb-1">Supplier</label>
        <select id="supplierSelect" name="supplier_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
          <option value="">Select Supplier</option>
          <?php foreach ($supplier_list as $sup): ?>
            <option value="<?= (int)$sup['supplier_id'] ?>"><?= e($sup['supplier_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Product (loaded by supplier) -->
      <div>
        <label class="block text-sm mb-1">Product</label>
        <select id="productSelect" name="product_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
          <option value="">Select a Supplier First</option>
        </select>
      </div>

      <!-- Color -->
      <div>
        <label class="block text-sm mb-1">Color</label>
        <select id="colorSelect" name="color_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
          <option value="">Select Color</option>
          <?php
            $colors = $conn->query("SELECT color_id,color FROM colors ORDER BY color ASC");
            while ($c = $colors->fetch_assoc()): ?>
              <option value="<?= (int)$c['color_id'] ?>"><?= e($c['color']) ?></option>
          <?php endwhile; ?>
        </select>

        <div class="mt-2 flex gap-2">
          <input type="text" id="newColorInput" placeholder="New color" class="border p-2 rounded w-full focus:ring-2 focus:ring-[var(--rose)]">
          <button type="button" id="addColorBtn" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-3 py-2 rounded text-sm">Add</button>
        </div>
        <p id="colorMessage" class="text-xs mt-1"></p>
      </div>

      <!-- Size -->
      <div>
        <label class="block text-sm mb-1">Size</label>
        <select id="sizeSelect" name="size_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
          <option value="">Select Size</option>
          <?php
            $sizes = $conn->query("SELECT size_id,size FROM sizes ORDER BY size ASC");
            while ($s = $sizes->fetch_assoc()): ?>
              <option value="<?= (int)$s['size_id'] ?>"><?= e($s['size']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Quantity -->
      <div>
        <label class="block text-sm mb-1">Quantity</label>
        <input type="number" name="quantity" min="1" class="border p-2 w-full rounded focus:ring-2 focus:ring-[var(--rose)]" required>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" @click="stockInOpen=false" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded text-sm">Cancel</button>
        <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded text-sm">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
// Load products for selected supplier
document.getElementById('supplierSelect').addEventListener('change', function() {
    const supplierId = this.value;
    const productSel = document.getElementById('productSelect');
    productSel.innerHTML = '<option>Loading...</option>';
    if (!supplierId) {
        productSel.innerHTML = '<option value="">Select a Supplier First</option>';
        return;
    }
    fetch('fetch_products_by_supplier.php?supplier_id=' + encodeURIComponent(supplierId))
      .then(r => r.json())
      .then(data => {
        productSel.innerHTML = '<option value="">Select Product</option>';
        if (!Array.isArray(data) || data.length === 0) {
            productSel.innerHTML = '<option value="">No products for this supplier</option>';
            return;
        }
        data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = p.product_name;
            productSel.appendChild(opt);
        });
      })
      .catch(() => {
        productSel.innerHTML = '<option value="">Error loading products</option>';
      });
});

// Add color (AJAX)
document.getElementById('addColorBtn').addEventListener('click', function() {
    const input = document.getElementById('newColorInput');
    const msg = document.getElementById('colorMessage');
    const colorName = input.value.trim();
    if (!colorName) {
        msg.textContent = 'Enter a color name.';
        msg.className = 'text-red-500 text-xs';
        return;
    }
    fetch('add_color.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'color=' + encodeURIComponent(colorName)
    })
    .then(r => r.json())
    .then(json => {
        if (json.success) {
            const sel = document.getElementById('colorSelect');
            const opt = document.createElement('option');
            opt.value = json.color_id;
            opt.textContent = json.color;
            opt.selected = true;
            sel.appendChild(opt);
            msg.textContent = 'Color added!';
            msg.className = 'text-green-500 text-xs';
            input.value = '';
        } else {
            msg.textContent = json.message || 'Error adding color.';
            msg.className = 'text-red-500 text-xs';
        }
    })
    .catch(() => {
        msg.textContent = 'Server error.';
        msg.className = 'text-red-500 text-xs';
    });
});
</script>
</body>
</html>
