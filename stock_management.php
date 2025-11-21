<?php
// stock_management.php
require 'conn.php';
session_start();

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $sql = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
            FROM adminusers a LEFT JOIN roles r ON a.role_id = r.role_id
            WHERE a.admin_id = ? LIMIT 1";
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

// 🔹 Fetch Lists
$categories = [];
$res = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($c = $res->fetch_assoc()) $categories[] = $c;

$supplier_list = [];
$res = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
while ($s = $res->fetch_assoc()) $supplier_list[] = $s;

$color_list = [];
$res = $conn->query("SELECT color_id, color FROM colors ORDER BY color ASC");
while ($c = $res->fetch_assoc()) $color_list[] = $c;

$size_list = [];
$res = $conn->query("SELECT size_id, size FROM sizes ORDER BY size ASC");
while ($s = $res->fetch_assoc()) $size_list[] = $s;

// 🔹 Filters & Logic
$selected_category = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
$stock_rows = [];
$outStock = false;

// 🔹 Stock Query
$stockQuery = "
    SELECT 
        s.stock_id,
        p.product_id,
        p.product_name,
        s.color_id,
        s.size_id,
        col.color,
        sz.size,
        COALESCE(s.current_qty, 0) AS current_qty,
        MAX(sup.supplier_id) AS supplier_id,
        MAX(sup.supplier_name) AS supplier_name,
        MAX(si.date_added) AS date_added
    FROM products p
    INNER JOIN stock s ON p.product_id = s.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN colors col ON s.color_id = col.color_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    LEFT JOIN stock_in si ON si.stock_id = s.stock_id
    LEFT JOIN suppliers sup ON si.supplier_id = sup.supplier_id
    " . ($selected_category ? "WHERE p.category_id = ? " : "") . "
    GROUP BY s.stock_id, p.product_id, p.product_name, s.color_id, s.size_id, col.color, sz.size, s.current_qty
    ORDER BY date_added DESC, p.product_name ASC
";

