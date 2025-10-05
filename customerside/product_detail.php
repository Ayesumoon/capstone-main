<?php
session_start();
require 'conn.php'; // your DB connection file

$isLoggedIn = isset($_SESSION['customer_id']); // Assuming 'customer_id' is stored in session upon login

// ✅ Redirect if no product_id
if (!isset($_GET['product_id'])) {
  header("Location: shop.php");
  exit();
}

$product_id = $_GET['product_id'];

// ✅ Fetch product details
$stmt = $conn->prepare("SELECT p.product_name, p.price_id, p.description, p.image_url, c.category_name 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.category_id 
                        WHERE p.product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
  echo "Product not found!";
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Product Details | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <style>
    body {
      padding-top: 4rem; /* prevent overlap with fixed navbar */
    }
  </style>
</head>

<body class="bg-pink-50 text-gray-800 font-sans" 
      x-data="{ showLogin: false, showSignup: false, cartCount: 0 }" 
      @keydown.escape.window="showLogin = false; showSignup = false">

<!-- ✅ Navbar -->
<nav class="bg-pink-100 shadow-md fixed top-0 left-0 w-full z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">

    <!-- Brand -->
    <div class="flex flex-1 items-center gap-4">
      <h1 class="text-2xl font-bold text-pink-600 whitespace-nowrap">Seven Dwarfs Boutique</h1>
      <form action="shop.php" method="get" class="flex flex-1 max-w-sm">
        <input type="text" name="search" placeholder="Search products..." 
               class="w-full px-3 py-2 border border-pink-300 rounded-md 
                      focus:outline-none focus:ring-2 focus:ring-pink-400 text-sm">
      </form>
    </div>

    <!-- Nav Links -->
    <ul class="flex flex-wrap justify-center space-x-4 text-sm md:text-base">
      <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
      <li><a href="shop.php" class="hover:text-pink-500 font-semibold">Shop</a></li>
    </ul>

    <!-- Right Section -->
    <div class="flex items-center gap-4 text-pink-600">

      <!-- Cart -->
      <a href="cart.php" class="hover:text-pink-500 relative" title="Cart">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" 
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" 
                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h11.1a1 1 0 001-.8l1.4-5.2H7zm0 0l-1-4H4" />
        </svg>
      </a>

      <!-- Profile / Login -->
      <div class="relative">
        <?php if ($isLoggedIn): ?>
        <div x-data="{ open: false }" class="relative">
          <button @click="open = !open" class="hover:text-pink-500" title="Profile">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-pink-600" 
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
          <div x-show="open" @click.away="open = false" 
               class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Profile</a>
            <a href="purchases.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Purchases</a>
            <form action="logout.php" method="POST">
              <button type="submit" 
                      class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-pink-100">Logout</button>
            </form>
          </div>
        </div>
        <?php else: ?>
        <button @click="showLogin = true" class="hover:text-pink-500" title="Login">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" 
               fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                  d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- ✅ Product Details -->
<div class="max-w-4xl mx-auto mt-10 bg-white shadow-lg rounded-lg p-8">
  <div class="flex flex-col md:flex-row gap-8">

    <!-- Product Image -->
    <div class="md:w-1/3">
      <img src="<?= htmlspecialchars($product['image_url']) ?>"
           alt="<?= htmlspecialchars($product['product_name']) ?>" 
           class="w-full h-full object-cover rounded-lg shadow">
    </div>

    <!-- Product Info -->
    <div class="flex-1">
      <h2 class="text-3xl font-semibold text-pink-600"><?= htmlspecialchars($product['product_name']) ?></h2>
      <p class="text-lg font-semibold text-pink-600 mt-2">₱<?= number_format($product['price_id'], 2) ?></p>
      <p class="text-gray-600 mt-4"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
      <p class="text-gray-500 mt-2">Category: <?= htmlspecialchars($product['category_name']) ?></p>

      <!-- Add to Cart -->
      <div class="mt-6">
        <form method="POST" action="add_to_cart.php" class="flex items-center gap-4">
          <input type="hidden" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>">
          <input type="hidden" name="price_id" value="<?= $product['price_id'] ?>">
          <input type="hidden" name="product_id" value="<?= $product_id ?>">
          <input type="number" name="quantity" value="1" min="1" 
                 class="border rounded px-2 w-20 text-center">
          <button type="submit" 
                  class="bg-pink-500 text-white px-6 py-3 rounded-full text-lg shadow-md hover:bg-pink-600 transition">
            Add to Cart
          </button>
        </form>
      </div>

      <!-- Back -->
      <div class="mt-6">
        <a href="shop.php" 
           class="bg-gray-200 text-gray-700 px-6 py-2 rounded-full shadow-md hover:bg-gray-300 transition">
          Back to Shop
        </a>
      </div>
    </div>
  </div>
</div>

<!-- 🔐 Login Modal -->
<div x-show="showLogin" x-transition x-cloak 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-md relative">
    <button @click="showLogin = false" 
            class="absolute top-3 right-3 text-gray-400 hover:text-pink-500 text-lg font-bold">&times;</button>
    <h2 class="text-lg font-semibold mb-4 text-pink-600">Login</h2>
    <form action="login_handler.php" method="POST">
      <input type="email" name="email" placeholder="Email" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="password" name="password" placeholder="Password" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <button type="submit" 
              class="w-full bg-pink-500 text-white py-2 rounded hover:bg-pink-600 transition">
        Log In
      </button>
    </form>
    <p class="text-sm text-center mt-4">
      Don't have an account? 
      <button @click="showLogin = false; showSignup = true" 
              class="text-pink-600 hover:underline">Sign up here</button>
    </p>
  </div>
</div>

<!-- 📝 Signup Modal -->
<div x-show="showSignup" x-transition x-cloak 
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-md relative" 
       x-data="{ password: '', confirmPassword: '', mismatch: false }">
    <button @click="showSignup = false" 
            class="absolute top-3 right-3 text-gray-400 hover:text-pink-500 text-lg font-bold">&times;</button>
    <h2 class="text-lg font-semibold mb-4 text-pink-600">Sign Up</h2>
    <form action="signup_handler.php" method="POST" 
          @submit.prevent="mismatch = password !== confirmPassword; if (!mismatch) $el.submit();">
      <input type="text" name="first_name" placeholder="First Name" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="text" name="last_name" placeholder="Last Name" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="email" name="email" placeholder="Email" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="text" name="phone" placeholder="Phone" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="text" name="address" placeholder="Address" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="password" name="password" placeholder="Password" x-model="password" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="password" name="confirm_password" placeholder="Confirm Password" x-model="confirmPassword" required 
             class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">

      <template x-if="mismatch">
        <p class="text-red-500 text-sm mb-3">Passwords do not match.</p>
      </template>

      <button type="submit" 
              class="w-full bg-pink-500 text-white py-2 rounded hover:bg-pink-600 transition">
        Sign Up
      </button>
    </form>
  </div>
</div>

</body>
</html>
