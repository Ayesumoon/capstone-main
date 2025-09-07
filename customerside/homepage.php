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



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seven Dwarfs Boutique | Whimsical Fairy Tale Fashion</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --pink-pastel: #ffd6e0;
      --doc-red: #ff6b6b;
      --grumpy-green: #82c596;
      --sleepy-blue: #a0c4ff;
      --happy-yellow: #ffea99;
      --bashful-purple: #c8b6ff;
      --sneezy-orange: #ffb347;
      --dopey-white: #f8f9fa;
    }
    
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #fff9fb;
    }
    
    .header-font {
      font-family: 'Playfair Display', serif;
    }
    
    .nav-link:hover {
      color: var(--bashful-purple);
      transform: translateY(-2px);
    }
    
    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
  </style>
</head>
<body class="text-gray-800">

  <!-- Hero Section -->
  <section class="pt-24 pb-16 md:pt-32 md:pb-24 px-6" style="background-color: var(--pink-pastel);">
    <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-8 items-center">
      <div>
        <h1 class="header-font text-4xl md:text-6xl font-bold mb-4 text-gray-800">Whimsical Fashion from the Fairy Tale Forest</h1>
        <p class="text-lg mb-8 text-gray-800">Discover charming apparel inspired by Snow White's seven dwarfs - where fairy tale magic meets modern style.</p>
  <p class="text-lg text-pink-900 mb-6"> Welcome to Seven Dwarfs  Boutique ✨</p>
 </p>
        <div class="flex space-x-4">
         <a href="shop.php" class="bg-pink-600 text-white px-6 py-3 rounded-full hover:bg-pink-700 transition">Shop Now</a>
        </div>
      </div>
      <div class="relative">
        <img src="storebg.png" alt="Happy models wearing colorful Seven Dwarfs Boutique fashion standing in a forest glen surrounded by butterflies and flowers" class="rounded-lg shadow-xl w-full">
        <div class="absolute -bottom-4 -right-4 bg-yellow-200 p-2 rounded-lg shadow-md">
          <p class="text-xs font-bold">New Summer Collection</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Featured Categories -->
  <section class="py-16 px-6">
    <div class="max-w-7xl mx-auto">
      <h2 class="header-font text-3xl text-center mb-12">Our Dwarf-Inspired Collections</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <!-- Dress -->
        <div class="bg-white rounded-xl overflow-hidden shadow-md product-card transition duration-300">
          <div class="h-48 bg-blue-50 flex items-center justify-center">
            <img src="dress1.jpg" alt="Fun, mismatched clothing items in bright colors representing Dopey's playful style" class="h-full w-full object-cover">
          </div>
          <div class="p-4">
            <h3 class="font-bold text-lg mb-2">Dress</h3>
            <p class="text-gray-600 text-sm">Whimsical, colorful styles</p>
          </div>
        </div>
        
        <!-- Trousers -->
        <div class="bg-white rounded-xl overflow-hidden shadow-md product-card transition duration-300">
          <div class="h-48 bg-green-50 flex items-center justify-center">
            <img src="trousers.jpg" alt="Warm, comfortable sweaters and jackets in forest greens representing Grumpy's practical nature" class="h-full w-full object-cover">
          </div>
          <div class="p-4">
            <h3 class="font-bold text-lg mb-2">Trousers</h3>
            <p class="text-gray-600 text-sm">Practical comfort wear</p>
          </div>
        </div>
        
        <!-- Blouse -->
        <div class="bg-white rounded-xl overflow-hidden shadow-md product-card transition duration-300">
          <div class="h-48 bg-red-50 flex items-center justify-center">
            <img src="whiteblouse1.jpg" alt="Elegant blazers and dresses in rich red tones representing Doc's leadership" class="h-full w-full object-cover">
          </div>
          <div class="p-4">
            <h3 class="font-bold text-lg mb-2">Tops</h3>
            <p class="text-gray-600 text-sm">Refined styles</p>
          </div>
        </div>
        
        <!-- Coords -->
        <div class="bg-white rounded-xl overflow-hidden shadow-md product-card transition duration-300">
          <div class="h-48 bg-yellow-50 flex items-center justify-center">
            <img src="coords.jpg" alt="Bright, cheerful sundresses and shirts in sunny yellows representing Happy's joyful personality" class="h-full w-full object-cover">
          </div>
          <div class="p-4">
            <h3 class="font-bold text-lg mb-2">Coords</h3>
            <p class="text-gray-600 text-sm">Joyful everyday wear</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- New Arrivals -->
  <section class="py-16 px-6" style="background-color: var(--pink-pastel);">
    <div class="max-w-7xl mx-auto">
      <h2 class="header-font text-3xl text-center mb-12">New Arrivals from the Mine</h2>
      
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Product 1 -->
        <div class="bg-white rounded-xl overflow-hidden shadow-lg product-card transition duration-300">
          <div class="relative">
            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/e7509efe-d19b-4525-b9e8-60958fa1f875.png" alt="Pastel pink dress with embroidered dwarf hats along the hem and puff sleeves" class="w-full h-80 object-cover">
            <span class="absolute top-4 right-4 bg-pink-200 text-pink-800 text-xs font-bold px-3 py-1 rounded-full">New</span>
          </div>
          <div class="p-6">
            <div class="flex justify-between items-start mb-2">
              <h3 class="font-bold text-xl">Dwarf Hat Dress</h3>
              <span class="font-bold text-lg">$68.99</span>
            </div>
            <p class="text-gray-600 mb-4">A charming dress featuring tiny embroidered dwarf hats</p>
            <div class="flex space-x-2 mb-4">
              <span class="w-5 h-5 rounded-full bg-pink-300"></span>
              <span class="w-5 h-5 rounded-full bg-blue-300"></span>
              <span class="w-5 h-5 rounded-full bg-yellow-300"></span>
            </div>
            <button class="w-full bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg transition duration-300">Add to Cart</button>
          </div>
        </div>
        
        <!-- Product 2 -->
        <div class="bg-white rounded-xl overflow-hidden shadow-lg product-card transition duration-300">
          <div class="relative">
            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/deb715fc-1167-4224-ad9c-4bb5cbf304c1.png" alt="Patchwork cardigan in rainbow colors with seven different colored buttons representing each dwarf" class="w-full h-80 object-cover">
            <span class="absolute top-4 right-4 bg-yellow-200 text-yellow-800 text-xs font-bold px-3 py-1 rounded-full">Bestseller</span>
          </div>
          <div class="p-6">
            <div class="flex justify-between items-start mb-2">
              <h3 class="font-bold text-xl">Dwarf Button Cardigan</h3>
              <span class="font-bold text-lg">$79.99</span>
            </div>
            <p class="text-gray-600 mb-4">Cozy cardigan featuring seven special buttons</p>
            <div class="flex space-x-2 mb-4">
              <span class="w-5 h-5 rounded-full bg-red-300"></span>
              <span class="w-5 h-5 rounded-full bg-green-300"></span>
              <span class="w-5 h-5 rounded-full bg-purple-300"></span>
            </div>
            <button class="w-full bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg transition duration-300">Add to Cart</button>
          </div>
        </div>
        
        <!-- Product 3 -->
        <div class="bg-white rounded-xl overflow-hidden shadow-lg product-card transition duration-300">
          <div class="relative">
            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/c9b7af6d-4ba4-4a45-bffb-c5e02d56d840.png" alt="Denim overalls with seven small pockets, each in a different color and embroidered with a dwarf's name" class="w-full h-80 object-cover">
            <span class="absolute top-4 right-4 bg-blue-200 text-blue-800 text-xs font-bold px-3 py-1 rounded-full">Limited</span>
          </div>
          <div class="p-6">
            <div class="flex justify-between items-start mb-2">
              <h3 class="font-bold text-xl">Miner's Overalls</h3>
              <span class="font-bold text-lg">$89.99</span>
            </div>
            <p class="text-gray-600 mb-4">Authentic-style overalls with seven special pockets</p>
            <div class="flex space-x-2 mb-4">
              <span class="w-5 h-5 rounded-full bg-orange-300"></span>
              <span class="w-5 h-5 rounded-full bg-white border border-gray-300"></span>
            </div>
            <button class="w-full bg-gray-800 hover:bg-gray-700 text-white py-2 rounded-lg transition duration-300">Add to Cart</button>
          </div>
        </div>
      </div>
      
      <div class="text-center mt-12">
        <button class="border-2 border-gray-800 hover:bg-gray-800 hover:text-white px-8 py-3 rounded-full font-medium transition duration-300">View All Products</button>
      </div>
    </div>
  </section>

  <!-- Testimonials -->
  <section class="py-16 px-6">
    <div class="max-w-7xl mx-auto">
      <h2 class="header-font text-3xl text-center mb-12">What Our Customers Say</h2>
      
      <div class="grid md:grid-cols-3 gap-8">
        <!-- Testimonial 1 -->
        <div class="bg-white p-8 rounded-xl shadow-md">
          <div class="flex items-center mb-4">
            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/5e82d368-de34-440a-aa45-08068062a14c.png" alt="Smiling young woman with curly hair wearing the Dwarf Button Cardigan" class="w-12 h-12 rounded-full mr-4">
            <div>
              <h4 class="font-bold">Emma Johnson</h4>
              <div class="flex">
                <span>★★★★★</span>
              </div>
            </div>
          </div>
          <p>"The Dwarf Button Cardigan is my new favorite! The quality is amazing and I love the story behind each button. It's so unique and gets compliments everywhere I go!"</p>
        </div>
        
        <!-- Testimonial 2 -->
        <div class="bg-white p-8 rounded-xl shadow-md">
          <div class="flex items-center mb-4">
            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/d03c1da1-8f0e-4623-b26d-c1705bce975f.png" alt="Middle-aged man with beard smiling, wearing the Miner's Overalls with tools in the pockets" class="w-12 h-12 rounded-full mr-4">
            <div>
              <h4 class="font-bold">Robert Smith</h4>
              <div class="flex">
                <span>★★★★★</span>
              </div>
            </div>
          </div>
          <p>"As a craftsman, I appreciate both style and functionality. These overalls are perfect - durable, comfortable, and the pockets are genuinely useful. The dwarf theme is a fun touch!"</p>
        </div>
        
        <!-- Testimonial 3 -->
        <div class="bg-white p-8 rounded-xl shadow-md">
          <div class="flex items-center mb-4">
            <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/8f3fc4ee-f7cc-42d0-a9bf-3003f36bcb30.png" alt="Teenage girl with short hair laughing, wearing the Dwarf Hat Dress at a garden party" class="w-12 h-12 rounded-full mr-4">
            <div>
              <h4 class="font-bold">Sophia Chen</h4>
              <div class="flex">
                <span>★★★★☆</span>
              </div>
            </div>
          </div>
          <p>"The Dwarf Hat Dress is adorable! It fits perfectly and is so comfortable. I wish there were more color options though - I'd buy them all!"</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Newsletter -->
  <section class="py-16 px-6" style="background-color: var(--pink-pastel);">
    <div class="max-w-4xl mx-auto text-center">
      <h2 class="header-font text-3xl mb-4">Join Our Fairy Tale</h2>
      <p class="mb-8 max-w-2xl mx-auto">Subscribe to get updates on new collections, exclusive offers, and magical surprises straight from the dwarfs' workshop!</p>
      <form class="flex flex-col sm:flex-row gap-4 max-w-lg mx-auto">
        <input type="email" placeholder="Enter your email" class="flex-grow px-4 py-3 rounded-full border-0 focus:ring-2 focus:ring-pink-400">
        <button type="submit" class="bg-pink-600 hover:bg-pink-700 text-white px-8 py-3 rounded-full font-medium transition duration-300">Subscribe</button>
      </form>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-gray-900 text-gray-300 py-12 px-6">
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
      <div>
        <div class="flex items-center space-x-2 mb-4">
          <img src="https://storage.googleapis.com/workspace-0f70711f-8b4e-4d94-86f1-2a93ccde5887/image/4e7eb94a-624e-4314-b694-a6f6b4468b63.png" alt="Seven Dwarfs Boutique small logo in white" class="h-8 w-8">
          <span class="header-font text-xl font-bold text-white">Seven Dwarfs Boutique</span>
        </div>
        <p class="text-sm">Bringing fairy tale magic to your wardrobe since 2023.</p>
      </div>
      
      <div>
        <h3 class="text-white font-bold mb-4">Shop</h3>
        <ul class="space-y-2">
          <li><a href="#" class="hover:text-white transition duration-300">New Arrivals</a></li>
          <li><a href="#" class="hover:text-white transition duration-300">Dwarf Collections</a></li>
          <li><a href="#" class="hover:text-white transition duration-300">Best Sellers</a></li>
          <li><a href="#" class="hover:text-white transition duration-300">Sale</a></li>
        </ul>
      </div>
      
      <div>
        <h3 class="text-white font-bold mb-4">About</h3>
        <ul class="space-y-2">
          <li><a href="#" class="hover:text-white transition duration-300">Our Story</a></li>
          <li><a href="#" class="hover:text-white transition duration-300">Sustainability</a></li>
          <li><a href="#" class="hover:text-white transition duration-300">Blog</a></li>
          <li><a href="#" class="hover:text-white transition duration-300">Careers</a></li>
        </ul>
      </div>
      
      <div>
        <h3 class="text-white font-bold mb-4">Contact</h3>
        <address class="not-italic">
          <p class="mb-2">123 Fairy Tale Lane</p>
          <p class="mb-2">Storybrooke, ST 12345</p>
          <p class="mb-2">(555) 123-4567</p>
          <p>hello@sevendwarfsboutique.com</p>
        </address>
      </div>
    </div>
    
    <div class="max-w-7xl mx-auto pt-8 mt-8 border-t border-gray-800 flex flex-col md:flex-row justify-between items-center">
      <p class="text-sm mb-4 md:mb-0">© 2023 Seven Dwarfs Boutique. All rights reserved.</p>
      <div class="flex space-x-4">
        <a href="#" class="hover:text-white transition duration-300">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"></path>
          </svg>
        </a>
        <a href="#" class="hover:text-white transition duration-300">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"></path>
          </svg>
        </a>
        <a href="#" class="hover:text-white transition duration-300">
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"></path>
          </svg>
        </a>
      </div>
    </div>
  </footer>

  <script>
    // Shopping cart functionality
    let cartCount = 0;
    const cartButtons = document.querySelectorAll('button:contains("Add to Cart")');
    
    cartButtons.forEach(button => {
      button.addEventListener('click', function() {
        cartCount++;
        document.querySelector('nav svg[aria-label="Shopping cart"] + span').textContent = cartCount;
        
        // Animation
        this.textContent = 'Added!';
        this.classList.add('bg-green-600');
        setTimeout(() => {
          this.textContent = 'Add to Cart';
          this.classList.remove('bg-green-600');
        }, 1500);
      });
    });
    
    // Mobile menu toggle
    const mobileMenuButton = document.querySelector('.md\\:hidden');
    const navLinks = document.querySelector('.hidden.md\\:flex');
    
    mobileMenuButton.addEventListener('click', function() {
      navLinks.classList.toggle('hidden');
      navLinks.classList.toggle('flex');
      navLinks.classList.toggle('flex-col');
      navLinks.classList.toggle('absolute');
      navLinks.classList.toggle('top-16');
      navLinks.classList.toggle('left-0');
      navLinks.classList.toggle('right-0');
      navLinks.classList.toggle('bg-white');
      navLinks.classList.toggle('p-4');
      navLinks.classList.toggle('shadow-lg');
    });
  </script>
</body>
</html>

