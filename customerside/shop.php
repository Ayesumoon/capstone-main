<?php
session_start();

// Connect to database
$conn = new mysqli("localhost", "root", "", "dbms");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$isLoggedIn = isset($_SESSION['customer_id']);

// 🔥 Handle profile picture (avatar) logic
$avatar = 'assets/default-avatar.png'; // default avatar
if (isset($_SESSION['customer_id'])) {
  $customer_id = $_SESSION['customer_id'];

  $stmt = $conn->prepare("SELECT profile_picture FROM customers WHERE customer_id = ?");
  $stmt->bind_param("i", $customer_id);
  $stmt->execute();
  $resultProfile = $stmt->get_result();
  $customer = $resultProfile->fetch_assoc();

  if (!empty($customer['profile_picture'])) {
    $avatar = 'uploads/profiles/' . htmlspecialchars($customer['profile_picture']);
  }
}

// ✅ Now continue to build product shop query

// Assuming you've connected to the database already
if (isset($_GET['product_id'])) {
  $product_id = $_GET['product_id'];
  $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
  $stmt->bind_param("i", $product_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows > 0) {
      $product = $result->fetch_assoc();
      // Now you can display the product details
  } else {
      echo "Product not found.";
  }
}

// Initialize categories array
$categories = [];

// Get categories from the database
$sql = "SELECT category_name FROM categories";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row['category_name'];
    }
} else {
    // Optionally, log an error or handle empty categories
    // echo "No categories found or query failed.";
}


// Get category, sort, and search filters from query string
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : null;

/// Base SQL
$sql = "SELECT p.product_name, p.description, p.price_id AS price, p.image_url, c.category_name, p.product_id
FROM products p
JOIN categories c ON p.category_id = c.category_id
WHERE p.stocks > 0";

// Add category filter if selected
if ($selectedCategory) {
$sql .= " AND c.category_name = ?";
}

// Add search filter if provided
if ($searchQuery) {
$sql .= " AND p.product_name LIKE ?";
}

// Add sorting logic
switch ($sort) {
case 'low_to_high':
$sql .= " ORDER BY p.price_id ASC";
break;
case 'high_to_low':
$sql .= " ORDER BY p.price_id DESC";
break;
default:
$sql .= " ORDER BY p.product_id DESC"; // latest
}

// ✅ Prepare and execute query
$stmt = $conn->prepare($sql);

// Bind parameters based on which filters are applied
if ($selectedCategory && $searchQuery) {
$searchQuery = "%" . $searchQuery . "%"; // Wildcard for search
$stmt->bind_param("ss", $selectedCategory, $searchQuery); // Two string parameters
} elseif ($selectedCategory) {
$stmt->bind_param("s", $selectedCategory); // Single string parameter
} elseif ($searchQuery) {
$searchQuery = "%" . $searchQuery . "%"; // Wildcard for search
$stmt->bind_param("s", $searchQuery); // Single string parameter
}

$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
die("Query failed: " . $conn->error);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Shop | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-pink-50 text-gray-800" x-data="{ showLogin: false, showSignup: false, cartCount: 0 }" @keydown.escape.window="showLogin = false; showSignup = false">
  <!-- Navbar -->
  <nav class="bg-pink-100 shadow-md">
    <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">
      
  

      <!-- Left side: Logo and Brand Name -->
      <div class="flex items-center space-x-4">
        <img src="logo.png" alt="User profile picture" class="rounded-full" width="60" height="50">
      </div>                       

      <!-- Left: Logo + Search -->
      <div class="flex flex-1 items-center gap-4">
        <h1 class="text-2xl font-bold text-pink-600 whitespace-nowrap">Seven Dwarfs Boutique</h1>
        <form action="shop.php" method="get" class="flex flex-1 max-w-sm">
          <input type="text" name="search" placeholder="Search products..." 
                class="w-full px-3 py-2 border border-pink-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-400 text-sm">
        </form>
      </div>
      
    
    <div class="flex items-center justify-center space-x-8">
  
 <!-- Center: Navigation Links -->
 <ul class="flex flex-wrap justify-center space-x-4 text-sm md:text-base">
        <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
        <li><a href="shop.php" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-full transition">Shop</a></li>
        <li><a href="about.php" class="hover:text-pink-500">About</a></li>
        <li><a href="contact.php" class="hover:text-pink-500">Contact</a></li>
      </ul>

      

      <!-- Right: Icons -->
      <div class="flex items-center gap-4 text-pink-600">
        <!-- Cart Icon -->
        <a href="cart.php" class="hover:text-pink-500" title="Cart">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h11.1a1 1 0 001-.8l1.4-5.2H7zm0 0l-1-4H4" />
          </svg>
        </a>

        <!-- Profile Icon -->
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

 <!-- Main Content -->
<section class="max-w-7xl mx-auto px-4 py-12 flex gap-10">
  <!-- Sidebar -->
  <aside class="w-64 bg-white rounded-xl shadow-md p-6">
    <h3 class="text-lg font-bold mb-4 text-pink-700">Filters</h3>
    <ul class="space-y-2">
      <li><a href="shop.php" class="text-gray-700 hover:text-pink-600">✨ New Arrivals</a></li>
      <li><a href="#" class="text-gray-700 hover:text-pink-600">🔥 On Sale</a></li>
    </ul>
    <div class="mt-6">
      <button class="w-full flex justify-between items-center font-semibold text-pink-600">
        Categories
        <svg class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      <ul class="mt-3 pl-2 text-sm text-gray-700 space-y-1">
        <?php foreach ($categories as $cat): ?>
          <li>
            <a href="shop.php?category=<?= urlencode($cat) ?>" 
              class="<?= $selectedCategory === $cat ? 'text-pink-600 font-semibold' : 'hover:text-pink-500' ?>">
              <?= $cat ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>

  <!-- Product Grid -->
  <div class="flex-1">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($product = $result->fetch_assoc()): ?>
          <div class="bg-white rounded-xl shadow-md p-4 transform transition-transform duration-300 hover:scale-105 hover:-translate-y-2 hover:shadow-lg">
            <a href="product_detail.php?product_id=<?php echo $product['product_id']; ?>" class="block">
              <img src="<?php echo $product['image_url']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="w-full h-48 object-cover rounded-lg mb-4">
              <h4 class="text-lg font-semibold"><?php echo htmlspecialchars($product['product_name']); ?></h4>
              <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($product['category_name']); ?></p>
              <p class="font-semibold text-pink-600 mt-2">₱<?php echo number_format($product['price'], 2); ?></p>
            </a>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="text-gray-500 text-lg">No products found.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
