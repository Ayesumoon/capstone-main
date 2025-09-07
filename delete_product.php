<?php
session_start();
require 'conn.php'; // DB connection

if (!isset($_GET['id'])) {
    die("No product ID provided.");
}

$product_id = intval($_GET['id']);

// First check if product exists
$check = $conn->prepare("SELECT product_id FROM products WHERE product_id = ?");
$check->bind_param("i", $product_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    die("Product not found.");
}
$check->close();

// Delete the product (related product_colors/product_sizes/stock rows
// will be deleted automatically if you added ON DELETE CASCADE)
$stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);

if ($stmt->execute()) {
    // Redirect to inventory page with success message
    header("Location: products.php?msg=Product+deleted+successfully");
    exit;
} else {
    echo "Error deleting product: " . $conn->error;
}

$stmt->close();
$conn->close();
?>
