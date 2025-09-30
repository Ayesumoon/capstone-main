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
      <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
      <li><a href="shop.php" class="hover:text-pink-500">Shop</a></li>
      <li><a href="about.php" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-full transition">About</a></li>
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

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us | Seven Dwarfs Boutique</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      background: #fff5f7;
      color: #333;
    }

    header {
      background: #f7b6c2;
      padding: 15px 40px;
      text-align: center;
    }

    header h1 {
      margin: 0;
      color: #fff;
      font-size: 28px;
      letter-spacing: 2px;
    }

    .about-section {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: center;
      padding: 60px 20px;
      max-width: 1100px;
      margin: auto;
    }

    .about-content {
      flex: 1 1 500px;
      padding: 20px;
    }

    .about-content h2 {
      font-size: 32px;
      color: #b85c6e;
      margin-bottom: 15px;
    }

    .about-content p {
      font-size: 16px;
      line-height: 1.7;
      margin-bottom: 20px;
    }

    .about-image {
      flex: 1 1 500px;
      padding: 20px;
      text-align: center;
    }

    .about-image img {
      max-width: 100%;
      border-radius: 15px;
      box-shadow: 0px 4px 15px rgba(218, 90, 122, 0.1);
    }

    footer {
      background: #f7b6c2;
      text-align: center;
      padding: 15px;
      margin-top: 40px;
      color: #fff;
    }
  </style>
</head>
<body>
  <header>
    <h1>Seven Dwarfs Boutique</h1>
  </header>

  <section class="about-section">
    <div class="about-content">
      <h2>About Us</h2>
      <p>
        At <strong>Seven Dwarfs Boutique</strong>, we believe that fashion should be a magical 
        experience one that brings confidence, charm, and joy to every individual. 
        Inspired by timeless fairy tales and modern trends, our boutique offers a 
        unique collection of clothing, accessories, and styles that celebrate 
        beauty in all its forms.
      </p>
      <p>
        Founded with the vision of blending whimsy and elegance, Seven Dwarfs Boutique 
        is more than just a fashion store it’s a place where creativity meets individuality. 
        Each piece is carefully curated to reflect both classic sophistication and 
        modern playfulness, ensuring our customers always find something special.
      </p>
      <p>
        Step into a world of style, magic, and confidence welcome to 
        <strong>Seven Dwarfs Boutique</strong>.
      </p>
    </div>
    <div class="about-image">
      <img src="SDB bg.jpg" alt="Seven Dwarfs Boutique">
    </div>
  </section>

  <footer>
    <p>&copy; 2025 Seven Dwarfs Boutique | All Rights Reserved</p>
  </footer>
</body>
</html>