if ($stmt = $conn->prepare($stockQuery)) {
    if ($selected_category) {
        $stmt->bind_param('i', $selected_category);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if ((int)$r['current_qty'] === 0) $outStock = true;
        $stock_rows[] = $r;
    }
    $stmt->close();
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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
  <style>
    :root { --rose: #e5a5b2; --rose-hover: #d48b98; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    [x-cloak] { display: none !important; }
    .active-link { background-color: #fef3f5; color: var(--rose); font-weight:600; border-radius: .5rem; }
  </style>
</head>

<body class="font-poppins text-sm" 
      x-data="{ 
          userMenu: false, 
          productMenu: true, 
          stockInOpen: false,
          editStockOpen: false,
          editData: { 
              id: null, 
              product_id: '', 
              qty: 0,
              supplier_id: '',
              color_id: '',
              size_id: ''
          }
      }">
<div class="flex min-h-screen">
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
        <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1" x-data="{ open: true }">
      <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition">
        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
      </a>

      <div>
        <button @click="userMenu = !userMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
          <span><i class="fas fa-users-cog mr-2"></i> User Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <div x-show="userMenu" x-transition class="pl-8 space-y-1 mt-1">
          <a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Users</a>
          <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Roles</a>
        </div>
      </div>

      <div>
        <button @click="productMenu = !productMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded-md transition">
          <span><i class="fas fa-box-open mr-2"></i> Product Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <div x-show="productMenu" x-transition class="pl-8 space-y-1 mt-1">
          <a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i> Category</a>
          <a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i> Products</a>
          <a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i> Inventory</a>
          <a href="stock_management.php" class="block py-1 active-link"><i class="fas fa-boxes mr-2"></i> Stock Management</a>
        </div>
      </div>

      <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition"><i class="fas fa-shopping-cart mr-2"></i> Orders</a>
      <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transitio"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>
      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded-md transition"><i class="fas fa-industry mr-2"></i> Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>
      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded-md transition"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="flex-1 p-8 bg-gray-50 space-y-6 overflow-auto">

    <div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm">
      <h1 class="text-2xl font-semibold">Stock Management</h1>
    </div>

    <?php if ($outStock): ?>
      <div class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded-md shadow-sm">
        <strong class="font-semibold">⚠ Out of Stock:</strong> Some items are out of stock!
      </div>
    <?php endif; ?>

    <div class="flex flex-wrap justify-between items-center bg-white p-5 rounded-lg shadow">
      <form method="GET" class="flex items-center gap-2">
        <label class="font-medium text-sm text-gray-700">Category:</label>
        <select name="category_id" onchange="this.form.submit()" class="p-2 border rounded-md focus:ring-2 focus:ring-[var(--rose)]">
          <option value="0">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['category_id'] ?>" <?= ($cat['category_id']==$selected_category)?'selected':'' ?> >
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

    <!-- Table -->
    <div class="bg-white p-6 rounded-2xl shadow">
      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg text-sm">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Product</th>
              <th class="px-4 py-3 text-left">Color</th>
              <th class="px-4 py-3 text-left">Size</th>
              <th class="px-4 py-3 text-left">Qty</th>
              <th class="px-4 py-3 text-left">Supplier</th>
              <th class="px-4 py-3 text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <?php if (count($stock_rows) > 0): foreach ($stock_rows as $row): ?>
              <tr class="hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-medium"><?= e($row['product_name']) ?></td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded-full border bg-gray-50 text-xs"><?= e($row['color'] ?: 'N/A') ?></span></td>
                <td class="px-4 py-3"><span class="px-2 py-1 rounded border bg-gray-50 text-xs font-bold"><?= e($row['size'] ?: 'N/A') ?></span></td>
                <td class="px-4 py-3 font-bold text-gray-900"><?= (int)$row['current_qty'] ?></td>
                <td class="px-4 py-3"><?= e($row['supplier_name'] ?? 'N/A') ?></td>
                
                <td class="px-4 py-3 text-center">
                    <button 
                        @click="
                            editStockOpen = true; 
                            editData = {
                                id: <?= $row['stock_id'] ?>,
                                product_id: '<?= $row['product_id'] ?>', 
                                qty: <?= (int)$row['current_qty'] ?>,
                                supplier_id: '<?= $row['supplier_id'] ?>',
                                color_id: '<?= $row['color_id'] ?>',
                                size_id: '<?= $row['size_id'] ?>'
                            };
                            // Trigger Product Load for the Modal
                            loadProductsForEdit('<?= $row['supplier_id'] ?>', '<?= $row['product_id'] ?>');
                        "
                        class="text-[var(--rose)] hover:text-[var(--rose-hover)] bg-pink-50 hover:bg-pink-100 p-2 rounded-lg transition"
                        title="Edit Details">
                        <i class="fas fa-pen"></i>
                    </button>
                </td>
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


<div x-show="stockInOpen" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4" x-transition>
  <div @click.away="stockInOpen = false" class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 space-y-4">
    <h3 class="text-lg font-semibold text-gray-800">Stock In (New)</h3>
    <form action="process_stock_in.php" method="POST" class="space-y-4">
      <div>
        <label class="block text-sm mb-1">Supplier</label>
        <select id="supplierSelect" name="supplier_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
          <option value="">Select Supplier</option>
          <?php foreach ($supplier_list as $sup): ?>
            <option value="<?= $sup['supplier_id'] ?>"><?= e($sup['supplier_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Product</label>
        <select id="productSelect" name="product_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
          <option value="">Select Supplier First</option>
        </select>
      </div>
      <!-- Colors/Sizes etc... (Keep your existing code here) -->
      <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm mb-1">Color</label>
            <select name="color_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
              <option value="">Select</option>
              <?php foreach ($color_list as $c): ?>
                  <option value="<?= $c['color_id'] ?>"><?= e($c['color']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm mb-1">Size</label>
            <select name="size_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]" required>
              <option value="">Select</option>
              <?php foreach ($size_list as $s): ?>
                  <option value="<?= $s['size_id'] ?>"><?= e($s['size']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Quantity</label>
        <input type="number" name="quantity" min="1" class="border p-2 w-full rounded focus:ring-2 focus:ring-[var(--rose)]" required>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" @click="stockInOpen=false" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded text-sm">Cancel</button>
        <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded text-sm">Save</button>
      </div>
    </form>
  </div>
</div>


<div x-show="editStockOpen" x-cloak class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 p-4" x-transition>
  <div @click.away="editStockOpen = false" class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 space-y-4">
    <div class="flex justify-between items-center border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-800">Edit Stock Details</h3>
        <button @click="editStockOpen = false" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
    </div>

    <form action="process_edit_stock.php" method="POST" class="space-y-4">
      <input type="hidden" name="stock_id" :value="editData.id">
      
      <!-- Supplier Dropdown -->
      <div>
        <label class="block text-sm font-medium mb-1 text-gray-700">Supplier</label>
        <select id="editSupplierSelect" name="supplier_id" x-model="editData.supplier_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
            <option value="">Select Supplier</option>
            <?php foreach ($supplier_list as $sup): ?>
                <option value="<?= $sup['supplier_id'] ?>"><?= e($sup['supplier_name']) ?></option>
            <?php endforeach; ?>
        </select>
      </div>

      <!-- Product Dropdown (Dynamic) -->
      <div>
        <label class="block text-sm font-medium mb-1 text-gray-700">Product</label>
        <select id="editProductSelect" name="product_id" x-model="editData.product_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
            <option value="">Select Supplier First</option>
            <!-- Options loaded via JS -->
        </select>
      </div>

      <!-- Color & Size -->
      <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Color</label>
            <select name="color_id" x-model="editData.color_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
                <option value="">None</option>
                <?php foreach ($color_list as $c): ?>
                    <option value="<?= $c['color_id'] ?>"><?= e($c['color']) ?></option>
                <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1 text-gray-700">Size</label>
            <select name="size_id" x-model="editData.size_id" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
                <option value="">None</option>
                <?php foreach ($size_list as $s): ?>
                    <option value="<?= $s['size_id'] ?>"><?= e($s['size']) ?></option>
                <?php endforeach; ?>
            </select>
          </div>
      </div>

      <!-- Quantity -->
      <div>
        <label class="block text-sm font-medium mb-1 text-gray-700">Quantity</label>
        <input type="number" name="new_quantity" x-model="editData.qty" min="0" class="border w-full p-2 rounded focus:ring-2 focus:ring-[var(--rose)] font-semibold" required>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" @click="editStockOpen=false" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded text-sm font-medium">Cancel</button>
        <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded text-sm font-medium">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
// 1. Logic for Stock In Modal (Existing)
document.getElementById('supplierSelect').addEventListener('change', function() {
    fetchProducts(this.value, 'productSelect');
});

// 2. Logic for Edit Modal (Supplier change updates Product list)
document.getElementById('editSupplierSelect').addEventListener('change', function() {
    fetchProducts(this.value, 'editProductSelect');
});

// Helper function to fetch products
function fetchProducts(supplierId, targetSelectId) {
    const productSel = document.getElementById(targetSelectId);
    productSel.innerHTML = '<option>Loading...</option>';
    
    if (!supplierId) {
        productSel.innerHTML = '<option value="">Select Supplier First</option>';
        return;
    }

    fetch('fetch_products_by_supplier.php?supplier_id=' + encodeURIComponent(supplierId))
      .then(r => r.json())
      .then(data => {
        productSel.innerHTML = '<option value="">Select Product</option>';
        if (!Array.isArray(data) || data.length === 0) {
            productSel.innerHTML = '<option value="">No products found</option>';
            return;
        }
        data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = p.product_name;
            productSel.appendChild(opt);
        });

        // Special: If this is the Edit Modal, try to re-select the current product if it exists in the new list
        if(targetSelectId === 'editProductSelect') {
             // Get the product ID from Alpine data if available (we can access the select value)
             // But usually, the user changes supplier implies they want to pick a NEW product
        }
      })
      .catch(() => { productSel.innerHTML = '<option value="">Error loading products</option>'; });
}

// 3. Function called when opening Edit Modal to Pre-fill
function loadProductsForEdit(supplierId, currentProductId) {
    const productSel = document.getElementById('editProductSelect');
    
    // If no supplier is set on the row, just clear it
    if (!supplierId) {
        productSel.innerHTML = '<option value="">Select Supplier First</option>';
        return;
    }

    fetch('fetch_products_by_supplier.php?supplier_id=' + encodeURIComponent(supplierId))
      .then(r => r.json())
      .then(data => {
        productSel.innerHTML = '<option value="">Select Product</option>';
        data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = p.product_name;
            if (p.product_id == currentProductId) opt.selected = true; // Select current
            productSel.appendChild(opt);
        });
      });
}
</script>
</body>
</html>