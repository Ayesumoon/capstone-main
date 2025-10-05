<?php
session_start();
require 'conn.php';

$isLoggedIn = isset($_SESSION['customer_id']);
$cartItems = [];
$grandTotal = 0;
$cartCount = 0;

if ($isLoggedIn) {
    $customer_id = $_SESSION['customer_id'];

    $sql = "
    SELECT c.cart_id, c.quantity, p.product_name, p.price_id, p.image_url
    FROM carts c
    JOIN products p ON c.product_id = p.product_id
    WHERE c.customer_id = ? AND c.cart_status = 'active'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
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

<!-- 🔐 Login Modal -->
<div x-show="showLogin" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative">
    <button @click="showLogin = false" 
            class="absolute top-4 right-4 text-gray-400 hover:text-[#d76d7a] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[#d76d7a]">Welcome Back</h2>
    <form action="login_handler.php" method="POST" class="space-y-4">
      <input type="email" name="email" placeholder="Email" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="password" name="password" placeholder="Password" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <button type="submit" class="btn-rose w-full py-3 rounded-lg font-medium">Log In</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Don’t have an account?
      <button @click="showLogin = false; showSignup = true" class="text-[#d76d7a] hover:underline font-medium">Sign up here</button>
    </p>
  </div>
</div>

<!-- 📝 Signup Modal -->
<div x-show="showSignup" x-transition x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 backdrop-blur-sm">
  <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md relative"
       x-data="{ password: '', confirmPassword: '', mismatch: false }">
    <button @click="showSignup = false" 
            class="absolute top-4 right-4 text-gray-400 hover:text-[#d76d7a] text-2xl font-bold">&times;</button>
    <h2 class="text-2xl font-bold mb-6 text-center text-[#d76d7a]">Create Account</h2>
    <form action="signup_handler.php" method="POST" class="space-y-4"
          @submit.prevent="mismatch = password !== confirmPassword; if (!mismatch) $el.submit();">
      <div class="grid grid-cols-2 gap-4">
        <input type="text" name="first_name" placeholder="First Name" required 
               class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
        <input type="text" name="last_name" placeholder="Last Name" required 
               class="border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      </div>
      <input type="email" name="email" placeholder="Email" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="text" name="phone" placeholder="Phone" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="text" name="address" placeholder="Address" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="password" name="password" placeholder="Password" x-model="password" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <input type="password" name="confirm_password" placeholder="Confirm Password" x-model="confirmPassword" required 
             class="w-full border border-gray-300 p-3 rounded-lg focus:ring-2 focus:ring-[#d76d7a] outline-none">
      <template x-if="mismatch"><p class="text-red-500 text-sm -mt-2">⚠ Passwords do not match.</p></template>
      <button type="submit" class="btn-rose w-full py-3 rounded-lg font-medium">Sign Up</button>
    </form>
    <p class="text-sm text-center mt-6 text-gray-600">
      Already have an account?
      <button @click="showSignup = false; showLogin = true" class="text-[#d76d7a] hover:underline font-medium">Log in here</button>
    </p>
  </div>
</div>

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
                <img src="<?= htmlspecialchars($item['image_url']); ?>" 
                     class="w-12 h-12 object-cover rounded-md border border-gray-200">
                <span class="font-medium text-gray-700"><?= htmlspecialchars($item['product_name']); ?></span>
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
