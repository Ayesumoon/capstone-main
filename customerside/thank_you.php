<?php
session_start();
require 'conn.php';

// 🧠 Verify order ID and customer login
if (!isset($_GET['order_id']) || !isset($_SESSION['customer_id'])) {
    header("Location: shop.php");
    exit;
}

$order_id = intval($_GET['order_id']);
$customer_id = $_SESSION['customer_id'];

// 🧾 Fetch order details
$order_stmt = $conn->prepare("
    SELECT 
        o.order_id, 
        o.total_amount, 
        o.created_at, 
        os.order_status_name, 
        pm.payment_method_name
    FROM orders o
    JOIN order_status os ON o.order_status_id = os.order_status_id
    JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    WHERE o.order_id = ? AND o.customer_id = ?
");
$order_stmt->bind_param("ii", $order_id, $customer_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();
$order_stmt->close();

if (!$order) {
    echo "<script>alert('Order not found.'); window.location.href='shop.php';</script>";
    exit;
}

// 🛍 Fetch ordered products
$item_stmt = $conn->prepare("
    SELECT 
        oi.product_id, 
        oi.qty, 
        oi.price, 
        oi.color, 
        oi.size, 
        p.product_name, 
        p.image_url
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items = $item_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$item_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Thank You | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    :root {
      --rose: #d37689;
      --rose-hover: #b75f6f;
      --soft-bg: #fff7f8;
    }
    body {
      background-color: var(--soft-bg);
      font-family: 'Poppins', sans-serif;
    }
  </style>
</head>

<body class="text-gray-800">

<!-- ✅ Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose)]">Seven Dwarfs Boutique</h1>
    </div>
  </div>
</nav>

<!-- 🎉 Thank You Section -->
<main class="max-w-4xl mx-auto px-6 py-16 text-center">
  <div class="bg-white shadow-lg rounded-2xl p-10 border border-pink-100">
    <i class="fa-solid fa-circle-check text-[var(--rose)] text-5xl mb-6"></i>
    <h1 class="text-3xl font-bold text-[var(--rose)] mb-2">Thank You for Your Purchase!</h1>
    <p class="text-gray-600 mb-8">Your order has been placed successfully. You can track its progress in <strong>My Purchases</strong>.</p>

    <!-- 🧾 Order Details -->
    <div class="text-left bg-pink-50 p-6 rounded-xl mb-8 shadow-inner">
      <p><strong>Order Number:</strong> #<?= htmlspecialchars($order['order_id']); ?></p>
      <p><strong>Date:</strong> <?= date('F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
      <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method_name']); ?></p>
      <p><strong>Status:</strong> <?= htmlspecialchars($order['order_status_name']); ?></p>
      <p class="mt-2 font-semibold text-[var(--rose)]">Total: ₱<?= number_format($order['total_amount'], 2); ?></p>
    </div>

    <!-- 🛍 Ordered Items -->
    <h2 class="text-lg font-semibold text-gray-700 mb-3">Items in Your Order</h2>
    <div class="divide-y divide-gray-200 bg-white rounded-lg border border-gray-100">
      <?php foreach ($items as $item): ?>
      <div class="flex items-center justify-between p-4">
        <div class="flex items-center gap-4">
          <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="Product" class="w-16 h-16 rounded-lg border">
          <div>
            <p class="font-medium"><?= htmlspecialchars($item['product_name']); ?></p>
            <p class="text-gray-500 text-sm">
              Color: <?= htmlspecialchars($item['color']); ?> | 
              Size: <?= htmlspecialchars($item['size']); ?>
            </p>
            <p class="text-gray-500 text-sm">Qty: <?= $item['qty']; ?></p>
          </div>
        </div>
        <p class="text-gray-700 font-semibold">₱<?= number_format($item['price'] * $item['qty'], 2); ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- 🩷 Buttons -->
    <div class="mt-10 flex justify-center gap-6">
      <a href="purchases.php" 
         class="px-6 py-3 bg-[var(--rose)] text-white rounded-lg shadow hover:bg-[var(--rose-hover)] transition">
        View My Purchases
      </a>
      <a href="shop.php" 
         class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg shadow hover:bg-gray-300 transition">
        Continue Shopping
      </a>
    </div>
  </div>
</main>

<footer class="text-center text-gray-500 text-sm py-6 mt-12">
  © <?= date('Y') ?> Seven Dwarfs Boutique — All Rights Reserved.
</footer>

</body>
</html>
