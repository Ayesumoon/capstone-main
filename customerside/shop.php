<?php
session_start();

// Connect to database
$conn = new mysqli("localhost", "root", "", "dbms");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$isLoggedIn = isset($_SESSION['customer_id']);

// 🔥 Handle profile picture
$avatar = 'assets/default-avatar.png';
if ($isLoggedIn) {
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

// ✅ Filters
$categories = [];
$resultCats = $conn->query("SELECT category_name FROM categories ORDER BY category_name ASC");
while ($row = $resultCats->fetch_assoc()) {
  $categories[] = $row['category_name'];
}

$selectedCategory = $_GET['category'] ?? null;
$sort = $_GET['sort'] ?? 'latest';
$searchQuery = $_GET['search'] ?? null;

// ✅ Build query
$sql = "
SELECT 
    p.product_id,
    p.product_name,
    p.description,
    p.price_id AS price,
    p.image_url,
    c.category_name,
    COALESCE(SUM(st.current_qty), 0) AS total_stock
FROM products p
JOIN categories c ON p.category_id = c.category_id
LEFT JOIN stock st ON p.product_id = st.product_id
WHERE 1=1
";

$binds = [];
$types = "";

// Category filter
if ($selectedCategory) {
  $sql .= " AND c.category_name = ?";
  $binds[] = $selectedCategory;
  $types .= "s";
}

// Search filter
if ($searchQuery) {
  $sql .= " AND p.product_name LIKE ?";
  $binds[] = "%$searchQuery%";
  $types .= "s";
}

$sql .= "
GROUP BY p.product_id, p.product_name, p.description, p.price_id, p.image_url, c.category_name
";

// Sorting
switch ($sort) {
  case 'low_to_high':
    $sql .= " ORDER BY p.price_id ASC";
    break;
  case 'high_to_low':
    $sql .= " ORDER BY p.price_id DESC";
    break;
  default:
    $sql .= " ORDER BY p.product_id DESC";
    break;
}

$stmt = $conn->prepare($sql);
if (!empty($binds)) {
  $stmt->bind_param($types, ...$binds);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en" x-data="{ showLogin: false, showSignup: false, cartCount: 0 }" @keydown.escape.window="showLogin = false; showSignup = false">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Shop | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

  <style>
    :root {
      --rose-muted: #d37689;
      --rose-hover: #b75f6f;
      --blush-bg: #faf7f8;
      --soft-bg: #f5f2f3;
      --text-gray: #444;
    }

    body {
      background-color: var(--blush-bg);
      color: var(--text-gray);
      font-family: 'Poppins', sans-serif;
    }

    .btn-rose {
      background-color: var(--rose-muted);
      color: white;
      transition: background-color 0.2s ease, transform 0.2s ease;
    }

    .btn-rose:hover {
      background-color: var(--rose-hover);
      transform: translateY(-1px);
    }

    .nav-link:hover {
      color: var(--rose-muted);
      transform: translateY(-1px);
    }

    .card {
      transition: all 0.25s ease;
    }

    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.08);
    }

    [x-cloak] { display: none !important; }
  </style>
</head>

<body>

<!-- 🌸 Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">

    <!-- Left: Logo -->
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose-muted)]">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Search -->
    <div class="flex flex-1 items-center gap-4 max-w-lg">
      <form action="shop.php" method="get" class="flex flex-1">
        <input type="text" name="search" placeholder="Search products..."
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none text-sm">
      </form>
    </div>

    <!-- Links -->
    <ul class="hidden md:flex space-x-5 text-sm font-medium">
      <li><a href="homepage.php" class="nav-link">Home</a></li>
      <li><a href="shop.php" class="text-white bg-[var(--rose-muted)] hover:bg-[var(--rose-hover)] px-4 py-2 rounded-full transition">Shop</a></li>
      <li><a href="about.php" class="nav-link">About</a></li>
      <li><a href="contact.php" class="nav-link">Contact</a></li>
    </ul>

    <!-- Icons -->
    <div class="flex items-center gap-5">
      <a href="cart.php" class="text-[var(--rose-muted)] hover:text-[var(--rose-hover)] relative">
        <i class="fa-solid fa-cart-shopping text-lg"></i>
      </a>

      <!-- Profile -->
      <div class="relative">
        <?php if ($isLoggedIn): ?>
        <div x-data="{ open: false }">
          <img src="<?= $avatar ?>" alt="Profile" class="w-8 h-8 rounded-full border border-[var(--rose-muted)] cursor-pointer" @click="open=!open">
          <div x-show="open" @click.away="open=false"
               class="absolute right-0 mt-3 w-44 bg-white border border-gray-200 rounded-xl shadow-lg z-50">
            <a href="profile.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Profile</a>
            <a href="purchases.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Purchases</a>
            <form action="logout.php" method="POST">
              <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-[var(--soft-bg)]">Logout</button>
            </form>
          </div>
        </div>
        <?php else: ?>
        <button @click="showLogin = true" class="text-[var(--rose-muted)] hover:text-[var(--rose-hover)]">
          <i class="fa-solid fa-user text-xl"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- 🛍️ Main Section -->
<section class="max-w-7xl mx-auto px-4 py-12 flex flex-col md:flex-row gap-8">

  <!-- Sidebar Filters -->
  <aside class="w-full md:w-64 bg-white rounded-xl shadow p-6">
    <h3 class="text-lg font-semibold mb-4 text-[var(--rose-muted)]">Filters</h3>
    <ul class="space-y-2 text-sm">
      <li><a href="shop.php" class="hover:text-[var(--rose-muted)]">✨ New Arrivals</a></li>
      <li><a href="#" class="hover:text-[var(--rose-muted)]">🔥 On Sale</a></li>
    </ul>

    <div class="mt-6">
      <h4 class="font-semibold text-gray-700 mb-2">Categories</h4>
      <ul class="space-y-1 text-sm">
        <?php foreach ($categories as $cat): ?>
          <li>
            <a href="shop.php?category=<?= urlencode($cat) ?>"
              class="<?= $selectedCategory === $cat ? 'text-[var(--rose-muted)] font-semibold' : 'hover:text-[var(--rose-muted)]' ?>">
              <?= htmlspecialchars($cat) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>

  <!-- Product Grid -->
  <div class="flex-1">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <?php if ($result->num_rows > 0): ?>
        <?php while ($product = $result->fetch_assoc()): ?>
          <?php if ($product['total_stock'] > 0): ?>
            <div class="card bg-white rounded-xl shadow p-4">
              <a href="product_detail.php?product_id=<?= $product['product_id']; ?>">
                <img src="<?= $product['image_url'] ?: 'assets/no-image.png' ?>"
                     alt="<?= htmlspecialchars($product['product_name']); ?>"
                     class="w-full h-48 object-cover rounded-lg mb-3">
                <h4 class="text-lg font-semibold truncate"><?= htmlspecialchars($product['product_name']); ?></h4>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($product['category_name']); ?></p>
                <p class="mt-2 font-bold text-[var(--rose-muted)]">₱<?= number_format($product['price'], 2); ?></p>
              </a>
            </div>
          <?php endif; ?>
        <?php endwhile; ?>
      <?php else: ?>
        <p class="col-span-full text-center text-gray-500">No products found.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

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

</body>
</html>
