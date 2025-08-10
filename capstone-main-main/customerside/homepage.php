<?php
$conn = new mysqli("localhost", "root", "", "dbms");

$isLoggedIn = isset($_SESSION['customer_id']);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$products = mysqli_query($conn, "SELECT * FROM products"); // adjust table name if needed

$sql = "SELECT product_name, description, price_id AS price, image_url 
        FROM products 
        WHERE stocks > 0 
        ORDER BY product_id DESC 
        LIMIT 6";

$result = $conn->query($sql);
?>
<?php
session_start();
$isLoggedIn = isset($_SESSION['customer_id']); // Adjust if you're using a different session key
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
      <li><a href="homepage.php" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-full transition">Home</a></li>
      <li><a href="shop.php" class="hover:text-pink-500">Shop</a></li>
      <li><a href="about.php" class="hover:text-pink-500">About</a></li>
      <li><a href="contact.php" class="hover:text-pink-500">Contact</a></li>
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

<!-- Hero Section -->
<section class="bg-pink-200 text-center py-20">
  <h2 class="text-4xl font-bold text-pink-800 mb-4">Welcome to Seven Dwarfs Boutique</h2>
  <p class="text-lg text-pink-900 mb-6">Where fashion meets fairytales ✨</p>
  <a href="shop.php" class="bg-pink-600 text-white px-6 py-3 rounded-full hover:bg-pink-700 transition">Shop Now</a>
</section>

<!-- Background Section -->
<section class="relative w-full h-screen md:h-full">
  <img src="storebg.png" alt="Store Background" class="w-full h-full object-cover">
</section>


<!-- Products Section -->
<section class="max-w-7xl mx-auto px-4 py-16">
  <h3 class="text-3xl font-bold text-center text-pink-700 mb-12">Featured Products</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
    <?php while($product = mysqli_fetch_assoc($products)) : ?>
      <div class="bg-white rounded-xl shadow-md p-4 transition-transform transform hover:scale-105 hover:shadow-xl">
        <img 
          src="<?= htmlspecialchars($product['image_url']) ?>"
          alt="<?= htmlspecialchars($product['product_name']) ?>" 
          class="rounded-md mb-4 w-full h-full object-cover hover:scale-110 transition-transform duration-300 ease-in-out"
        >
        <h4 class="text-xl font-semibold text-pink-800"><?= htmlspecialchars($product['product_name']) ?></h4>
        <p class="text-sm text-gray-600"><?= htmlspecialchars($product['description']) ?></p>
      </div>
    <?php endwhile; ?>
  </div>
</section>




<?php $conn->close(); ?>

<!-- Footer -->
<footer class="bg-pink-100 text-center py-6">
  <p class="text-pink-700">&copy; 2025 Seven Dwarfs Boutique. All rights reserved.</p>
</footer>

</body>
</html>
