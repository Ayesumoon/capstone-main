<?php
session_start();
require 'conn.php';

// 🧍 Check login
$isLoggedIn = isset($_SESSION['customer_id']);
$customer_id = $_SESSION['customer_id'] ?? null;

// 🛑 Redirect if no product_id
if (!isset($_GET['product_id'])) {
  header("Location: shop.php");
  exit();
}

$product_id = (int)$_GET['product_id'];

// 🧠 Fetch product details
$stmt = $conn->prepare("
  SELECT p.product_id, p.product_name, p.price_id, p.description, p.image_url, c.category_name 
  FROM products p
  LEFT JOIN categories c ON p.category_id = c.category_id
  WHERE p.product_id = ?
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
  echo "Product not found!";
  exit();
}

// 🖼 Handle multiple images
$images = [];
$raw = trim($product['image_url'] ?? '');
if ($raw && str_starts_with($raw, '[')) {
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) $images = $decoded;
} elseif ($raw) {
  $images = array_filter(array_map('trim', explode(',', $raw)));
}

// Build correct paths
$displayImages = [];
if (!empty($images)) {
  foreach ($images as $img) {
    $img = trim($img);
    if (!str_contains($img, 'uploads/')) {
      $img = '../uploads/products/' . basename($img);
    } elseif (str_starts_with($img, 'uploads/')) {
      $img = '../' . $img;
    }
    $displayImages[] = htmlspecialchars($img);
  }
} else {
  $displayImages[] = '../uploads/products/default.png';
}

// 🎨 Fetch available colors and sizes from stock
$colors = [];
$sizes = [];

