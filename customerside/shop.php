<?php
session_start();
require 'conn.php'; // Database connection

$isLoggedIn = isset($_SESSION['customer_id']);
$avatar = '../assets/default-avatar.png'; // Updated for customer folder location

// 🧍 Customer Avatar
if ($isLoggedIn) {
  $stmt = $conn->prepare("SELECT profile_picture FROM customers WHERE customer_id = ?");
  $stmt->bind_param("i", $_SESSION['customer_id']);
  $stmt->execute();
  $res = $stmt->get_result();
  $cust = $res->fetch_assoc();
  if (!empty($cust['profile_picture'])) {
    $avatar = '../uploads/profiles/' . htmlspecialchars($cust['profile_picture']);
  }
  $stmt->close();
}

// 📂 Categories
$categories = [];
$resCat = $conn->query("SELECT category_name FROM categories ORDER BY category_name ASC");
while ($row = $resCat->fetch_assoc()) {
  $categories[] = $row['category_name'];
}

// 🧭 Filters
$selectedCategory = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? null;
$sort = $_GET['sort'] ?? 'latest';

// 🧠 Product Query
$sql = "
  SELECT 
    p.product_id,
    p.product_name,
    p.description,
    p.price_id,              -- changed from price_id
    p.image_url,
    c.category_name
  FROM products p
  LEFT JOIN categories c ON p.category_id = c.category_id
  WHERE 1=1
";

$binds = [];
$types = "";

// ✅ Only apply category filter if not “all”
if (!empty($selectedCategory) && strtolower($selectedCategory) !== 'all') {
  $sql .= " AND c.category_name = ?";
  $binds[] = $selectedCategory;
  $types .= "s";
}

if (!empty($search)) {
  $sql .= " AND p.product_name LIKE ?";
  $binds[] = "%$search%";
  $types .= "s";
}

// Sorting
switch ($sort) {
  case 'low_to_high': $sql .= " ORDER BY p.price_id ASC"; break;
  case 'high_to_low': $sql .= " ORDER BY p.price_id DESC"; break;
  default: $sql .= " ORDER BY p.product_id DESC"; break;
}

$stmt = $conn->prepare($sql);
if (!empty($binds)) {
  $stmt->bind_param($types, ...$binds);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
  // 🧠 Normalize image data
  $imageList = [];
  $raw = trim($row['image_url'] ?? '');

  if ($raw && str_starts_with($raw, '[')) {
      $decoded = json_decode($raw, true);
      if (is_array($decoded)) $imageList = $decoded;
  } elseif ($raw) {
      $imageList = array_filter(array_map('trim', explode(',', $raw)));
  }

  // Build correct paths relative to customer/
  $displayImages = [];
  if (!empty($imageList)) {
      foreach ($imageList as $img) {
          $img = trim($img);
          if (!str_contains($img, 'uploads/')) {
              $img = '../uploads/products/' . $img;
          } elseif (str_starts_with($img, 'uploads/')) {
              $img = '../' . $img;
          }
          $displayImages[] = htmlspecialchars($img);
      }
  } else {
      $displayImages[] = '../uploads/products/default.png';
  }

  $row['first_image'] = $displayImages[0];
  $products[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en" x-data="{ showLogin: false, showSignup: false }">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Shop | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    :root { --rose: #d37689; --rose-hover: #b75f6f; --soft-bg: #faf7f8; }
    body { background: var(--soft-bg); font-family: 'Poppins', sans-serif; color: #333; }
    .btn-rose { background: var(--rose); color: #fff; }
    .btn-rose:hover { background: var(--rose-hover); }
    .card:hover { transform: translateY(-3px); box-shadow: 0 8px 16px rgba(0,0,0,0.08); }
    [x-cloak] { display: none !important; }
  </style>
</head>

<body>
<!-- 🌸 Navbar -->
<nav class="bg-white border-b shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
    <div class="flex items-center gap-3">
      <img src="../logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose)]">Seven Dwarfs Boutique</h1>
    </div>

    <form action="shop.php" method="get" class="flex flex-1 mx-6 max-w-lg">
      <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search ?? '') ?>"
             class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none text-sm">
    </form>
<ul class="hidden md:flex space-x-5 text-sm font-medium">
      <li><a href="homepage.php" class="nav-link">Home</a></li>
      <li><a href="shop.php" class="text-white bg-[var(--rose-muted)] px-4 py-2 rounded-full hover:bg-[var(--rose-hover)] transition">Shop</a></li>
      <li><a href="about.php" class="nav-link">About</a></li>
      <li><a href="contact.php" class="nav-link">Contact</a></li>
    </ul>
    <div class="flex items-center gap-5">
      <a href="cart.php" class="text-[var(--rose)] hover:text-[var(--rose-hover)] relative">
        <i class="fa-solid fa-cart-shopping text-lg"></i>
      </a>

      <!-- Profile -->
      <div class="relative">
        <?php if ($isLoggedIn): ?>
          <div x-data="{ open: false }">
            <img src="<?= $avatar ?>" class="w-8 h-8 rounded-full border cursor-pointer" @click="open = !open">
            <div x-show="open" @click.away="open=false" class="absolute right-0 mt-3 bg-white border rounded-xl shadow-lg w-44 z-50">
              <a href="profile.php" class="block px-4 py-2 text-sm hover:bg-gray-100">My Profile</a>
              <a href="purchases.php" class="block px-4 py-2 text-sm hover:bg-gray-100">My Purchases</a>
              <form action="logout.php" method="POST">
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-gray-100">Logout</button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <button @click="showLogin = true" class="text-[var(--rose)] hover:text-[var(--rose-hover)]">
            <i class="fa-solid fa-user text-xl"></i>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- 🛍️ Product Section -->
<section class="max-w-7xl mx-auto px-6 py-12">
  <!-- Category & Sort Controls -->
  <div class="flex flex-wrap justify-between items-center mb-8">
    <form method="GET" id="categoryForm" class="flex items-center gap-3">
      <label class="text-sm font-medium text-gray-700">Category:</label>
      <select name="category" onchange="document.getElementById('categoryForm').submit()" 
              class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--rose)]">
        <option value="all" <?= ($selectedCategory === 'all') ? 'selected' : '' ?>>Show All</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat); ?>" <?= ($selectedCategory === $cat) ? 'selected' : ''; ?>>
            <?= htmlspecialchars($cat); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>

    <form method="GET" class="flex items-center gap-3">
      <label class="text-sm font-medium text-gray-700">Sort By:</label>
      <select name="sort" onchange="this.form.submit()" 
              class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--rose)]">
        <option value="latest" <?= ($sort === 'latest') ? 'selected' : ''; ?>>Latest</option>
        <option value="low_to_high" <?= ($sort === 'low_to_high') ? 'selected' : ''; ?>>Price: Low to High</option>
        <option value="high_to_low" <?= ($sort === 'high_to_low') ? 'selected' : ''; ?>>Price: High to Low</option>
      </select>
      <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search); ?>"><?php endif; ?>
      <?php if ($selectedCategory): ?><input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory); ?>"><?php endif; ?>
    </form>
  </div>

  <!-- Product Grid -->
  <div class="flex-1">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
      <?php if (!empty($products)): ?>
        <?php foreach ($products as $p): ?>
          <a href="product_detail.php?product_id=<?= $p['product_id']; ?>" 
             class="card bg-white rounded-xl shadow p-4 block hover:scale-[1.02] transition">
            <img 
              src="<?= htmlspecialchars($p['first_image']); ?>" 
              alt="<?= htmlspecialchars($p['product_name']); ?>" 
              class="w-full h-48 object-cover rounded-lg mb-3"
              onerror="this.src='../uploads/products/default.png';">
            <h4 class="text-lg font-semibold truncate"><?= htmlspecialchars($p['product_name']); ?></h4>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($p['category_name']); ?></p>
            <p class="mt-2 font-bold text-[var(--rose)]">₱<?= number_format($p['price_id'], 2); ?></p>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="col-span-full text-center text-gray-500">No products found.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- 🔐 Login Modal -->
