<?php
require 'conn.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_data = json_decode($_POST['cart_data'], true);
    $payment_method = intval($_POST['payment_method']);
    $cash_given = floatval($_POST['cash_given']);
    $admin_id = $_SESSION['admin_id'] ?? null;

    if (!$cart_data || count($cart_data) === 0) {
        die("Cart is empty.");
    }

    // Calculate total
    $total = 0;
    foreach ($cart_data as $item) {
        $total += $item['price'] * $item['qty'];
    }
    $change = $cash_given - $total;
    if ($change < 0) die("Insufficient cash.");

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert order (removed product_id!)
        $first_product_id = $cart_data[0]['id']; // first product in cart
        $stmt = $conn->prepare("INSERT INTO orders (admin_id, total_amount, cash_given, changes, order_status_id, created_at, product_id, payment_method_id) 
                                VALUES (?, ?, ?, ?, 0, NOW(), ?, ?)");
        $stmt->bind_param("idddii", $admin_id, $total, $cash_given, $change, $first_product_id, $payment_method);

        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // Insert items & update stock
        foreach ($cart_data as $item) {
            // Get stock_id
            $res = $conn->prepare("SELECT stock_id, current_qty FROM stock WHERE product_id = ? LIMIT 1");
            $res->bind_param("i", $item['id']);
            $res->execute();
            $stock = $res->get_result()->fetch_assoc();
            $res->close();

            if (!$stock || $stock['current_qty'] < $item['qty']) {
                throw new Exception("Not enough stock for " . $item['name']);
            }

            $stock_id = $stock['stock_id'];

            // Insert into order_items
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, stock_id, qty, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $order_id, $stock_id, $item['qty'], $item['price']);
            $stmt->execute();
            $stmt->close();

            // Update stock (deduct purchased qty)
            $new_qty = $stock['current_qty'] - $item['qty'];
            $stmt = $conn->prepare("UPDATE stock SET current_qty = ? WHERE stock_id = ?");
            $stmt->bind_param("ii", $new_qty, $stock_id);
            $stmt->execute();
            $stmt->close();

            // Update total stock in products table (deduct instead of overwrite)
            $stmt = $conn->prepare("UPDATE products SET stocks = stocks - ? WHERE product_id = ?");
            $stmt->bind_param("ii", $item['qty'], $item['id']);
            $stmt->execute();
            $stmt->close();

        }

        // Insert transaction
       $customer_id = null; // For walk-in customers

$stmt = $conn->prepare("
    INSERT INTO transactions (order_id, customer_id, payment_method_id, total, order_status_id, date_time) 
    VALUES (?, ?, ?, ?, 0, NOW())
");
$stmt->bind_param("iiid", $order_id, $customer_id, $payment_method, $total);

        $stmt->close();

        // Commit transaction
        $conn->commit();

        // Simple receipt
        // Receipt UI
echo '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Receipt</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    .receipt { max-width: 350px; margin: auto; border: 1px dashed #333; padding: 20px; }
    h1 { text-align: center; font-size: 20px; margin-bottom: 10px; }
    .center { text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    td { padding: 5px 0; }
    .total-row td { border-top: 1px dashed #333; font-weight: bold; }
    .footer { margin-top: 15px; text-align: center; font-size: 12px; }
    button { margin-top: 15px; padding: 8px 12px; background: #4CAF50; color: #fff; border: none; cursor: pointer; }
    button:hover { background: #45a049; }
  </style>
</head>
<body onload="window.print()">
  <div class="receipt">
    <h1>Seven Dwarfs POS</h1>
    <p class="center">Order ID: ' . $order_id . '</p>
    <hr>
    <table>
';

foreach ($cart_data as $item) {
    $line_total = $item['price'] * $item['qty'];
    echo "<tr>
            <td>" . htmlspecialchars($item['name']) . " x" . $item['qty'] . "</td>
            <td style='text-align:right'>₱" . number_format($line_total, 2) . "</td>
          </tr>";
}

echo '
      <tr class="total-row"><td>Total</td><td style="text-align:right">₱' . number_format($total, 2) . '</td></tr>
      <tr><td>Cash</td><td style="text-align:right">₱' . number_format($cash_given, 2) . '</td></tr>
      <tr><td>Change</td><td style="text-align:right">₱' . number_format($change, 2) . '</td></tr>
    </table>
    <div class="footer">
      <p>Thank you for shopping!</p>
      <p>Please come again</p>
    </div>
    <div class="center">
      <button onclick="window.print()">Print Again</button>
    </div>
  </div>
</body>
</html>
';


    } catch (Exception $e) {
        $conn->rollback();
        die("Error: " . $e->getMessage());
    }
}
?>