$color_query = $conn->prepare("
  SELECT DISTINCT col.color_id, col.color 
  FROM stock st
  JOIN colors col ON st.color_id = col.color_id
  WHERE st.product_id = ?
");
$color_query->bind_param("i", $product_id);
$color_query->execute();
$color_result = $color_query->get_result();
while ($row = $color_result->fetch_assoc()) {
  $colors[] = $row;
}
$color_query->close();

$size_query = $conn->prepare("
  SELECT DISTINCT sz.size_id, sz.size 
  FROM stock st
  JOIN sizes sz ON st.size_id = sz.size_id
  WHERE st.product_id = ?
");
$size_query->bind_param("i", $product_id);
$size_query->execute();
$size_result = $size_query->get_result();
while ($row = $size_result->fetch_assoc()) {
  $sizes[] = $row;
}
$size_query->close();

// 🛒 Fetch cart count if logged in
$cartCount = 0;
$avatar = '../assets/default-avatar.png';
if ($isLoggedIn) {
  $stmt = $conn->prepare("SELECT SUM(quantity) AS total_items FROM carts WHERE customer_id = ?");
  $stmt->bind_param("i", $customer_id);
  $stmt->execute();
  $stmt->bind_result($cartCount);
  $stmt->fetch();
  $stmt->close();

  // Profile picture
  $avatarQuery = $conn->prepare("SELECT profile_picture FROM customers WHERE customer_id = ?");
  $avatarQuery->bind_param("i", $customer_id);
  $avatarQuery->execute();
  $avatarResult = $avatarQuery->get_result()->fetch_assoc();
  if (!empty($avatarResult['profile_picture'])) {
    $avatar = '../uploads/profiles/' . htmlspecialchars($avatarResult['profile_picture']);
  }
  $avatarQuery->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($product['product_name']); ?> | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    :root {
      --rose: #d37689;
      --rose-hover: #b75f6f;
      --soft-bg: #fef9fa;
    }
    body {
      background-color: var(--soft-bg);
      font-family: 'Poppins', sans-serif;
      color: #444;
    }
  </style>
</head>

<body>
<!-- 🌸 Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
    <div class="flex items-center gap-3">
      <img src="../logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose)]">Seven Dwarfs Boutique</h1>
    </div>

    <form action="shop.php" method="get" class="flex flex-1 mx-6 max-w-lg">
      <input type="text" name="search" placeholder="Search products..."
             class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--rose)] outline-none text-sm">
    </form>

    <ul class="hidden md:flex space-x-5 text-sm font-medium">
      <li><a href="homepage.php" class="nav-link">Home</a></li>
      <li><a href="shop.php" class="nav-link">Shop</a></li>
      <li><a href="about.php" class="nav-link">About</a></li>
      <li><a href="contact.php" class="nav-link">Contact</a></li>
    </ul>
    
    <div class="flex items-center gap-5">
      <a href="cart.php" class="text-[var(--rose)] hover:text-[var(--rose-hover)] relative">
        <i class="fa-solid fa-cart-shopping text-lg"></i>
        <?php if ($cartCount > 0): ?>
          <span class="absolute -top-2 -right-2 bg-[var(--rose)] text-white text-xs font-semibold rounded-full px-2 py-0.5">
            <?= $cartCount; ?>
          </span>
        <?php endif; ?>
      </a>
      <div class="relative">
        <?php if ($isLoggedIn): ?>
          <img src="<?= $avatar ?>" class="w-8 h-8 rounded-full border cursor-pointer">
        <?php else: ?>
          <a href="homepage.php" class="text-[var(--rose)] hover:text-[var(--rose-hover)]">
            <i class="fa-solid fa-user text-xl"></i>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- 🛍 Product Detail Section -->
<section class="max-w-6xl mx-auto px-6 py-12">
  <div class="flex flex-col md:flex-row gap-10 bg-white rounded-2xl shadow-lg p-8">
    
    <!-- 🖼 Product Images -->
    <div class="md:w-1/2 grid grid-cols-2 sm:grid-cols-3 gap-3">
      <?php foreach ($displayImages as $img): ?>
        <img src="<?= $img ?>" alt="<?= htmlspecialchars($product['product_name']); ?>"
             class="w-full h-48 object-cover rounded-lg shadow-sm hover:scale-105 transition-transform duration-200"
             onerror="this.src='../uploads/products/default.png';">
      <?php endforeach; ?>
    </div>

    <!-- 📋 Product Info -->
    <div class="flex-1">
      <h2 class="text-3xl font-semibold text-[var(--rose)]"><?= htmlspecialchars($product['product_name']); ?></h2>
      <p class="text-lg font-bold text-[var(--rose-hover)] mt-2">₱<?= number_format($product['price_id'], 2); ?></p>
      <p class="text-gray-600 mt-4 leading-relaxed"><?= nl2br(htmlspecialchars($product['description'])); ?></p>
      <p class="text-gray-500 mt-3 text-sm">Category: <?= htmlspecialchars($product['category_name']); ?></p>

      <!-- 🛒 Add to Cart -->
      <!-- 🛒 Add to Cart -->
<form method="POST" action="add_to_cart.php" class="flex flex-col gap-3 mt-6">
  <input type="hidden" name="product_id" value="<?= $product_id; ?>">

  <!-- 🩵 Choose Color -->
  <label class="text-sm font-medium text-gray-700">Color:</label>
  <select name="color_id" required class="border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[var(--rose)]"
    <?= empty($colors) ? 'disabled' : ''; ?>>
    <option value="">Select color</option>
    <?php foreach ($colors as $color): ?>
      <option value="<?= htmlspecialchars($color['color_id']); ?>">
        <?= htmlspecialchars($color['color']); ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- 🩷 Choose Size -->
  <label class="text-sm font-medium text-gray-700">Size:</label>
  <select name="size_id" required class="border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[var(--rose)]"
    <?= empty($sizes) ? 'disabled' : ''; ?>>
    <option value="">Select size</option>
    <?php foreach ($sizes as $size): ?>
      <option value="<?= htmlspecialchars($size['size_id']); ?>">
        <?= htmlspecialchars($size['size']); ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- Quantity + Button -->
  <div class="flex items-center gap-3 mt-4">
    <input type="number" name="quantity" value="1" min="1"
           class="border rounded-lg px-3 py-2 w-24 text-center focus:ring-2 focus:ring-[var(--rose)]">
    <button type="submit"
            class="bg-[var(--rose)] text-white px-6 py-3 rounded-full text-sm shadow-md hover:bg-[var(--rose-hover)] transition">
      Add to Cart
    </button>
  </div>
</form>

      <div class="mt-6">
        <a href="shop.php"
           class="bg-gray-100 text-gray-700 px-6 py-2 rounded-full shadow-sm hover:bg-gray-200 transition">
          ← Back to Shop
        </a>
      </div>
    </div>
  </div>
</section>

<footer class="bg-white border-t border-gray-200 py-6 mt-10 text-center text-sm text-gray-600">
  © <?= date('Y') ?> Seven Dwarfs Boutique | All Rights Reserved.
</footer>
</body>
</html>
