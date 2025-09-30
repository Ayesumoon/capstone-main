<?php
// ✅ Start session and define login status
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Default: user not logged in
$isLoggedIn = false;

// Check if a customer is logged in
if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
    $isLoggedIn = true;
}
?> 

<!DOCTYPE html>
<html lang="en" x-data="{ profileOpen: false, showLogin: false, showSignup: false }" @keydown.escape.window="showLogin = false; showSignup = false">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <style>
    [x-cloak] { display: none !important; }
  </style>
</head>
<body class="bg-pink-50 text-gray-800">

<!-- Navbar -->
<nav class="bg-pink-100 shadow-md">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">

    <!-- Hamburger (if needed for mobile, placeholder here) -->
    <div class="w-10 h-10 flex flex-col justify-center space-y-1.5 md:hidden">
      <span class="block h-[2px] w-full bg-black rounded"></span>
      <span class="block h-[2px] w-full bg-black rounded"></span>
      <span class="block h-[2px] w-full bg-black rounded"></span>
    </div>

    <!-- Left side: Logo and Brand -->
    <div class="flex items-center space-x-4">
      <img src="logo.png" alt="User profile picture" class="rounded-full" width="60" height="50">
    </div>

    <!-- Center: Logo + Search -->
    <div class="flex flex-1 items-center gap-4">
      <h1 class="text-2xl font-bold text-pink-600 whitespace-nowrap">Seven Dwarfs Boutique</h1>
      <form action="shop.php" method="get" class="flex flex-1 max-w-sm">
        <input type="text" name="search" placeholder="Search products..." 
              class="w-full px-3 py-2 border border-pink-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-400 text-sm">
      </form>
    </div>

    <!-- Navigation Links -->
    <ul class="flex flex-wrap justify-center space-x-4 text-sm md:text-base">
      <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
      <li><a href="shop.php" class="hover:text-pink-500">Shop</a></li>
      <li><a href="about" class="hover:text-pink-500">About</a></li>
      <li><a href="contact" class="hover:text-pink-500">Contact</a></li>
    </ul>

    <!-- Icons -->
    <div class="flex items-center gap-4">
      <!-- Cart Icon -->
      <a href="cart.php" class="hover:text-pink-500" title="Cart">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h11.1a1 1 0 001-.8l1.4-5.2H7zm0 0l-1-4H4" />
        </svg>
      </a>

      <!-- Profile -->
      <div class="relative">
  <?php if ($isLoggedIn): ?>
  <div x-data="{ open: false }" class="relative">
    <button @click="open = !open" class="hover:text-pink-500" title="Profile">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
    </button>
    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
      <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Profile</a>
      <a href="purchases.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Purchases</a>
      <form action="logout.php" method="POST">
        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-pink-100">Logout</button>
      </form>
    </div>
  </div>
  <?php else: ?>
  <button @click="showLogin = true" class="hover:text-pink-500" title="Profile">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  </button>
  <?php endif; ?>
</div>

  </div>
</nav>

<!-- Login Modal -->
<div x-show="showLogin" x-transition x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-md relative">
    <button @click="showLogin = false" class="absolute top-3 right-3 text-gray-400 hover:text-pink-500 text-lg font-bold">&times;</button>
    <h2 class="text-lg font-semibold mb-4 text-pink-600">Login</h2>
    <form action="login_handler.php" method="POST">
      <input type="email" name="email" placeholder="Email" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="password" name="password" placeholder="Password" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <button type="submit" class="w-full bg-pink-500 text-white py-2 rounded hover:bg-pink-600 transition">Log In</button>
    </form>
    <p class="text-sm text-center mt-4">
      Don't have an account? 
      <button @click="showLogin = false; showSignup = true" class="text-pink-600 hover:underline">Sign up here</button>
    </p>
  </div>
</div>