<div x-show="showLogin" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative">
    <button @click="showLogin = false" class="absolute top-4 right-4 text-gray-400 hover:text-[var(--rose)] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[var(--rose)]">Welcome Back</h2>
    <form action="login_handler.php" method="POST" class="space-y-4">
      <input type="email" name="email" placeholder="Email" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      <input type="password" name="password" placeholder="Password" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      <button type="submit" class="bg-[var(--rose)] text-white w-full py-3 rounded-lg font-medium hover:bg-[var(--rose-hover)]">Log In</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Don’t have an account?
      <button @click="showLogin = false; showSignup = true" class="text-[var(--rose-muted)] hover:underline font-medium">Sign up here</button>
    </p>
  </div>
</div>

<!-- 📝 Signup Modal -->
<div x-show="showSignup" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative"
       x-data="{ password: '', confirmPassword: '', mismatch: false }">
    <button @click="showSignup = false" class="absolute top-4 right-4 text-gray-400 hover:text-[var(--rose)] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[var(--rose)]">Create Account</h2>
    <form action="signup_handler.php" method="POST" class="space-y-4"
          @submit.prevent="mismatch = password !== confirmPassword; if (!mismatch) $el.submit();">
      <div class="grid grid-cols-2 gap-4">
        <input type="text" name="first_name" placeholder="First Name" required class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
        <input type="text" name="last_name" placeholder="Last Name" required class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      </div>
      <input type="email" name="email" placeholder="Email" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      <input type="text" name="phone" placeholder="Phone" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      <input type="text" name="address" placeholder="Address" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      <input type="password" name="password" placeholder="Password" x-model="password" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      <input type="password" name="confirm_password" placeholder="Confirm Password" x-model="confirmPassword" required class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none">
      <template x-if="mismatch"><p class="text-red-500 text-sm -mt-2">⚠ Passwords do not match.</p></template>
      <button type="submit" class="bg-[var(--rose)] text-white w-full py-3 rounded-lg font-medium hover:bg-[var(--rose-hover)]">Sign Up</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Already have an account?
      <button @click="showSignup = false; showLogin = true" class="text-[var(--rose)] hover:underline font-medium">Log in here</button>
    </p>
  </div>
</div>

</body>
</html>
