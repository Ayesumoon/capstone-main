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
<html lang="en" x-data="{ profileOpen: false, showLogin: false, showSignup: false }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seven Dwarfs Boutique | Whimsical Fairy Tale Fashion</title>

  <!-- Tailwind + Alpine + Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --rose-muted: #d37689;
      --rose-hover: #b75f6f;
      --blush-bg: #faf7f8;
      --section-bg: #f7f1f3;
      --text-gray: #444;
    }

    body {
      background-color: var(--blush-bg);
      color: var(--text-gray);
      font-family: 'Poppins', sans-serif;
    }

    .header-font {
      font-family: 'Playfair Display', serif;
    }

    .btn-rose {
      background-color: var(--rose-muted);
      color: white;
      transition: background-color 0.2s ease;
    }

    .btn-rose:hover {
      background-color: var(--rose-hover);
    }

    .nav-link:hover {
      color: var(--rose-muted);
      transform: translateY(-1px);
    }

    .product-card {
      transition: all 0.3s ease;
    }

    .product-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.08);
    }
  </style>
</head>

<body class="text-gray-800">

<!-- 🌸 Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">
    <!-- Logo -->
    <div class="flex items-center space-x-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-rose-700">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Links -->
    <ul class="hidden md:flex space-x-5 font-medium text-sm">
      <li><a href="homepage.php" class="nav-link">Home</a></li>
      <li><a href="shop.php" class="nav-link">Shop</a></li>
      <li><a href="about.php" class="nav-link">About</a></li>
      <li><a href="contact.php" class="nav-link">Contact</a></li>
    </ul>

    <!-- Icons -->
    <div class="flex items-center space-x-4">
      <a href="cart.php" class="text-rose-700 hover:text-rose-600">
        <i class="fas fa-shopping-cart text-lg"></i>
      </a>
      <button @click="showLogin = true" class="text-rose-700 hover:text-rose-600">
        <i class="fas fa-user-circle text-xl"></i>
      </button>
    </div>
  </div>
</nav>

<!-- 🔐 Login Modal -->
<div x-show="showLogin" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative">
    <button @click="showLogin = false" class="absolute top-4 right-4 text-gray-400 hover:text-[var(--rose-muted)] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[var(--rose-muted)]">Welcome Back</h2>
    <form action="login_handler.php" method="POST" class="space-y-4">
      <input type="email" name="email" placeholder="Email" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      <input type="password" name="password" placeholder="Password" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      <button type="submit" class="btn-rose w-full py-3 rounded-lg font-medium">Log In</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Don’t have an account?
      <button @click="showLogin = false; showSignup = true" class="text-[var(--rose-muted)] hover:underline font-medium">Sign up here</button>
    </p>
  </div>
</div>

<!-- 📝 Signup Modal -->
<div x-show="showSignup" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative"
       x-data="{ password: '', confirmPassword: '', mismatch: false }">
    <button @click="showSignup = false" class="absolute top-4 right-4 text-gray-400 hover:text-[var(--rose-muted)] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[var(--rose-muted)]">Create Account</h2>
    <form action="signup_handler.php" method="POST" class="space-y-4"
          @submit.prevent="mismatch = password !== confirmPassword; if (!mismatch) $el.submit();">
      <div class="grid grid-cols-2 gap-4">
        <input type="text" name="first_name" placeholder="First Name" required class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
        <input type="text" name="last_name" placeholder="Last Name" required class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      </div>
      <input type="email" name="email" placeholder="Email" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      <input type="text" name="phone" placeholder="Phone" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      <input type="text" name="address" placeholder="Address" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      <input type="password" name="password" placeholder="Password" x-model="password" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      <input type="password" name="confirm_password" placeholder="Confirm Password" x-model="confirmPassword" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
      <template x-if="mismatch"><p class="text-red-500 text-sm -mt-2">⚠ Passwords do not match.</p></template>
      <button type="submit" class="btn-rose w-full py-3 rounded-lg font-medium">Sign Up</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Already have an account?
      <button @click="showSignup = false; showLogin = true" class="text-[var(--rose-muted)] hover:underline font-medium">Log in here</button>
    </p>
  </div>
</div>

