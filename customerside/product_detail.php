<?php
session_start();
require 'conn.php'; // Database connection

// Check if customer is logged in
$isLoggedIn = isset($_SESSION['customer_id']);
$customer_id = $_SESSION['customer_id'] ?? null;

// ✅ Redirect if no product_id
if (!isset($_GET['product_id'])) {
  header("Location: shop.php");
  exit();
}

$product_id = (int)$_GET['product_id'];

// ✅ Fetch product details
$stmt = $conn->prepare("
  SELECT p.product_name, p.price_id, p.description, p.image_url, c.category_name 
  FROM products p
  JOIN categories c ON p.category_id = c.category_id
  WHERE p.product_id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
  echo "Product not found!";
  exit();
}
$stmt->close();

// ✅ Fetch cart count if logged in
$cartCount = 0;
if ($isLoggedIn) {
  $stmt = $conn->prepare("SELECT SUM(quantity) AS total_items FROM carts WHERE customer_id = ?");
  $stmt->bind_param("i", $customer_id);
  $stmt->execute();
  $stmt->bind_result($cartCount);
  $stmt->fetch();
  $stmt->close();

  // Optional: fetch user avatar (if you have a column for it)
  $avatarQuery = $conn->prepare("SELECT profile_picture FROM customers WHERE customer_id = ?");
  $avatarQuery->bind_param("i", $customer_id);
  $avatarQuery->execute();
  $avatarResult = $avatarQuery->get_result()->fetch_assoc();
  $avatar = $avatarResult['profile_picture'] ?? 'default-avatar.png';
  $avatarQuery->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Product Details | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
      padding-top: 5rem;
    }
    [x-cloak] { display: none !important; }
  </style>
</head>

<body x-data="{ showLogin: false, showSignup: false, cartCount: 0 }" 
      @keydown.escape.window="showLogin = false; showSignup = false">

<!-- 🌸 Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm fixed top-0 left-0 w-full z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">

    <!-- Brand -->
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose-muted)]">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Search -->
    <form action="shop.php" method="get" class="flex-1 max-w-sm mx-4 w-full">
      <input type="text" name="search" placeholder="Search products..."
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none text-sm">
    </form>

    <!-- Nav + Icons -->
    <ul class="flex flex-wrap justify-center space-x-4 text-sm md:text-base font-medium text-gray-700">
      <li><a href="homepage.php" class="hover:text-rose-500">Home</a></li>
      <li><a href="shop.php" class="hover:text-rose-500">Shop</a></li>
      <li><a href="about.php" class="hover:text-rose-500">About</a></li>
      <li><a href="contact.php" class="hover:text-rose-500">Contact</a></li>
    </ul>


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
      <div class="relative" x-data="{ open: false }">
        <?php if ($isLoggedIn): ?>
          <img src="<?= htmlspecialchars($avatar) ?>" 
               class="w-8 h-8 rounded-full border cursor-pointer" 
               @click="open=!open">
          <div x-show="open" @click.away="open=false"
               class="absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <a href="profile.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Profile</a>
            <a href="purchases.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Purchases</a>
            <form action="logout.php" method="POST">
              <button type="submit" 
                class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-[var(--soft-bg)]">Logout</button>
            </form>
          </div>
        <?php else: ?>
          <button @click="showLogin=true" class="text-[var(--rose-muted)] hover:text-[var(--rose-hover)] transition">
            <i class="fa-solid fa-user text-lg"></i>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>


<!-- 🔐 Login Modal -->
<div x-show="showLogin" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative">
    <button @click="showLogin = false" 
            class="absolute top-4 right-4 text-gray-400 hover:text-[#d76d7a] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[#d76d7a]">Welcome Back</h2>
    <form action="login_handler.php" method="POST" class="space-y-4">
      <input type="email" name="email" placeholder="Email" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="password" name="password" placeholder="Password" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <button type="submit" class="btn-rose w-full py-3 rounded-lg font-medium">Log In</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Don’t have an account?
      <button @click="showLogin = false; showSignup = true" class="text-[#d76d7a] hover:underline font-medium">Sign up here</button>
    </p>
  </div>
</div>

<!-- 📝 Signup Modal -->
<div x-show="showSignup" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative"
       x-data="{ password: '', confirmPassword: '', mismatch: false }">
    <button @click="showSignup = false" 
            class="absolute top-4 right-4 text-gray-400 hover:text-[#d76d7a] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[#d76d7a]">Create Account</h2>
    <form action="signup_handler.php" method="POST" class="space-y-4"
          @submit.prevent="mismatch = password !== confirmPassword; if (!mismatch) $el.submit();">
      <div class="grid grid-cols-2 gap-4">
        <input type="text" name="first_name" placeholder="First Name" required 
               class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
        <input type="text" name="last_name" placeholder="Last Name" required 
               class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      </div>
      <input type="email" name="email" placeholder="Email" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="text" name="phone" placeholder="Phone" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="text" name="address" placeholder="Address" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="password" name="password" placeholder="Password" x-model="password" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="password" name="confirm_password" placeholder="Confirm Password" x-model="confirmPassword" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <template x-if="mismatch"><p class="text-red-500 text-sm -mt-2">⚠ Passwords do not match.</p></template>
      <button type="submit" class="btn-rose w-full py-3 rounded-lg font-medium">Sign Up</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Already have an account?
      <button @click="showSignup = false; showLogin = true" class="text-[#d76d7a] hover:underline font-medium">Log in here</button>
    </p>
  </div>
</div>

<!-- 🌷 Product Details -->
<div class="max-w-5xl mx-auto mt-10 bg-white rounded-2xl shadow-lg p-8">
  <div class="flex flex-col md:flex-row gap-10">

    <!-- Product Image -->
    <div class="md:w-1/3">
      <img src="<?= htmlspecialchars($product['image_url']) ?>"
           alt="<?= htmlspecialchars($product['product_name']) ?>" 
           class="w-full h-auto object-cover rounded-lg shadow-md">
    </div>

    <!-- Product Info -->
    <div class="flex-1">
      <h2 class="text-3xl font-semibold text-[var(--rose-muted)]"><?= htmlspecialchars($product['product_name']) ?></h2>
      <p class="text-lg font-bold text-[var(--rose-hover)] mt-2">₱<?= number_format($product['price_id'], 2) ?></p>
      <p class="text-gray-600 mt-4 leading-relaxed"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
      <p class="text-gray-500 mt-3 text-sm">Category: <?= htmlspecialchars($product['category_name']) ?></p>

      <!-- Add to Cart -->
      <form method="POST" action="add_to_cart.php" class="flex items-center gap-4 mt-6">
        <input type="hidden" name="product_id" value="<?= $product_id ?>">
        <input type="number" name="quantity" value="1" min="1" 
               class="border rounded-lg px-3 py-2 w-24 text-center focus:ring-2 focus:ring-[var(--rose-muted)]">
        <button type="submit" 
                class="bg-[var(--rose-muted)] text-white px-6 py-3 rounded-full text-sm shadow-md hover:bg-[var(--rose-hover)] transition">
          Add to Cart
        </button>
      </form>

      <!-- Back -->
      <div class="mt-6">
        <a href="shop.php" 
           class="bg-gray-100 text-gray-700 px-6 py-2 rounded-full shadow-sm hover:bg-gray-200 transition">
          ← Back to Shop
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 py-6 mt-10 text-center text-sm text-gray-600">
  © <?= date('Y') ?> Seven Dwarfs Boutique | All Rights Reserved.
</footer>

</body>
</html>
