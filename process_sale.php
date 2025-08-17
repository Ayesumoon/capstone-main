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

    // Insert order
    $stmt = $conn->prepare("INSERT INTO orders (admin_id, total_amount, cash_given, changes, order_status_id, created_at, product_id, payment_method_id) VALUES (?, ?, ?, ?, 0, NOW(), 0, ?)");
    $stmt->bind_param("idddi", $admin_id, $total, $cash_given, $change, $payment_method);
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
            die("Not enough stock for " . $item['name']);
        }

        $stock_id = $stock['stock_id'];

        // Insert into order_items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, stock_id, qty, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $order_id, $stock_id, $item['qty'], $item['price']);
        $stmt->execute();
        $stmt->close();

        // Update stock
        $new_qty = $stock['current_qty'] - $item['qty'];
        $stmt = $conn->prepare("UPDATE stock SET current_qty = ? WHERE stock_id = ?");
        $stmt->bind_param("ii", $new_qty, $stock_id);
        $stmt->execute();
        $stmt->close();

        // Also update products.stocks
        $stmt = $conn->prepare("UPDATE products SET stocks = ? WHERE product_id = ?");
        $stmt->bind_param("ii", $new_qty, $item['id']);
        $stmt->execute();
        $stmt->close();
    }

    // Insert transaction
    $stmt = $conn->prepare("INSERT INTO transactions (order_id, customer_id, payment_method_id, total, order_status_id, date_time) VALUES (?, 0, ?, ?, 0, NOW())");
    $stmt->bind_param("iid", $order_id, $payment_method, $total);
    $stmt->execute();
    $stmt->close();

    // Simple receipt
    echo "<h1>Receipt</h1>";
    echo "Order ID: $order_id<br>";
    foreach ($cart_data as $item) {
        echo $item['name'] . " x" . $item['qty'] . " = ₱" . ($item['price']*$item['qty']) . "<br>";
    }
    echo "Total: ₱$total<br>";
    echo "Cash: ₱$cash_given<br>";
    echo "Change: ₱$change<br>";
    echo "<br><button onclick='window.print()'>Print Receipt</button>";
}
?>