<!-- 🧚 Hero Section -->
<section class="py-16 md:py-24 px-6 bg-[var(--section-bg)]">
  <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-10 items-center">
    <div>
      <h1 class="header-font text-4xl md:text-6xl font-bold mb-4 text-gray-800">
        Whimsical Fashion from the Fairy Tale Forest
      </h1>
      <p class="text-lg mb-8">Discover charming apparel inspired by the Seven Dwarfs — where storybook magic meets modern comfort.</p>
      <a href="shop.php" class="btn-rose px-6 py-3 rounded-full font-medium shadow-sm hover:shadow-md">Shop Now</a>
    </div>

    <div>
      <img src="storebg.png" alt="Seven Dwarfs Collection" class="rounded-xl shadow-lg w-full">
    </div>
  </div>
</section>

<!-- 🧵 Featured Collections -->
<section class="py-16 px-6 bg-white">
  <div class="max-w-7xl mx-auto text-center">
    <h2 class="header-font text-3xl mb-10 text-gray-800">Dwarf-Inspired Collections</h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
      <div class="product-card bg-white rounded-xl shadow p-4">
        <img src="dress1.jpg" alt="Dress" class="w-full h-48 object-cover rounded-md mb-3">
        <h3 class="font-semibold">Dress</h3>
        <p class="text-sm text-gray-500">Whimsical & colorful</p>
      </div>
      <div class="product-card bg-white rounded-xl shadow p-4">
        <img src="trousers.jpg" alt="Trousers" class="w-full h-48 object-cover rounded-md mb-3">
        <h3 class="font-semibold">Trousers</h3>
        <p class="text-sm text-gray-500">Comfort wear</p>
      </div>
      <div class="product-card bg-white rounded-xl shadow p-4">
        <img src="whiteblouse1.jpg" alt="Tops" class="w-full h-48 object-cover rounded-md mb-3">
        <h3 class="font-semibold">Tops</h3>
        <p class="text-sm text-gray-500">Refined style</p>
      </div>
      <div class="product-card bg-white rounded-xl shadow p-4">
        <img src="coords.jpg" alt="Coords" class="w-full h-48 object-cover rounded-md mb-3">
        <h3 class="font-semibold">Coords</h3>
        <p class="text-sm text-gray-500">Joyful everyday wear</p>
      </div>
    </div>
  </div>
</section>

<!-- ✨ New Arrivals -->
<section class="py-16 px-6 bg-[var(--section-bg)]">
  <div class="max-w-7xl mx-auto text-center">
    <h2 class="header-font text-3xl mb-10 text-gray-800">New Arrivals from the Mine</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
      <div class="product-card bg-white rounded-xl shadow p-4">
        <img src="coords.jpg" class="rounded-lg w-full h-64 object-cover mb-3">
        <h3 class="font-semibold text-lg">Dwarf Hat Dress</h3>
        <p class="text-gray-500 text-sm mb-3">₱550 — Embroidered fairy charm</p>
        <button class="btn-rose w-full py-2 rounded-md">Add to Cart</button>
      </div>
      <div class="product-card bg-white rounded-xl shadow p-4">
        <img src="trousers.jpg" class="rounded-lg w-full h-64 object-cover mb-3">
        <h3 class="font-semibold text-lg">Button Cardigan</h3>
        <p class="text-gray-500 text-sm mb-3">₱750 — Cozy with seven charms</p>
        <button class="btn-rose w-full py-2 rounded-md">Add to Cart</button>
      </div>
      <div class="product-card bg-white rounded-xl shadow p-4">
        <img src="whiteblouse1.jpg" class="rounded-lg w-full h-64 object-cover mb-3">
        <h3 class="font-semibold text-lg">Miner's Overalls</h3>
        <p class="text-gray-500 text-sm mb-3">₱899 — Durable & charming</p>
        <button class="btn-rose w-full py-2 rounded-md">Add to Cart</button>
      </div>
    </div>
  </div>
</section>

<!-- 🩶 Footer -->
<footer class="bg-white border-t border-gray-200 py-10 mt-10">
  <div class="max-w-7xl mx-auto text-center text-sm text-gray-600">
    © <?= date('Y'); ?> Seven Dwarfs Boutique — Crafted with charm and care.
  </div>
</footer>

</body>
</html>
