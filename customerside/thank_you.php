<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['customer_id'])) {
  header("Location: login.php");
  exit;
}

$customer_id = $_SESSION['customer_id'];

// ✅ Fetch the latest order of this customer
$sql = "
SELECT o.order_id, o.created_at, o.total_amount, o.payment_method_id, pm.payment_method_name AS payment_method
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

// ✅ Get order items (join directly with products)
$sqlItems = "
SELECT p.product_name, p.image_url, o.quantity AS qty, p.price_id AS price, (o.quantity * p.price_id) AS total
FROM orders o
JOIN products p ON o.product_id = p.product_id
WHERE o.order_id = ?
";
$stmt = $conn->prepare($sqlItems);
$stmt->bind_param("i", $order['order_id']);
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

  <style>
    :root {
      --rose-muted: #d37689;
      --rose-hover: #b75f6f;
      --soft-bg: #fef9fa;
    }
    body {
      background-color: var(--soft-bg);
      font-family: 'Poppins', sans-serif;
      color: #444;
    }
  </style>
</head>

<body class="text-gray-800">

<!-- 🌸 Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-6">
    
    <!-- Brand -->
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose-muted)]">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Links -->
    <ul class="hidden md:flex space-x-5 text-sm font-medium">
      <li><a href="homepage.php" class="hover:text-[var(--rose-muted)]">Home</a></li>
      <li><a href="shop.php" class="text-[var(--rose-muted)] font-semibold">Shop</a></li>
      <li><a href="about.php" class="hover:text-[var(--rose-muted)]">About</a></li>
      <li><a href="contact.php" class="hover:text-[var(--rose-muted)]">Contact</a></li>
    </ul>

    <!-- Icons -->
    <div class="flex items-center gap-6 text-[var(--rose-muted)]">
      <a href="cart.php" class="relative hover:text-[var(--rose-hover)] transition">
        <i class="fa-solid fa-cart-shopping text-lg"></i>
      </a>
      <a href="profile.php" class="hover:text-[var(--rose-hover)] transition">
        <i class="fa-solid fa-user text-lg"></i>
      </a>
    </div>
  </div>
</nav>

<!-- ✅ Main Content -->
<main class="max-w-5xl mx-auto px-6 py-12">
  <div class="bg-white rounded-2xl shadow-lg p-10 text-center">
    
    <!-- Header -->
    <div class="mb-8">
      <div class="text-5xl mb-4">🎉</div>
      <h2 class="text-3xl font-bold text-[var(--rose-muted)]">Thank You for Your Order!</h2>
      <p class="text-gray-600 mt-2">Your order has been placed successfully. We’ll notify you once it’s on the way!</p>
    </div>

    <!-- Order Info -->
    <div class="bg-[var(--soft-bg)] border border-gray-200 rounded-xl p-6 text-left mb-8">
      <h3 class="text-lg font-semibold text-[var(--rose-muted)] mb-3">Order Summary</h3>
      <p><strong>Product Name:</strong> 
        <?php 
          $productNames = [];
          mysqli_data_seek($items, 0); 
          while ($r = $items->fetch_assoc()) { 
            $productNames[] = htmlspecialchars($r['product_name']); 
          }
          echo implode(', ', $productNames);
          mysqli_data_seek($items, 0);
        ?>
      </p>
      <p><strong>Date Ordered:</strong> <?= date("F j, Y, g:i a", strtotime($order['created_at'])); ?></p>
      <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']); ?></p>
    </div>

    <!-- Product List -->
    <div class="overflow-hidden rounded-xl border border-gray-200 mb-6">
      <table class="w-full text-left">
        <thead class="bg-[var(--soft-bg)]">
          <tr class="text-gray-700">
            <th class="p-4 font-semibold">Product</th>
            <th class="p-4 font-semibold">Price</th>
            <th class="p-4 font-semibold">Qty</th>
            <th class="p-4 font-semibold">Total</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <?php 
          $grandTotal = 0;
          mysqli_data_seek($items, 0);
          while ($item = $items->fetch_assoc()):
            $grandTotal += $item['total'];
          ?>
            <tr class="hover:bg-[var(--soft-bg)] transition">
              <td class="p-4 flex items-center gap-3">
                <img src="<?= $item['image_url'] ?: 'assets/no-image.png' ?>" class="w-12 h-12 rounded object-cover shadow">
                <?= htmlspecialchars($item['product_name']); ?>
              </td>
              <td class="p-4">₱<?= number_format($item['price'], 2); ?></td>
              <td class="p-4"><?= $item['qty']; ?></td>
              <td class="p-4 text-[var(--rose-muted)] font-semibold">₱<?= number_format($item['total'], 2); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <!-- Grand Total -->
    <div class="text-right text-lg font-semibold">
      <p>Total: <span class="text-[var(--rose-muted)]">₱<?= number_format($grandTotal, 2); ?></span></p>
    </div>

    <!-- Button -->
    <div class="mt-8">
      <a href="shop.php" 
         class="inline-block bg-[var(--rose-muted)] text-white px-8 py-3 rounded-full font-medium shadow-md hover:bg-[var(--rose-hover)] transition">
        Continue Shopping
      </a>
    </div>

  </div>
</main>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 py-6 mt-10 text-center text-sm text-gray-600">
  © <?= date('Y') ?> Seven Dwarfs Boutique | All Rights Reserved.
</footer>

</body>
</html>
