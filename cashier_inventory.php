<?php
session_start();
require 'conn.php';

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

if ($role_id != 1) {
    header("Location: dashboard.php");
    exit;
}

$selected_category = $_GET['category'] ?? 'all';
$selected_size = $_GET['size'] ?? 'all';
$selected_color = $_GET['color'] ?? 'all';

$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$sizes = $conn->query("SELECT DISTINCT s.size FROM stock st INNER JOIN sizes s ON st.size_id = s.size_id ORDER BY s.size ASC");
$colors = $conn->query("SELECT DISTINCT c.color FROM stock st INNER JOIN colors c ON st.color_id = c.color_id ORDER BY c.color ASC");

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

if ($selected_category !== 'all') {
    $query .= " AND p.category_id = ? ";
    $params[] = $selected_category;
    $types .= "i";
}

if ($selected_size !== 'all') {
    $query .= " AND sz.size = ? ";
    $params[] = $selected_size;
    $types .= "s";
}

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
  width: 250px;
  background: linear-gradient(135deg, #fef2f4 0%, #f9e9ed 100%);
  border-right: 1px solid #f3dbe2;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
}
.sidebar a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0.75rem 1rem;
  border-radius: 10px;
  font-weight: 500;
  color: #4b5563;
  margin-bottom: 0.35rem;
}
.sidebar a:hover {
  background-color: #fef2f4;
  color: var(--rose-hover);
}
.sidebar-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 2rem;
}
.sidebar-logo {
  width: 40px;
  height: 40px;
}
.sidebar-title {
  font-size: 1.3rem;
  font-weight: 600;
  color: var(--rose);
}
.active-link {
  background-color: var(--rose);
  color: white !important;
}
.main-content {
  margin-left: 260px;
  padding: 2rem;
}
</style>
</head>
<body>

<!-- 🌸 Sidebar -->
<aside class="sidebar">
  <div>
    <div class="sidebar-header">
      <img src="logo.png" class="sidebar-logo" alt="Logo">
      <span class="sidebar-title">Seven Dwarfs</span>
    </div>

    <nav>
      <a href="cashier_pos.php">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 7V6a6 6 0 1 1 12 0v1"/><rect x="4" y="7" width="16" height="13" rx="3"/><path d="M9 11v2m6-2v2"/></svg>
        POS
      </a>

      <a href="cashier_transactions.php">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M3 10h18"/><path d="M7 15h2"/></svg>
        Transactions
      </a>

      <a href="cashier_inventory.php" class="active-link">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M3 7l9 5 9-5"/><path d="M12 12v5"/></svg>
        Inventory
      </a>
    </nav>
  </div>

  <div class="mt-auto border-t pt-4">
    <p class="text-sm text-gray-600 mb-2">
      Cashier: <span class="font-medium text-[var(--rose)]"><?= htmlspecialchars($cashier_name); ?></span>
    </p>
    <form action="logout.php" method="POST">
      <button class="w-full text-left text-red-500 hover:text-red-600 font-medium py-1">🚪 Logout</button>
    </form>
  </div>
</aside>

<!-- 🌸 Main Content -->
<div class="main-content">

  <!-- Header & Filters -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <h1 class="text-3xl font-semibold text-[var(--rose)] tracking-wide">
      Inventory Overview
    </h1>

  </div>

  <!-- Inventory Table -->
<div class="bg-white shadow rounded-xl p-6 border border-gray-200">

  <!-- 🔎 Live Search -->
  <div class="flex justify-between items-center mb-4">
    <input 
      id="searchInput"
      type="text"
      placeholder="Search products..."
      class="w-72 px-4 py-2 border border-gray-300 rounded-lg focus:ring-[var(--rose)] focus:outline-none"
    >
  </div>

    <?php if ($result->num_rows > 0): ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead class="bg-[var(--rose)] text-white">
            <tr>
              <th class="px-4 py-3 text-left font-medium">Image</th>
              <th class="px-4 py-3 text-left font-medium">Product Name</th>
              <th class="px-4 py-3 text-left font-medium">Category</th>
              <th class="px-4 py-3 text-left font-medium">Price</th>
              <th class="px-4 py-3 text-left font-medium">Supplier Price</th>
              <th class="px-4 py-3 text-center font-medium">Stocks</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <?php
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
              <td class="px-4 py-3">
                <img src="<?= htmlspecialchars($img); ?>" onerror="this.src='uploads/default.png';"
                  class="w-16 h-16 object-cover rounded-md shadow-sm" alt="Product">
              </td>

              <td class="px-4 py-3 font-medium text-gray-800">
                <?= htmlspecialchars($row['product_name']); ?>
              </td>

              <td class="px-4 py-3 text-gray-600">
                <?= htmlspecialchars($row['category_name']); ?>
              </td>

              <td class="px-4 py-3 text-[var(--rose)] font-semibold">
                ₱<?= number_format($row['price'], 2); ?>
              </td>

              <td class="px-4 py-3 text-gray-700">
                ₱<?= number_format($row['supplier_price'], 2); ?>
              </td>

              <td class="px-4 py-3 text-center 
                <?= ($row['total_stock'] <= 5) ? 'text-red-500 font-semibold' : 'text-gray-700'; ?>">
                <?= $row['total_stock'] ?? 0; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>

<div id="pagination" class="flex justify-center items-center gap-2 mt-6"></div>

      </div>
    <?php else: ?>
      <p class="text-gray-500 text-center py-10 text-lg">
        No products found for this filter.
      </p>
    <?php endif; ?>
  </div>
</div>
<script>

const rows = Array.from(document.querySelectorAll("tbody tr"));
const searchInput = document.getElementById("searchInput");
const pagination = document.getElementById("pagination");

let currentPage = 1;
const rowsPerPage = 10;

// Pagination Render
function renderPagination(totalRows) {
    pagination.innerHTML = "";
    const pageCount = Math.ceil(totalRows / rowsPerPage);

    if (pageCount <= 1) return;

    // Prev Button
    const prev = document.createElement("button");
    prev.textContent = "Prev";
    prev.className = "px-3 py-1 border rounded";
    prev.disabled = currentPage === 1;
    prev.onclick = () => { currentPage--; updateTable(); };
    pagination.appendChild(prev);

    // Page Numbers
    for (let i = 1; i <= pageCount; i++) {
        const btn = document.createElement("button");
        btn.textContent = i;
        btn.className = `px-3 py-1 border rounded ${i === currentPage ? 'bg-[var(--rose)] text-white' : ''}`;
        btn.onclick = () => { currentPage = i; updateTable(); };
        pagination.appendChild(btn);
    }

    // Next Button
    const next = document.createElement("button");
    next.textContent = "Next";
    next.className = "px-3 py-1 border rounded";
    next.disabled = currentPage === pageCount;
    next.onclick = () => { currentPage++; updateTable(); };
    pagination.appendChild(next);
}

// Update Table View
function updateTable() {
    const query = searchInput.value.toLowerCase().trim();

    const filtered = rows.filter(row =>
        row.textContent.toLowerCase().includes(query)
    );

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    rows.forEach(r => r.style.display = "none");

    filtered.slice(start, end).forEach(r => r.style.display = "");

    renderPagination(filtered.length);
}

// Search Input Event
searchInput.addEventListener("input", () => {
    currentPage = 1;
    updateTable();
});

// Initial Load
updateTable();
</script>

</body>
</html>
