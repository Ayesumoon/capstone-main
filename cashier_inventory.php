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

// ✅ Get selected category filter
$selected_category = isset($_GET['category']) ? $_GET['category'] : 'all';

// ✅ Fetch all categories for dropdown
$categoryQuery = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
$categoryResult = $conn->query($categoryQuery);

// ✅ Fetch inventory based on category filter
if ($selected_category === 'all') {
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
        GROUP BY p.product_id, p.product_name, c.category_name, p.image_url
        ORDER BY p.product_name ASC
    ";
    $stmt = $conn->prepare($query);
} else {
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
        WHERE p.category_id = ?
        GROUP BY p.product_id, p.product_name, c.category_name, p.image_url
        ORDER BY p.product_name ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_category);
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
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-[var(--rose)]">Inventory Overview</h1>

    <!-- 🏷️ Category Dropdown -->
    <form method="GET" class="flex items-center gap-2">
      <label for="category" class="text-gray-600 font-medium">Category:</label>
      <select name="category" id="category" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" onchange="this.form.submit()">
        <option value="all" <?= $selected_category === 'all' ? 'selected' : ''; ?>>All</option>
        <?php while ($cat = $categoryResult->fetch_assoc()): ?>
          <option value="<?= $cat['category_id']; ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : ''; ?>>
            <?= htmlspecialchars($cat['category_name']); ?>
          </option>
        <?php endwhile; ?>
      </select>
    </form>
  </div>

  <!-- 📋 Inventory Table -->
  <div class="bg-white shadow rounded-lg p-6">
    <?php if ($result->num_rows > 0): ?>
      <table class="w-full text-sm border-collapse">
        <thead class="bg-[var(--rose)] text-white">
          <tr>
            <th class="px-4 py-2">Image</th>
            <th class="px-4 py-2">Product Name</th>
            <th class="px-4 py-2">Category</th>
            <th class="px-4 py-2">Price</th>
            <th class="px-4 py-2">Supplier Price</th>
            <th class="px-4 py-2">Stocks</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
          <?php
          // 🖼️ Fix image display (JSON, CSV, or single path)
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
              <img src="<?= htmlspecialchars($img); ?>" onerror="this.src='uploads/default.png';" alt="Product" class="w-14 h-14 object-cover rounded-md">
            </td>
            <td class="px-4 py-2 font-medium"><?= htmlspecialchars($row['product_name']); ?></td>
            <td class="px-4 py-2"><?= htmlspecialchars($row['category_name']); ?></td>
            <td class="px-4 py-2 text-[var(--rose)] font-semibold">₱<?= number_format($row['price'], 2); ?></td>
            <td class="px-4 py-2">₱<?= number_format($row['supplier_price'], 2); ?></td>
            <td class="px-4 py-2 <?= ($row['total_stock'] <= 5) ? 'text-red-500 font-semibold' : ''; ?>">
              <?= $row['total_stock'] ?? 0; ?>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-gray-500 text-center">No products found for this category.</p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>

<?php 
$stmt->close();
$conn->close();
?>
