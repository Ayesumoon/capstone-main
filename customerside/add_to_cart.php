<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = $_POST['product_id'];
    $productName = $_POST['product_name'];
    $price = $_POST['price_id']; // Inconsistent naming: price vs. price_id
    $quantity = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 1;

    // Initialize cart if not already
    if (!isset($_SESSION['carts'])) { // Inconsistent naming: carts vs. cart
        $_SESSION['carts'] = [];
    }

    // Check if product is already in the cart
    if (isset($_SESSION['carts'][$productId])) {
        $_SESSION['carts'][$productId]['quantity'] += $quantity;
    } else {
        $_SESSION['carts'][$productId] = [
            'product_name' => $productName,
            'price_id' => $price, // Keep consistent: price_id
            'quantity' => $quantity
        ];
    }

    // Debugging line to see if the cart is updating correctly
    // var_dump($_SESSION['carts']);  // Remove in production

    // Redirect to cart page
    header('Location: cart.php');
    exit;
} else {
    // Invalid access
    header('Location: shop.php');
    exit;
}
?>