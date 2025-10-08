<?php
session_start();
require 'conn.php';

$isLoggedIn = isset($_SESSION['customer_id']);
$cartItems = [];
$grandTotal = 0;
$cartCount = 0;

if ($isLoggedIn) {
    $customer_id = $_SESSION['customer_id'];

    // ✅ Fetch the customer's actual chosen color & size from the carts table
    $sql = "
        SELECT 
            c.cart_id, 
            c.quantity, 
            c.color, 
            c.size, 
            p.product_name, 
            p.price_id, 
            p.image_url
        FROM carts c
        JOIN products p ON c.product_id = p.product_id
        WHERE c.customer_id = ? AND c.cart_status = 'active'
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // 🖼 Handle multiple images (JSON or comma-separated)
        $images = [];
        $raw = trim($row['image_url'] ?? '');

        if ($raw && str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) $images = $decoded;
        } elseif ($raw) {
            $images = array_filter(array_map('trim', explode(',', $raw)));
        }

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

        $row['display_image'] = $displayImages[0];
        $row['total'] = $row['price_id'] * $row['quantity'];
        $grandTotal += $row['total'];
        $cartItems[] = $row;
    }

    $cartCount = count($cartItems);
}
?>
<!DOCTYPE html>
<html lang="en" x-data="{ showLogin:false, showSignup:false }">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Cart | Seven Dwarfs Boutique</title>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

  <style>
    body {
      background-color: #faf7f8;
    }
    .brand-accent {
      color: #d76d7a;
    }
    .brand-bg {
      background-color: #d76d7a;
    }
    .brand-bg-hover:hover {
      background-color: #c45f6b;
    }
    .btn-rose {
      background-color: #d76d7a;
      color: white;
      transition: all 0.25s ease;
    }
    .btn-rose:hover {
      background-color: #c45f6b;
      transform: translateY(-1px);
    }
  </style>
</head>

<body class="font-sans text-gray-800">

<!-- 🌸 Navbar -->
<nav class="bg-white shadow border-b border-gray-200 sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">

    <!-- Left side: Logo -->
    <div class="flex items-center space-x-4">
      <img src="logo.png" alt="Seven Dwarfs Logo" class="rounded-full" width="50" height="50">
      <h1 class="text-xl font-semibold brand-accent">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Navigation Links -->
    <ul class="flex flex-wrap justify-center space-x-4 text-sm md:text-base font-medium text-gray-700">
      <li><a href="homepage.php" class="hover:text-rose-500">Home</a></li>
      <li><a href="shop.php" class="hover:text-rose-500">Shop</a></li>
      <li><a href="about.php" class="hover:text-rose-500">About</a></li>
      <li><a href="contact.php" class="hover:text-rose-500">Contact</a></li>
    </ul>

    <!-- Icons -->
    <div class="flex items-center gap-6 relative">
      <!-- 🛒 Cart -->
      <a href="cart.php" class="relative text-gray-700 hover:text-rose-500 transition">
        <i class="fas fa-shopping-cart text-lg"></i>
        <?php if ($cartCount > 0): ?>
          <span class="absolute -top-2 -right-3 bg-rose-600 text-white text-xs font-semibold rounded-full px-2 py-0.5 shadow">
            <?= $cartCount; ?>
          </span>
        <?php endif; ?>
      </a>

      <!-- 👤 Profile / Login -->
      <?php if ($isLoggedIn): ?>
        <div x-data="{ open: false }" class="relative">
          <img src="<?= $avatar ?? 'default-avatar.png' ?>" 
               class="w-8 h-8 rounded-full border cursor-pointer" 
               @click="open=!open">
          <div x-show="open" @click.away="open=false"
               class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <a href="profile.php" class="block px-4 py-2 text-sm hover:bg-pink-100">My Profile</a>
            <a href="purchases.php" class="block px-4 py-2 text-sm hover:bg-pink-100">My Purchases</a>
            <form action="logout.php" method="POST">
              <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-pink-100">Logout</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <button @click="showLogin=true" class="text-gray-700 hover:text-rose-500 transition">
          <i class="fas fa-user-circle text-xl"></i>
        </button>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- 🛒 Main Cart Section -->
<main class="max-w-6xl mx-auto px-4 py-12">
  <h1 class="text-3xl font-bold mb-8 brand-accent">My Cart</h1>

  <?php if (!empty($cartItems)): ?>
    <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100">
      <table class="w-full text-left border-collapse">
        <thead class="bg-gray-100 text-gray-700">
          <tr>
            <th class="p-4">Product</th>
            <th class="p-4">Price</th>
            <th class="p-4">Quantity</th>
            <th class="p-4">Total</th>
            <th class="p-4">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php foreach ($cartItems as $item): ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="p-4 flex items-center gap-3">
                <img src="<?= htmlspecialchars($item['display_image']); ?>" 
                    class="w-14 h-14 object-cover rounded-md border border-gray-200">
                <div>
                  <p class="font-medium text-gray-800"><?= htmlspecialchars($item['product_name']); ?></p>
                  <p class="text-sm text-gray-500">
                    Color: <?= htmlspecialchars($item['color']); ?> | Size: <?= htmlspecialchars($item['size']); ?>
                  </p>
                </div>
              </td>
              <td class="p-4 text-gray-600">₱<?= number_format($item['price_id'], 2); ?></td>
              <td class="p-4 text-gray-600"><?= $item['quantity']; ?></td>
              <td class="p-4 font-semibold brand-accent">₱<?= number_format($item['total'], 2); ?></td>
              <td class="p-4">
                <form action="remove_from_cart.php" method="POST">
                  <input type="hidden" name="cart_id" value="<?= $item['cart_id']; ?>">
                  <button type="submit" 
                          class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md hover:bg-rose-100 hover:text-rose-600 transition">
                    Remove
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="flex justify-between items-center px-6 py-6 bg-gray-50 border-t border-gray-100">
        <p class="text-lg font-semibold">
          Grand Total: <span class="brand-accent">₱<?= number_format($grandTotal, 2); ?></span>
        </p>
        <a href="checkout.php" 
           class="brand-bg text-white px-6 py-3 rounded-lg font-medium shadow-sm brand-bg-hover transition">
          Proceed to Checkout
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="text-center py-20 bg-white rounded-xl shadow-sm border border-gray-100">
      <p class="text-lg text-gray-600">Your cart is currently empty.</p>
      <a href="shop.php" 
         class="mt-6 inline-block brand-bg text-white px-6 py-3 rounded-lg font-medium shadow-sm brand-bg-hover transition">
        Go to Shop
      </a>
    </div>
  <?php endif; ?>
</main>

<!-- 🩶 Footer -->
<footer class="bg-white border-t border-gray-200 mt-12 py-6">
  <div class="max-w-7xl mx-auto text-center text-sm text-gray-500">
    © <?= date('Y'); ?> Seven Dwarfs Boutique — All Rights Reserved.
  </div>
</footer>

</body>
</html>
