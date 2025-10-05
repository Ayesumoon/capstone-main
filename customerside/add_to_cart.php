<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['customer_id'])) {
    echo "<script>alert('Please login to add items to cart'); window.location.href='login.php';</script>";
    exit;
}

$customer_id = $_SESSION['customer_id'];
$product_id = $_POST['product_id'];
$quantity = intval($_POST['quantity'] ?? 1);

// Check if this product is already in the user's cart
$stmt = $conn->prepare("SELECT cart_id, quantity FROM carts WHERE customer_id = ? AND product_id = ? AND cart_status = 'active'");
$stmt->bind_param("ii", $customer_id, $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Update quantity
    $newQty = $row['quantity'] + $quantity;
    $update = $conn->prepare("UPDATE carts SET quantity = ? WHERE cart_id = ?");
    $update->bind_param("ii", $newQty, $row['cart_id']);
    $update->execute();
} else {
    // Insert new item
    $insert = $conn->prepare("INSERT INTO carts (customer_id, product_id, quantity, cart_status, created_at) VALUES (?, ?, ?, 'active', NOW())");
    $insert->bind_param("iii", $customer_id, $product_id, $quantity);
    $insert->execute();
}

header("Location: cart.php");
exit;
