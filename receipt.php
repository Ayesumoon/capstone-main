<?php
require 'conn.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    echo "Invalid receipt.";
    exit;
}


// 🧾 Fetch shop info
$shopName = "Seven Dwarfs Boutique";

// 🧍 Fetch cashier name
$cashierRes = $conn->prepare("SELECT first_name FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$cashier = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashier['first_name'] ?? 'Unknown';

// 📦 Fetch order details
$orderQuery = $conn->prepare("
    SELECT o.*, pm.payment_method_name
    FROM orders o
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    WHERE o.order_id = ?
");
$orderQuery->bind_param("i", $order_id);
$orderQuery->execute();
$order = $orderQuery->get_result()->fetch_assoc();

if (!$order) {
    echo "Receipt not found.";
    exit;
}

// 🛒 Fetch ordered items
$itemQuery = $conn->prepare("
    SELECT oi.qty, oi.price, p.product_name
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$itemQuery->bind_param("i", $order_id);
$itemQuery->execute();
$items = $itemQuery->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt #<?= $order_id; ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body {
  background: #fef9fa;
  font-family: 'Poppins', sans-serif;
}
.receipt {
  width: 320px;
  margin: 2rem auto;
  background: white;
  border: 1px solid #ddd;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.receipt h1 {
  text-align: center;
  color: #d37689;
  font-weight: 700;
  font-size: 1.3rem;
  margin-bottom: 0.5rem;
}
.receipt hr {
  border: none;
  border-top: 1px dashed #d4d4d4;
  margin: 0.75rem 0;
}
.receipt table {
  width: 100%;
  font-size: 0.9rem;
}
.receipt td {
  padding: 0.25rem 0;
}
.totals td {
  font-weight: 600;
}
@media print {
  button { display: none; }
  body { background: white; }
  .receipt { box-shadow: none; border: none; margin: 0; }
}
</style>
</head>
<body>

<div class="receipt">
  <h1><?= $shopName; ?></h1>
  <p class="text-center text-sm text-gray-500">Thank you for shopping with us!</p>
  <hr>

  <div class="text-sm">
    <p><strong>Receipt No:</strong> #<?= $order['order_id']; ?></p>
    <p><strong>Date:</strong> <?= date('M d, Y h:i A', strtotime($order['created_at'])); ?></p>
    <p><strong>Cashier:</strong> <?= htmlspecialchars($cashier_name); ?></p>
    <p><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method_name']); ?></p>
  </div>

  <hr>

  <table>
    <thead>
      <tr class="font-semibold text-gray-700 border-b">
        <td>Item</td>
        <td class="text-center">Qty</td>
        <td class="text-right">Price</td>
      </tr>
    </thead>
    <tbody>
      <?php 
      $total = 0;
      while ($row = $items->fetch_assoc()):
        $subtotal = $row['qty'] * $row['price'];
        $total += $subtotal;
      ?>
      <tr>
        <td><?= htmlspecialchars($row['product_name']); ?></td>
        <td class="text-center"><?= $row['qty']; ?></td>
        <td class="text-right">₱<?= number_format($subtotal, 2); ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <hr>

  <table class="totals">
    <tr><td>Total</td><td class="text-right">₱<?= number_format($order['total_amount'], 2); ?></td></tr>
    <tr><td>Cash</td><td class="text-right">₱<?= number_format($order['cash_given'], 2); ?></td></tr>
    <tr><td>Change</td><td class="text-right">₱<?= number_format($order['changes'], 2); ?></td></tr>
  </table>

  <hr>
  <p class="text-center text-xs text-gray-400 mt-2">This serves as your official receipt.</p>
</div>

<div class="flex justify-center mt-4">
  <button onclick="window.print()" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-md shadow">
    🖨️ Print Receipt
  </button>
  <!-- Back to POS link removed -->
</div>

<script>
  window.onload = function() {
    window.print();
  };
</script>
</body>
</html>
