<?php
require 'conn.php';
session_start();

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    die("Invalid order ID");
}

// Fetch order
$order = $conn->query("SELECT o.order_id, o.date_ordered, o.total_amount, 
                              CONCAT(a.first_name, ' ', a.last_name) AS cashier
                        FROM orders o
                        LEFT JOIN adminusers a ON o.admin_id = a.admin_id
                        WHERE o.order_id = $order_id")->fetch_assoc();

// Fetch items
$items = $conn->query("SELECT oi.quantity, oi.price_id, oi.discount, p.product_name 
                       FROM order_items oi
                       JOIN products p ON oi.product_id = p.product_id
                       WHERE oi.order_id = $order_id");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    h2 { text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
    .total { font-weight: bold; }
    .center { text-align: center; }
  </style>
</head>
<body onload="window.print()">
  <h2>SevenDwarfs POS Receipt</h2>
  <p><strong>Order ID:</strong> <?= $order['order_id'] ?></p>
  <p><strong>Date:</strong> <?= $order['date_ordered'] ?></p>
  <p><strong>Cashier:</strong> <?= htmlspecialchars($order['cashier']) ?></p>

  <table>
    <thead>
      <tr>
        <th>Product</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Discount</th>
        <th>Total</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $items->fetch_assoc()): ?>
        <?php 
          $line_total = ($row['price_id'] * $row['quantity']) * (1 - $row['discount'] / 100);
        ?>
        <tr>
          <td><?= htmlspecialchars($row['product_name']) ?></td>
          <td><?= $row['quantity'] ?></td>
          <td>₱<?= number_format($row['price_id'], 2) ?></td>
          <td><?= $row['discount'] ?>%</td>
          <td>₱<?= number_format($line_total, 2) ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <p class="total">Total: ₱<?= number_format($order['total_amount'], 2) ?></p>
  <p class="center">Thank you for your purchase!</p>
</body>
</html>
