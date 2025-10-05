<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$cart_id = $_POST['cart_id'] ?? null;
if ($cart_id) {
    $stmt = $conn->prepare("DELETE FROM carts WHERE cart_id = ? AND customer_id = ?");
    $stmt->bind_param("ii", $cart_id, $_SESSION['customer_id']);
    $stmt->execute();
}

header("Location: cart.php");
exit;
