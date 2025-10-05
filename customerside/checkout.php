<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];

// ✅ Fetch customer info
$stmt = $conn->prepare("SELECT first_name, last_name, phone, address FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

// ✅ Fetch cart items
$sql = "
SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price_id, p.image_url
FROM carts c
JOIN products p ON c.product_id = p.product_id
WHERE c.customer_id = ? AND c.cart_status = 'active'
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$cartItems = [];
$subtotal = 0;
while ($row = $result->fetch_assoc()) {
    $row['total'] = $row['price_id'] * $row['quantity'];
    $subtotal += $row['total'];
    $cartItems[] = $row;
}

$shippingFee = 69;
$totalPayment = $subtotal + $shippingFee;

// ✅ Handle place order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $payment_method_id = intval($_POST['payment_method_id'] ?? 1); // default to COD

    foreach ($cartItems as $item) {
        $product_id = $item['product_id'];
        $quantity   = $item['quantity'];
        $total      = $item['total'];

        $stmt = $conn->prepare("
            INSERT INTO orders (customer_id, product_id, quantity, total_amount, order_status_id, created_at, payment_method_id)
            VALUES (?, ?, ?, ?, 1, NOW(), ?)
        ");
        $stmt->bind_param("iiidi", $customer_id, $product_id, $quantity, $total, $payment_method_id);
        $stmt->execute();
    }

    // ✅ Clear cart
    $conn->query("UPDATE carts SET cart_status = 'checked_out' WHERE customer_id = $customer_id");

    header("Location: thank_you.php");
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-pink-50 text-gray-800">

<!-- ✅ Navbar -->
<nav class="bg-pink-100 shadow-md">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">
    <!-- Logo -->
    <div class="flex items-center space-x-4">
      <img src="logo.png" alt="Logo" class="rounded-full w-12 h-12">
      <h1 class="text-2xl font-bold text-pink-600 whitespace-nowrap">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Links -->
    <ul class="flex flex-wrap justify-center space-x-4 text-sm md:text-base">
      <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
      <li><a href="shop.php" class="hover:text-pink-500">Shop</a></li>
      <li><a href="about.php" class="hover:text-pink-500">About</a></li>
      <li><a href="contact.php" class="hover:text-pink-500">Contact</a></li>
    </ul>

    <!-- Cart + Profile -->
    <div class="flex items-center gap-4">
      <a href="cart.php" class="hover:text-pink-500" title="Cart">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h11.1a1 1 0 001-.8l1.4-5.2H7z" />
        </svg>
      </a>
      <a href="profile.php" class="text-pink-600 hover:text-pink-500 font-medium">Profile</a>
    </div>
  </div>
</nav>

<!-- ✅ Checkout Content -->
<main class="max-w-6xl mx-auto mt-6 space-y-6">

  <!-- Shipping Address -->
  <section class="bg-white rounded border p-4">
    <h2 class="font-semibold text-gray-800 text-base mb-2">Shipping Address</h2>
    <div class="flex items-center justify-between">
      <div>
        <p class="font-medium">
          <?= htmlspecialchars($customer['first_name'] . " " . $customer['last_name']); ?>
          <span class="ml-2"><?= htmlspecialchars($customer['phone']); ?></span>
        </p>
        <p class="text-gray-600"><?= htmlspecialchars($customer['address']); ?></p>
      </div>
      <a href="profile.php" class="text-pink-500 font-medium hover:underline">Change</a>
    </div>
  </section>

  <!-- Product List -->
  <section class="bg-white rounded border">
    <div class="p-4 border-b font-semibold text-gray-800">Products Ordered</div>
    <div class="divide-y">
      <?php foreach ($cartItems as $item): ?>
      <div class="flex items-center justify-between p-4">
        <div class="flex items-center gap-3">
          <img src="<?= htmlspecialchars($item['image_url']); ?>" alt="Product" class="w-16 h-16 border rounded">
          <div>
            <p class="font-medium"><?= htmlspecialchars($item['product_name']); ?></p>
            <p class="text-gray-500">Qty: <?= $item['quantity']; ?></p>
          </div>
        </div>
        <p class="text-gray-800 font-medium">₱<?= number_format($item['total'], 2); ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

 <!-- PAYMENT METHOD -->
<section class="bg-white rounded border p-4">
  <h2 class="font-semibold text-pink-800 text-base mb-3">Payment Method</h2>
  <div class="space-y-2">
    <label class="flex items-center gap-2 cursor-pointer">
      <input type="radio" name="payment_method_id" value="1" checked>
      <span>Cash on Delivery</span>
    </label>
    <label class="flex items-center gap-2 cursor-pointer">
      <input type="radio" name="payment_method_id" value="2">
      <span>Credit / Debit Card</span>
    </label>
    <label class="flex items-center gap-2 cursor-pointer">
      <input type="radio" name="payment_method_id" value="3">
      <span>ShopeePay / E-Wallet</span>
    </label>
  </div>
</section>


    <!-- Order Summary -->
    <section class="bg-white rounded border p-4">
      <div class="flex justify-between py-2">
        <span>Merchandise Subtotal</span>
        <span>₱<?= number_format($subtotal, 2); ?></span>
      </div>
      <div class="flex justify-between py-2">
        <span>Shipping Fee</span>
        <span>₱<?= number_format($shippingFee, 2); ?></span>
      </div>
      <div class="flex justify-between py-2 font-semibold text-lg text-gray-800 border-t pt-3">
        <span>Total Payment:</span>
        <span class="text-pink-500">₱<?= number_format($totalPayment, 2); ?></span>
      </div>
    </section>

    <!-- Place Order -->
    <div class="flex justify-end">
      <button type="submit" name="place_order"
        class="px-10 py-3 bg-pink-500 text-white font-semibold rounded hover:bg-pink-600">
        Place Order
      </button>
    </div>
  </form>
</main>
</body>
</html>