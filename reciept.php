<?php
require 'conn.php';
session_start();

$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    die("Invalid order ID");
}

// Fetch order
$order = $conn->query("
    SELECT o.order_id, o.created_at, o.total_amount, o.cash_given, o.changes,
           CONCAT(a.first_name, ' ', a.last_name) AS cashier,
           pm.payment_method_name
    FROM orders o
    LEFT JOIN adminusers a ON o.admin_id = a.admin_id
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    WHERE o.order_id = $order_id
")->fetch_assoc();

// Fetch items
$items = $conn->query("
    SELECT oi.qty, oi.price, p.product_name
    FROM order_items oi
    JOIN stock s ON oi.stock_id = s.stock_id
    JOIN products p ON s.product_id = p.product_id
    WHERE oi.order_id = $order_id
");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Receipt</title>
  <style>
    body {
      font-family: 'Courier New', monospace;
      padding: 20px;
      max-width: 300px;
      margin: auto;
      background: #fff;
    }
    h2, h3, p {
      text-align: center;
      margin: 4px 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    td {
      padding: 3px 0;
    }
    .totals td {
      font-weight: bold;
      border-top: 1px dashed #000;
      padding-top: 5px;
    }
    .footer {
      text-align: center;
      margin-top: 15px;
      font-size: 12px;
    }
    .line {
      border-top: 1px dashed #000;
      margin: 10px 0;
    }
  </style>
</head>
<body onload="window.print()">

  <h2>Seven Dwarfs</h2>
  <p>Official Receipt</p>
  <div class="line"></div>

  <p><strong>Order ID:</strong> <?= $order['order_id'] ?></p>
  <p><strong>Date:</strong> <?= $order['created_at'] ?></p>
  <p><strong>Cashier:</strong> <?= htmlspecialchars($order['cashier']) ?></p>
  <p><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method_name']) ?></p>

  <div class="line"></div>

  <table>
    <?php while ($row = $items->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['product_name']) ?> x<?= $row['qty'] ?></td>
        <td style="text-align:right;">₱<?= number_format($row['price'] * $row['qty'], 2) ?></td>
      </tr>
    <?php endwhile; ?>
    <tr class="totals">
      <td>Total</td>
      <td style="text-align:right;">₱<?= number_format($order['total_amount'], 2) ?></td>
    </tr>
    <tr>
      <td>Cash</td>
      <td style="text-align:right;">₱<?= number_format($order['cash_given'], 2) ?></td>
    </tr>
    <tr>
      <td>Change</td>
      <td style="text-align:right;">₱<?= number_format($order['changes'], 2) ?></td>
    </tr>
  </table>

  <div class="line"></div>

  <p class="footer">Thank you for shopping!<br/>Please come again.</p>

</body>
</html>
