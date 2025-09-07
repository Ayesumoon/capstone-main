<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = $_POST['product_id'];

    if (isset($_SESSION['carts'][$productId])) {
        unset($_SESSION['carts'][$productId]);
    }
}

header('Location: cart.php');
exit;
