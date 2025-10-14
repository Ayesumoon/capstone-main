<?php
session_start();
require 'conn.php';

// 🔒 Ensure only logged-in cashiers can access
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// ✅ Get cashier info
$cashierRes = $conn->prepare("SELECT first_name, role_id FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$cashier = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashier['first_name'];
$role_id = $cashier['role_id'];

// Optional: Only allow role_id = 0 (Cashier)
if ($role_id != 0) {
    header("Location: dashboard.php");
    exit;
}

// ✅ Filters
$selected_category = $_GET['category'] ?? 'all';
$selected_size = $_GET['size'] ?? 'all';
$selected_color = $_GET['color'] ?? 'all';

// ✅ Fetch filter options
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$sizes = $conn->query("SELECT DISTINCT s.size FROM stock st INNER JOIN sizes s ON st.size_id = s.size_id ORDER BY s.size ASC");
$colors = $conn->query("SELECT DISTINCT c.color FROM stock st INNER JOIN colors c ON st.color_id = c.color_id ORDER BY c.color ASC");

// ✅ Build query dynamically with filters
$query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.price_id AS price,
        p.supplier_price,
        c.category_name,
        p.image_url,
        SUM(s.current_qty) AS total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock s ON p.product_id = s.product_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    LEFT JOIN colors cl ON s.color_id = cl.color_id
    WHERE 1=1
";

$params = [];
$types = "";

// Category filter
if ($selected_category !== 'all') {
    $query .= " AND p.category_id = ? ";
    $params[] = $selected_category;
    $types .= "i";
}

// Size filter
if ($selected_size !== 'all') {
    $query .= " AND sz.size = ? ";
    $params[] = $selected_size;
    $types .= "s";
}

// Color filter
if ($selected_color !== 'all') {
    $query .= " AND cl.color = ? ";
    $params[] = $selected_color;
    $types .= "s";
}

$query .= "
    GROUP BY p.product_id, p.product_name, c.category_name, p.image_url
    ORDER BY p.product_name ASC
";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cashier Inventory | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root { --rose: #d37689; --rose-hover: #b75f6f; }
body { background-color: #fef9fa; font-family: 'Poppins', sans-serif; }

.sidebar {
  width: 240px;
  background-color: white;
  border-right: 1px solid #e5e7eb;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 2px 0 6px rgba(0,0,0,0.05);
}
.sidebar a {
  display: block;
  padding: 0.75rem 1rem;
  border-radius: 8px;
  font-weight: 500;
  color: #4b5563;
  margin-bottom: 0.25rem;
}
.sidebar a:hover {
  background-color: #fef2f4;
  color: var(--rose-hover);
}
.active-link {
  background-color: var(--rose);
  color: white !important;
}
.main-content {
  margin-left: 260px;
  padding: 1.5rem;
}
</style>
</head>
<body>

<!-- 🌸 Sidebar -->
<aside class="sidebar">
  <div>
    <div class="flex items-center gap-3 mb-6">
      <img src="logo.png" class="w-10 h-10 rounded-full" alt="Logo">
      <h1 class="text-lg font-semibold text-[var(--rose)]">Seven Dwarfs</h1>
    </div>
    <nav>
      <a href="cashier_pos.php">🛍️ POS</a>
      <a href="cashier_transactions.php">💰 Transactions</a>
      <a href="cashier_inventory.php" class="active-link">📦 Inventory</a>
    </nav>
  </div>

  <div class="mt-auto border-t pt-3">
    <p class="text-sm text-gray-600 mb-2">Cashier: 
      <span class="font-medium text-[var(--rose)]"><?= htmlspecialchars($cashier_name); ?></span>
    </p>
    <form action="logout.php" method="POST">
      <button class="w-full text-left text-red-500 hover:text-red-600 font-medium">🚪 Logout</button>
    </form>
  </div>
</aside>

<!-- 🌸 Main Content -->
<div class="main-content">
  <!-- 🔹 Header + Filters -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <h1 class="text-2xl font-semibold text-[var(--rose)]">Inventory Overview</h1>

    <!-- 🏷️ Filter Form -->
    <form method="GET" class="flex flex-wrap items-center gap-3 bg-white px-4 py-2 rounded-xl shadow border border-gray-100">
      
      <!-- Category Filter -->
      <div class="flex items-center gap-2">
        <label for="category" class="text-gray-600 font-medium">Category:</label>
        <select name="category" id="category"
          class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
          <option value="all" <?= $selected_category === 'all' ? 'selected' : ''; ?>>All</option>
          <?php while ($cat = $categories->fetch_assoc()): ?>
            <option value="<?= $cat['category_id']; ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($cat['category_name']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Size Filter -->
      <div class="flex items-center gap-2">
        <label for="size" class="text-gray-600 font-medium">Size:</label>
        <select name="size" id="size"
          class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
          <option value="all" <?= $selected_size === 'all' ? 'selected' : ''; ?>>All</option>
          <?php while ($sz = $sizes->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($sz['size']); ?>" <?= $selected_size == $sz['size'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($sz['size']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <!-- Color Filter -->
      <div class="flex items-center gap-2">
        <label for="color" class="text-gray-600 font-medium">Color:</label>
        <select name="color" id="color"
          class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)]">
          <option value="all" <?= $selected_color === 'all' ? 'selected' : ''; ?>>All</option>
          <?php while ($cl = $colors->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($cl['color']); ?>" <?= $selected_color == $cl['color'] ? 'selected' : ''; ?>>
              <?= htmlspecialchars($cl['color']); ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <button type="submit"
        class="bg-[var(--rose)] text-white px-5 py-1.5 rounded-lg hover:bg-[var(--rose-hover)] transition font-medium shadow-sm">
        Filter
      </button>
    </form>
  </div>

  <!-- 📋 Inventory Table -->
  <div class="bg-white shadow rounded-xl p-6 border border-gray-100">
    <?php if ($result->num_rows > 0): ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead class="bg-[var(--rose)] text-white">
            <tr>
              <th class="px-4 py-2 text-left">Image</th>
              <th class="px-4 py-2 text-left">Product Name</th>
              <th class="px-4 py-2 text-left">Category</th>
              <th class="px-4 py-2 text-left">Price</th>
              <th class="px-4 py-2 text-left">Supplier Price</th>
              <th class="px-4 py-2 text-center">Stocks</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <?php
            // 🖼️ Handle image display
            $imagePath = $row['image_url'];
            if (!empty($imagePath)) {
                if (str_starts_with(trim($imagePath), '[')) {
                    $decoded = json_decode($imagePath, true);
                    $img = is_array($decoded) && count($decoded) > 0 ? $decoded[0] : 'uploads/default.png';
                } elseif (str_contains($imagePath, ',')) {
                    $parts = explode(',', $imagePath);
                    $img = trim($parts[0]);
                } else {
                    $img = trim($imagePath);
                }
            } else {
                $img = 'uploads/default.png';
            }
            ?>
            <tr class="border-b hover:bg-pink-50 transition">
              <td class="px-4 py-2">
                <img src="<?= htmlspecialchars($img); ?>" onerror="this.src='uploads/default.png';"
                  class="w-14 h-14 object-cover rounded-md shadow-sm" alt="Product">
              </td>
              <td class="px-4 py-2 font-medium text-gray-800"><?= htmlspecialchars($row['product_name']); ?></td>
              <td class="px-4 py-2 text-gray-600"><?= htmlspecialchars($row['category_name']); ?></td>
              <td class="px-4 py-2 text-[var(--rose)] font-semibold">₱<?= number_format($row['price'], 2); ?></td>
              <td class="px-4 py-2 text-gray-700">₱<?= number_format($row['supplier_price'], 2); ?></td>
              <td class="px-4 py-2 text-center <?= ($row['total_stock'] <= 5) ? 'text-red-500 font-semibold' : 'text-gray-700'; ?>">
                <?= $row['total_stock'] ?? 0; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p class="text-gray-500 text-center py-8">No products found for this filter.</p>
    <?php endif; ?>
  </div>
</div>


</body>
</html>

<?php 
$stmt->close();
$conn->close();
?>
