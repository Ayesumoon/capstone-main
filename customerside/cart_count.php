<?php
session_start();
$cartCount = 0;

if (isset($_SESSION['cart'])) { // Inconsistent naming: cart vs. carts
  $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
}

echo json_encode(['cartCount' => $cartCount]);
?>