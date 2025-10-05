<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// ✅ Get the latest order for this customer
$sql = "
SELECT o.order_id, o.created_at, o.total_amount, o.payment_method_id, pm.payment_method
FROM orders o
JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
WHERE o.customer_id = ?
ORDER BY o.created_at DESC
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo "No recent order found.";
    exit;
}

// ✅ Get ordered products for this order (assuming 1 row per product in `orders`)
$sqlItems = "
SELECT o.product_id, o.quantity, o.total_amount, p.product_name, p.image_url, p.price_id
FROM orders o
JOIN products p ON o.product_id = p.product_id
WHERE o.customer_id = ? AND o.created_at = ?
";
$stmt = $conn->prepare($sqlItems);
$stmt->bind_param("is", $customer_id, $order['created_at']);
$stmt->execute();
$items = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Thank You | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-pink-50 text-gray-800">

<!-- ✅ Navbar (same as homepage) -->
<nav class="bg-white shadow sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <!-- Logo -->
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="rounded-full w-12 h-12">
      <h1 class="text-xl md:text-2xl font-bold text-pink-600">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Links -->
    <ul class="hidden md:flex gap-6 text-sm font-medium">
      <li><a href="homepage.php" class="hover:text-pink-600">Home</a></li>
      <li><a href="shop.php" class="hover:text-pink-600">Shop</a></li>
      <li><a href="about.php" class="hover:text-pink-600">About</a></li>
      <li><a href="contact.php" class="hover:text-pink-600">Contact</a></li>
    </ul>

    <!-- Profile / Cart -->
    <div class="flex items-center gap-6 text-pink-600">
      <a href="cart.php" class="hover:text-pink-500" title="Cart">🛒</a>
      <a href="profile.php" class="hover:text-pink-500">Profile</a>
    </div>
  </div>
</nav>

<!-- ✅ Main Content -->
<main class="max-w-5xl mx-auto px-4 py-12">
  <div class="bg-white shadow-lg rounded-xl p-8 text-center">
    <h2 class="text-3xl font-bold text-pink-600 mb-4">🎉 Thank You for Your Order!</h2>
    <p class="text-gray-600 mb-6">Your order has been placed successfully. We’ll notify you once it’s on the way!</p>

    <!-- Order Details -->
    <div class="text-left">
      <h3 class="text-xl font-semibold text-pink-600 mb-3">Order Summary</h3>
      <p><strong>Order ID:</strong> <?= $order['order_id']; ?></p>
      <p><strong>Date:</strong> <?= date("F j, Y, g:i a", strtotime($order['created_at'])); ?></p>
      <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']); ?></p>
    </div>

    <!-- Product List -->
    <div class="mt-6">
      <table class="w-full text-left border rounded-lg overflow-hidden">
        <thead class="bg-pink-100">
          <tr>
            <th class="p-3">Product</th>
            <th class="p-3">Price</th>
            <th class="p-3">Qty</th>
            <th class="p-3">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $grandTotal = 0;
          while ($item = $items->fetch_assoc()): 
              $grandTotal += $item['total_amount'];
          ?>
            <tr class="border-b">
              <td class="p-3 flex items-center gap-3">
                <img src="<?= $item['image_url'] ?: 'assets/no-image.png' ?>" class="w-12 h-12 rounded object-cover">
                <?= htmlspecialchars($item['product_name']); ?>
              </td>
              <td class="p-3">₱<?= number_format($item['price_id'], 2); ?></td>
              <td class="p-3"><?= $item['quantity']; ?></td>
              <td class="p-3 text-pink-600 font-semibold">₱<?= number_format($item['total_amount'], 2); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- Grand Total -->
    <div class="mt-6 text-right">
      <p class="text-lg font-bold">Grand Total: <span class="text-pink-600">₱<?= number_format($grandTotal, 2); ?></span></p>
    </div>

    <!-- Back to Shop -->
    <div class="mt-8">
      <a href="shop.php" class="bg-pink-500 text-white px-6 py-3 rounded-lg hover:bg-pink-600 transition">
        Continue Shopping
      </a>
    </div>
  </div>
</main>

</body>
</html>
