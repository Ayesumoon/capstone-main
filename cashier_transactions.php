<?php
session_start();
require 'conn.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ✅ Ensure cashier logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// ✅ Fetch cashier info
$cashierRes = $conn->prepare("SELECT first_name FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$cashierRow = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashierRow ? $cashierRow['first_name'] : 'Unknown Cashier';
$cashierRes->close();

// ✅ Determine selected filter (today, week, month)
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';

// ✅ Build query based on filter
switch ($filter) {
    case 'week':
        $dateCondition = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $label = "This Week";
        break;
    case 'month':
        $dateCondition = "YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
        $label = "This Month";
        break;
    default:
        $dateCondition = "DATE(o.created_at) = CURDATE()";
        $label = "Today";
}

// ✅ Fetch filtered transactions
$stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.total_amount,
        o.cash_given,
        o.changes,
        o.created_at,
        pm.payment_method_name,
        a.first_name AS cashier_name,
        COUNT(oi.product_id) AS total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    LEFT JOIN adminusers a ON o.admin_id = a.admin_id
    WHERE $dateCondition AND o.admin_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

// ✅ Compute total sales for selected filter
$totalStmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM orders o
    WHERE $dateCondition AND o.admin_id = ?
");
$totalStmt->bind_param("i", $admin_id);
$totalStmt->execute();
$totalSales = $totalStmt->get_result()->fetch_assoc()['total_sales'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cashier Transactions | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root { --rose:#d37689; --rose-hover:#b75f6f; --card-bg: #fff; --cart-bg: #f9e9ed; --shadow: 0 4px 24px rgba(211,118,137,0.08);}
body { background:#fef9fa; font-family:'Poppins',sans-serif; }
.sidebar {
  width: 250px;
  background: linear-gradient(135deg, #fef2f4 0%, #f9e9ed 100%);
  border-right: 1px solid #f3dbe2;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  padding: 1.5rem 1rem 1rem 1rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  border-top-right-radius: 24px;
  border-bottom-right-radius: 24px;
  z-index: 20;
}
.sidebar-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f3dbe2;
}
.sidebar-logo {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(211,118,137,0.10);
  background: #fff;
  object-fit: cover;
}
.sidebar-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--rose);
  letter-spacing: 0.03em;
}
.sidebar nav {
  margin-top: 1rem;
}
.sidebar a {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  padding: 0.7rem 1rem;
  border-radius: 10px;
  font-weight: 500;
  color: #4b5563;
  margin-bottom: 0.3rem;
  font-size: 1rem;
  transition: background 0.18s, color 0.18s;
  text-decoration: none;
}
.sidebar a .sidebar-icon {
  font-size: 1.2em;
  width: 1.5em;
  text-align: center;
}
.sidebar a:hover {
  background-color: #f9e9ed;
  color: var(--rose-hover);
}
.active-link {
  background: linear-gradient(90deg, var(--rose) 70%, #f9e9ed 100%);
  color: #fff !important;
  box-shadow: 0 2px 8px rgba(211,118,137,0.10);
}
.sidebar-footer {
  margin-top: auto;
  border-top: 1px solid #f3dbe2;
  padding-top: 1.2rem;
}
.sidebar-cashier {
  font-size: 0.98rem;
  color: #6b7280;
  margin-bottom: 0.7rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.sidebar-cashier .cashier-icon {
  color: var(--rose);
  font-size: 1.1em;
}
.sidebar-logout-btn {
  width: 100%;
  text-align: left;
  color: #e11d48;
  font-weight: 600;
  background: #fff;
  border: none;
  border-radius: 8px;
  padding: 0.7rem 1rem;
  margin-top: 0.5rem;
  cursor: pointer;
  transition: background 0.18s, color 0.18s;
}
.sidebar-logout-btn:hover {
  background: #f9e9ed;
  color: #b91c1c;
}
.main-content {
  margin-left:260px;
  padding:1.5rem;
}
</style>
</head>
<body class="text-gray-800">

<!-- 🌸 Sidebar -->
<aside class="sidebar">
  <div>
    <div class="sidebar-header">
      <img src="logo.png" class="sidebar-logo" alt="Logo">
      <span class="sidebar-title">Seven Dwarfs</span>
    </div>
    <nav>
      <a href="cashier_pos.php">
        <span class="sidebar-icon" aria-label="POS">
          <!-- Shopping Bag SVG -->
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <path d="M6 7V6a6 6 0 1 1 12 0v1" />
            <rect x="4" y="7" width="16" height="13" rx="3" />
            <path d="M9 11v2m6-2v2" />
          </svg>
        </span>
        POS
      </a>
      <a href="cashier_transactions.php" class="active-link">
        <span class="sidebar-icon" aria-label="Transactions">
          <!-- Credit Card SVG -->
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <rect x="3" y="7" width="18" height="10" rx="2" />
            <path d="M3 10h18" />
            <path d="M7 15h2" />
          </svg>
        </span>
        Transactions
      </a>
      <a href="cashier_inventory.php">
        <span class="sidebar-icon" aria-label="Inventory">
          <!-- Box SVG -->
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <rect x="3" y="7" width="18" height="10" rx="2" />
            <path d="M3 7l9 5 9-5" />
            <path d="M12 12v5" />
          </svg>
        </span>
        Inventory
      </a>
    </nav>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-cashier">
      <span class="cashier-icon">👤</span>
      Cashier: <span class="font-medium text-[var(--rose)]"><?= htmlspecialchars($cashier_name); ?></span>
    </div>
    <form action="logout.php" method="POST">
      <button class="sidebar-logout-btn">🚪 Logout</button>
    </form>
  </div>
</aside>

<!-- 🌸 Main Content -->
<div class="main-content">
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-semibold text-[var(--rose)]">Cashier Transactions (<?= $label; ?>)</h1>

    <!-- 🔽 Filter Dropdown -->
    <form method="GET" class="flex items-center space-x-2">
      <label for="filter" class="text-gray-600 font-medium">Filter:</label>
      <select name="filter" id="filter" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)]" onchange="this.form.submit()">
        <option value="today" <?= $filter === 'today' ? 'selected' : ''; ?>>Today</option>
        <option value="week" <?= $filter === 'week' ? 'selected' : ''; ?>>This Week</option>
        <option value="month" <?= $filter === 'month' ? 'selected' : ''; ?>>This Month</option>
      </select>
    </form>
  </div>

  <!-- 💰 Total Sales Summary -->
  <div class="bg-white rounded-lg shadow p-6 mb-6 text-center">
    <h2 class="font-semibold text-lg text-gray-700 mb-2">Total Sales (<?= $label; ?>)</h2>
    <p class="text-3xl font-bold text-[var(--rose)]">₱<?= number_format($totalSales, 2); ?></p>
  </div>

  <!-- 📋 Transactions Table -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="font-semibold text-lg mb-4"><?= $label; ?> Sales Summary</h2>

    <?php if ($result->num_rows > 0): ?>
    <table class="w-full text-sm text-left border-collapse">
      <thead class="bg-[var(--rose)] text-white">
        <tr>
          <th class="px-3 py-2">Order ID</th>
          <th class="px-3 py-2">Items</th>
          <th class="px-3 py-2">Total</th>
          <th class="px-3 py-2">Cash Given</th>
          <th class="px-3 py-2">Change</th>
          <th class="px-3 py-2">Payment</th>
          <th class="px-3 py-2">Time</th>
          <th class="px-3 py-2">Cashier</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr class="border-b hover:bg-pink-50 transition">
          <td class="px-3 py-2 font-medium">#<?= $row['order_id']; ?></td>
          <td class="px-3 py-2"><?= $row['total_items']; ?></td>
          <td class="px-3 py-2 text-[var(--rose)] font-semibold">₱<?= number_format($row['total_amount'], 2); ?></td>
          <td class="px-3 py-2">₱<?= number_format($row['cash_given'], 2); ?></td>
          <td class="px-3 py-2">₱<?= number_format($row['changes'], 2); ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($row['payment_method_name']); ?></td>
          <td class="px-3 py-2"><?= date('h:i A', strtotime($row['created_at'])); ?></td>
          <td class="px-3 py-2 text-gray-600"><?= htmlspecialchars($row['cashier_name']); ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
      <p class="text-gray-500">No transactions found for <?= strtolower($label); ?>.</p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>

<?php 
$stmt->close(); 
$totalStmt->close();
$conn->close(); 
?>
