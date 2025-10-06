<?php
session_start();
require 'conn.php';

// ✅ Ensure user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$isLoggedIn = true;

// ✅ Fetch customer info
$stmt = $conn->prepare("SELECT first_name, last_name, phone, address, profile_picture FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$avatar = $customer['profile_picture'] ?? 'default-avatar.png';

// ✅ Fetch cart items
$stmt = $conn->prepare("
  SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price_id, p.image_url
  FROM carts c
  JOIN products p ON c.product_id = p.product_id
  WHERE c.customer_id = ? AND c.cart_status = 'active'
");
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
$stmt->close();

$shippingFee = 69;
$totalPayment = $subtotal + $shippingFee;

// ✅ Get cart count for navbar
$stmt = $conn->prepare("SELECT SUM(quantity) AS count FROM carts WHERE customer_id = ? AND cart_status = 'active'");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$stmt->bind_result($cartCount);
$stmt->fetch();
$stmt->close();

// ✅ Handle place order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $payment_method_id = intval($_POST['payment_method_id'] ?? 1);

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

    // ✅ Clear cart after checkout
    $conn->query("UPDATE carts SET cart_status = 'checked_out' WHERE customer_id = $customer_id");

    header("Location: thank_you.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Checkout | Seven Dwarfs Boutique</title>
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
    }
  </style>
</head>
<body class="text-gray-800">

<!-- ✅ Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
    <!-- Logo -->
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose-muted)]">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Links -->
    <ul class="hidden md:flex space-x-5 text-sm font-medium">
      <li><a href="homepage.php" class="hover:text-[var(--rose-hover)]">Home</a></li>
      <li><a href="shop.php" class="text-[var(--rose-muted)] font-semibold">Shop</a></li>
      <li><a href="about.php" class="hover:text-[var(--rose-hover)]">About</a></li>
      <li><a href="contact.php" class="hover:text-[var(--rose-hover)]">Contact</a></li>
    </ul>

    <!-- Cart + Profile -->
    <div class="flex items-center gap-6">
      <!-- Cart -->
      <a href="cart.php" class="relative text-[var(--rose-muted)] hover:text-[var(--rose-hover)] transition">
        <i class="fa-solid fa-cart-shopping text-lg"></i>
        <?php if ($cartCount > 0): ?>
          <span class="absolute -top-2 -right-2 bg-[var(--rose-muted)] text-white text-xs font-semibold rounded-full px-2 py-0.5">
            <?= $cartCount; ?>
          </span>
        <?php endif; ?>
      </a>

      <!-- Profile -->
      <div class="relative">
        <img src="<?= htmlspecialchars($avatar) ?>" 
             class="w-8 h-8 rounded-full border cursor-pointer" 
             onclick="document.getElementById('profileDropdown').classList.toggle('hidden')">
        <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <a href="profile.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Profile</a>
          <a href="purchases.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Purchases</a>
          <form action="logout.php" method="POST">
            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-[var(--soft-bg)]">Logout</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- ✅ Checkout -->
<main class="max-w-6xl mx-auto mt-8 space-y-6 px-4">
  <form method="POST" class="space-y-6">

    <!-- Shipping Address -->
    <section class="bg-white rounded-xl border p-5 shadow-sm">
      <h2 class="font-semibold text-gray-800 text-base mb-2">Shipping Address</h2>
      <div class="flex items-center justify-between">
        <div>
          <p class="font-medium">
            <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
            <span class="ml-2"><?= htmlspecialchars($customer['phone']); ?></span>
          </p>
          <p class="text-gray-600"><?= htmlspecialchars($customer['address']); ?></p>
        </div>
        <a href="profile.php" class="text-[var(--rose-muted)] font-medium hover:underline">Change</a>
      </div>
    </section>

    <!-- Product List -->
    <section class="bg-white rounded-xl border shadow-sm">
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

    <!-- ✅ Dynamic Payment Methods -->
    <section class="bg-white rounded-xl border p-5 shadow-sm">
      <h2 class="font-semibold text-pink-800 text-base mb-3">Payment Method</h2>
      <div class="space-y-3">
        <?php
        if (isset($conn) && $conn instanceof mysqli && $conn->ping()) {
            $pm_query = $conn->query("SELECT payment_method_id, payment_method_name FROM payment_methods ORDER BY payment_method_id ASC");
            if ($pm_query && $pm_query->num_rows > 0):
                $first = true;
                while ($pm = $pm_query->fetch_assoc()):
        ?>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" 
                          name="payment_method_id" 
                          value="<?= htmlspecialchars($pm['payment_method_id']); ?>" 
                          <?= $first ? 'checked' : ''; ?>>
                    <span><?= htmlspecialchars($pm['payment_method_name']); ?></span>
                  </label>
        <?php 
                    $first = false;
                endwhile;
            else:
                echo '<p class="text-gray-500 text-sm">No available payment methods.</p>';
            endif;
        } else {
            echo '<p class="text-gray-500 text-sm">Database connection unavailable.</p>';
        }
        ?>
      </div>
    </section>

    <!-- Order Summary -->
    <section class="bg-white rounded-xl border p-5 shadow-sm">
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
        <span class="text-[var(--rose-muted)]">₱<?= number_format($totalPayment, 2); ?></span>
      </div>
    </section>

    <!-- Place Order Button -->
    <div class="flex justify-end">
      <button type="submit" name="place_order"
        class="px-10 py-3 bg-[var(--rose-muted)] text-white font-semibold rounded-lg hover:bg-[var(--rose-hover)] shadow transition">
        Place Order
      </button>
    </div>
  </form>
</main>

<footer class="text-center text-gray-500 text-sm py-6 mt-8">
  © <?= date('Y') ?> Seven Dwarfs Boutique. All rights reserved.
</footer>

<?php $conn->close(); ?>
</body>
</html>