<!-- Signup Modal -->
<div x-show="showSignup" x-transition x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-md relative" x-data="{ password: '', confirmPassword: '', mismatch: false }">
    <button @click="showSignup = false" class="absolute top-3 right-3 text-gray-400 hover:text-pink-500 text-lg font-bold">&times;</button>
    <h2 class="text-lg font-semibold mb-4 text-pink-600">Sign Up</h2>
    <form action="signup_handler.php" method="POST" @submit.prevent="mismatch = password !== confirmPassword; if (!mismatch) $el.submit();">
      <input type="text" name="first_name" placeholder="First Name" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="text" name="last_name" placeholder="Last Name" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="email" name="email" placeholder="Email" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="text" name="phone" placeholder="Phone" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="text" name="address" placeholder="Address" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="password" name="password" placeholder="Password" x-model="password" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">
      <input type="password" name="confirm_password" placeholder="Confirm Password" x-model="confirmPassword" required class="w-full border border-gray-300 p-2 rounded mb-3 focus:ring-2 focus:ring-pink-400">

      <template x-if="mismatch">
        <p class="text-red-500 text-sm mb-3">Passwords do not match.</p>
      </template>

      <button type="submit" class="w-full bg-pink-500 text-white py-2 rounded hover:bg-pink-600 transition">Sign Up</button>
    </form>
  </div>
</div>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Seven Dwarfs Boutique Checkout</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-sm">

  <!-- HEADER -->
  <header class="bg-white shadow p-4 flex items-center">
    <img src="logo.png" alt="Seven Dwarfs Boutique" class="h-8 mr-2">
    <h1 class="text-xl font-semibold text-gray-800">Checkout</h1>
  </header>

  <main class="max-w-6xl mx-auto mt-6 space-y-6">

    <!-- SHIPPING ADDRESS -->
    <section class="bg-white rounded border p-4">
      <h2 class="font-semibold text-gray-800 text-base mb-2">Shipping Address</h2>
      <div class="flex items-center justify-between">
        <div>
          <p class="font-medium">Juan Dela Cruz <span class="ml-2">09123456789</span></p>
          <p class="text-gray-600">123 Main St, Brgy. Central, Quezon City, Metro Manila</p>
        </div>
        <button class="text-pink-500 font-medium hover:underline">Change</button>
      </div>
    </section>

    <!-- PRODUCT LIST -->
    <section class="bg-white rounded border">
      <div class="p-4 border-b font-semibold text-gray-800">Products Ordered</div>
      <div class="divide-y">
        <!-- ITEM -->
        <div class="flex items-center justify-between p-4">
          <div class="flex items-center gap-3">
            <img src="https://via.placeholder.com/60" alt="Product" class="w-16 h-16 border rounded">
            <div>
              <p class="font-medium">Floral Dress</p>
              <p class="text-gray-500">Qty: 1</p>
            </div>
          </div>
          <p class="text-gray-800 font-medium">₱799.00</p>
        </div>
        <!-- ITEM -->
        <div class="flex items-center justify-between p-4">
          <div class="flex items-center gap-3">
            <img src="https://via.placeholder.com/60" alt="Product" class="w-16 h-16 border rounded">
            <div>
              <p class="font-medium">Casual Sneakers</p>
              <p class="text-gray-500">Qty: 1</p>
            </div>
          </div>
          <p class="text-gray-800 font-medium">₱1299.00</p>
        </div>
      </div>
    </section>

    <!-- PAYMENT METHOD -->
    <section class="bg-white rounded border p-4">
      <h2 class="font-semibold text-pink-800 text-base mb-3">Payment Method</h2>
      <div class="space-y-2">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" name="payment" checked>
          <span>Cash on Delivery</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" name="payment">
          <span>Credit / Debit Card</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="radio" name="payment">
          <span>ShopeePay / E-Wallet</span>
        </label>
      </div>
    </section>

    <!-- ORDER SUMMARY -->
    <section class="bg-white rounded border p-4">
      <div class="flex justify-between py-2">
        <span>Merchandise Subtotal</span>
        <span>₱2098.00</span>
      </div>
      <div class="flex justify-between py-2">
        <span>Shipping Fee</span>
        <span>₱69.00</span>
      </div>
      <div class="flex justify-between py-2 font-semibold text-lg text-gray-800 border-t pt-3">
        <span>Total Payment:</span>
        <span class="text-pink-500">₱2167.00</span>
      </div>
    </section>

    <!-- PLACE ORDER -->
    <div class="flex justify-end">
      <button class="px-10 py-3 bg-pink-500 text-white font-semibold rounded hover:bg-pink-600">
        Place Order
      </button>
    </div>
  </main>
</body>
</html>
