<?php
require 'conn.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $order_id = intval($_POST['order_id'] ?? 0);

    if ($order_id <= 0) {
        die("⚠️ Invalid Order ID.");
    }

    $admin_id = $_SESSION['admin_id'] ?? null;

    // Find status ID for "Refunded"
    $status_sql = "SELECT order_status_id FROM order_status WHERE LOWER(order_status_name) = 'refunded' LIMIT 1";
    $status_res = $conn->query($status_sql);
    if (!$status_res || $status_res->num_rows === 0) {
        die("⚠️ 'Refunded' status not found in order_status table.");
    }
    $status_row = $status_res->fetch_assoc();
    $refunded_status_id = $status_row['order_status_id'];

    $conn->begin_transaction();

    try {
        // 🔹 Fetch all items from the order
        $items_sql = "
            SELECT oi.order_id, oi.stock_id, oi.qty, oi.price,
                   p.product_id, s.size_id, s.color_id
            FROM order_items oi
            INNER JOIN stock s ON oi.stock_id = s.stock_id
            INNER JOIN products p ON s.product_id = p.product_id
            WHERE oi.order_id = ?
        ";
        $stmt = $conn->prepare($items_sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $items = $stmt->get_result();
        $stmt->close();

        if ($items->num_rows === 0) {
            throw new Exception("No items found for Order ID {$order_id}");
        }

        // 🔹 Insert refund records + restock items
        while ($item = $items->fetch_assoc()) {
            $refund_amount_item = $item['qty'] * $item['price'];

            // Record refund
            $stmt = $conn->prepare("
                INSERT INTO refunds (order_id, product_id, stock_id, size_id, color_id, refund_amount, refunded_at, refunded_by)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->bind_param(
                "iiiiidi",
                $order_id,
                $item['product_id'],
                $item['stock_id'],
                $item['size_id'],
                $item['color_id'],
                $refund_amount_item,
                $admin_id
            );
            $stmt->execute();
            $stmt->close();

            // ✅ Restock refunded items in stock table
            $stmt = $conn->prepare("UPDATE stock SET current_qty = current_qty + ? WHERE stock_id = ?");
            $stmt->bind_param("ii", $item['qty'], $item['stock_id']);
            $stmt->execute();
            $stmt->close();

            // ✅ Insert into stock_in for tracking (refund restock)
            $stmt = $conn->prepare("
                INSERT INTO stock_in (stock_id, qty, source_type, source_id, created_at, created_by)
                VALUES (?, ?, 'refund', ?, NOW(), ?)
            ");
            $stmt->bind_param("iiii", $item['stock_id'], $item['qty'], $order_id, $admin_id);
            $stmt->execute();
            $stmt->close();
        }

        // 🔹 Update order status to "Refunded"
        $stmt = $conn->prepare("UPDATE orders SET order_status_id = ? WHERE order_id = ?");
        $stmt->bind_param("ii", $refunded_status_id, $order_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        echo "<script>alert('✅ Order {$order_id} refunded successfully!'); window.location.href='pointofsale.php';</script>";
    } catch (Exception $e) {
        $conn->rollback();
        die("❌ Refund failed: " . $e->getMessage());
    }
} else {
    die("⚠️ Invalid request.");
}
