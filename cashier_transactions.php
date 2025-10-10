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

// ✅ Fetch today's transactions (summary per order)
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
    WHERE DATE(o.created_at) = CURDATE() AND o.admin_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cashier Transactions | Seven Dwarfs Boutique</title>
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
<body class="text-gray-800">

<!-- 🌸 Sidebar -->
<aside class="sidebar">
  <div>
    <div class="flex items-center gap-3 mb-6">
      <img src="logo.png" class="w-10 h-10 rounded-full" alt="Logo">
      <h1 class="text-lg font-semibold text-[var(--rose)]">Seven Dwarfs</h1>
    </div>
    <nav>
      <a href="cashier_pos.php">🛍️ POS</a>
      <a href="cashier_transactions.php" class="active-link">💰 Transactions</a>
      <a href="inventory.php">📦 Inventory</a>
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
  <h1 class="text-2xl font-semibold text-[var(--rose)] mb-6">Cashier Transactions (Today)</h1>

  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="font-semibold text-lg mb-4">Today's Sales Summary</h2>

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
        <?php 
        $grandTotal = 0;
        while ($row = $result->fetch_assoc()):
          $grandTotal += $row['total_amount'];
        ?>
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

    <div class="text-right mt-4 font-semibold text-lg">
      Total Sales: <span class="text-[var(--rose)]">₱<?= number_format($grandTotal, 2); ?></span>
    </div>

    <?php else: ?>
      <p class="text-gray-500">No transactions yet for today.</p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>

<?php 
$stmt->close(); 
$conn->close(); 
?>