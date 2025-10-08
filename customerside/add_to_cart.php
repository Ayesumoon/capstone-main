<?php
session_start();
require 'conn.php';

// 🧍 Check login
if (!isset($_SESSION['customer_id'])) {
    echo "<script>alert('Please login to add items to cart'); window.location.href='login.php';</script>";
    exit;
}

$customer_id = $_SESSION['customer_id'];
$product_id = $_POST['product_id'] ?? null;
$quantity    = intval($_POST['quantity'] ?? 1);
$color_id    = $_POST['color_id'] ?? null;
$size_id     = $_POST['size_id'] ?? null;

// ✅ Get color and size names (for easier display in cart)
$color = '';
$size = '';

if (!empty($color_id)) {
    $cstmt = $conn->prepare("SELECT color FROM colors WHERE color_id = ?");
    $cstmt->bind_param("i", $color_id);
    $cstmt->execute();
    $cstmt->bind_result($color);
    $cstmt->fetch();
    $cstmt->close();
}

if (!empty($size_id)) {
    $sstmt = $conn->prepare("SELECT size FROM sizes WHERE size_id = ?");
    $sstmt->bind_param("i", $size_id);
    $sstmt->execute();
    $sstmt->bind_result($size);
    $sstmt->fetch();
    $sstmt->close();
}

// ✅ Validate inputs
if ($quantity < 1 || empty($color) || empty($size)) {
    echo "<script>alert('Please choose color and size.'); history.back();</script>";
    exit;
}

// ✅ Check if item already exists in cart (same product + color + size)
$check = $conn->prepare("
    SELECT * FROM carts 
    WHERE customer_id = ? AND product_id = ? AND color = ? AND size = ? AND cart_status = 'active'
");
$check->bind_param("iiss", $customer_id, $product_id, $color, $size);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    // Update quantity if same variant already exists
    $update = $conn->prepare("
        UPDATE carts 
        SET quantity = quantity + ? 
        WHERE customer_id = ? AND product_id = ? AND color = ? AND size = ? AND cart_status = 'active'
    ");
    $update->bind_param("iiiss", $quantity, $customer_id, $product_id, $color, $size);
    $update->execute();
    $update->close();
} else {
    // Insert new
    $stmt = $conn->prepare("
        INSERT INTO carts (customer_id, product_id, quantity, color, size, cart_status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->bind_param("iiiss", $customer_id, $product_id, $quantity, $color, $size);
    $stmt->execute();
    $stmt->close();
}

echo "<script>alert('✅ Added to cart successfully!'); window.location.href='cart.php';</script>";
exit;
?>
