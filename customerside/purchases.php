<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$isLoggedIn = true;
$user_id = $_SESSION['customer_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, address, profile_picture FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Purchases</title>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-pink-50 text-gray-800" x-data="{ showLogin: false, showSignup: false, cartCount: 0 }" @keydown.escape.window="showLogin = false; showSignup = false">

<!-- Navbar -->
<nav class="bg-pink-100 shadow-md">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">
    
    <!-- Logo -->
    <div class="flex items-center space-x-4">
      <img src="logo.png" alt="Logo" class="rounded-full w-14 h-14">
      <h1 class="text-2xl font-bold text-pink-600">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Search Bar -->
    <form action="shop.php" method="get" class="flex-1 max-w-sm">
      <input type="text" name="search" placeholder="Search products..." class="w-full px-3 py-2 border border-pink-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-400 text-sm">
    </form>

    <!-- Right Side Nav -->
    <div class="flex items-center gap-6">
      <ul class="flex space-x-4 text-sm md:text-base">
        <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
        <li><a href="shop.php" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-full">Shop</a></li>
        <li><a href="about.php" class="hover:text-pink-500">About</a></li>
        <li><a href="contact.php" class="hover:text-pink-500">Contact</a></li>
      </ul>

      <!-- Cart Icon -->
      <a href="cart.php" title="Cart" class="hover:text-pink-500">
        <svg class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h11.1a1 1 0 001-.8l1.4-5.2H7zM7 13l-1-4H4" />
        </svg>
      </a>

      <!-- Profile Dropdown -->
      <div class="relative" x-data="{ open: false }">
        <?php if ($isLoggedIn): ?>
          <button @click="open = !open" class="hover:text-pink-500" title="Profile">
            <svg class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
          <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-50">
            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Profile</a>
            <a href="purchases.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Purchases</a>
            <form action="logout.php" method="POST">
              <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-pink-100">Logout</button>
            </form>
          </div>
        <?php else: ?>
          <button @click="showLogin = true" class="hover:text-pink-500">
            <svg class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- Main Section -->
<div class="max-w-6xl mx-auto mt-10 bg-white shadow-xl rounded-2xl flex">
  <!-- Sidebar -->
  <aside class="w-1/4 border-r border-gray-200 p-6">
    <div class="flex flex-col items-center text-center">
      <img src="<?= htmlspecialchars($user['profile_picture'] ?? 'default-avatar.png') ?>" class="w-20 h-20 rounded-full object-cover mb-2" />
      <p class="text-lg font-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
      <a href="edit_profile.php" class="text-sm text-pink-500 hover:underline">Edit Profile</a>
    </div>

    <!-- Sidebar Links -->
    <div class="mt-8 space-y-2 text-sm">
      <div x-data="{ open: false }">
        <button @click="open = !open" class="w-full text-left text-pink-600 font-medium py-2 px-4 rounded-md hover:bg-pink-100 focus:outline-none">
          Account Settings
        </button>
        <div x-show="open" x-cloak class="space-y-2 pl-4">
          <a href="profile.php" class="text-pink-600 font-medium hover:text-pink-800 block">Profile</a>
          <a href="#" class="hover:text-pink-600 block">Banks & Cards</a>
          <a href="#" class="hover:text-pink-600 block">Addresses</a>
          <a href="#" class="hover:text-pink-600 block">Change Password</a>
          <a href="#" class="hover:text-pink-600 block">Privacy Settings</a>
          <a href="#" class="hover:text-pink-600 block">Notification Settings</a>
        </div>
      </div>
      <a href="purchases.php" class="flex items-center space-x-2 hover:text-pink-600 font-medium">
        <span>My Purchase</span>
      </a>
      <a href="#" class="flex items-center space-x-2 hover:text-pink-600">
        <span>Notifications</span>
      </a>
    </div>
  </aside>

  <!-- Purchases Content -->
  <section class="flex-1 p-6" x-data="{ tab: 'pay' }">
    <h1 class="text-2xl font-bold mb-4 text-pink-600">My Purchases</h1>

    <!-- Tabs -->
    <div class="flex space-x-4 mb-6 border-b pb-2">
      <button @click="tab = 'pay'" :class="tab === 'pay' ? 'text-pink-600 border-b-2 border-pink-600' : 'text-gray-600'" class="pb-2 font-medium">To Pay</button>
      <button @click="tab = 'ship'" :class="tab === 'ship' ? 'text-pink-600 border-b-2 border-pink-600' : 'text-gray-600'" class="pb-2 font-medium">To Ship</button>
      <button @click="tab = 'receive'" :class="tab === 'receive' ? 'text-pink-600 border-b-2 border-pink-600' : 'text-gray-600'" class="pb-2 font-medium">To Receive</button>
      <button @click="tab = 'rate'" :class="tab === 'rate' ? 'text-pink-600 border-b-2 border-pink-600' : 'text-gray-600'" class="pb-2 font-medium">To Rate</button>
      <button @click="tab = 'history'" :class="tab === 'history' ? 'text-pink-600 border-b-2 border-pink-600' : 'text-gray-600'" class="pb-2 font-medium">History</button>
    </div>

    <!-- Tab Content -->
    <div x-show="tab === 'pay'" class="space-y-4"><p class="text-gray-700">You have no orders to pay at the moment.</p></div>
    <div x-show="tab === 'ship'" class="space-y-4"><p class="text-gray-700">You have no items to be shipped.</p></div>
    <div x-show="tab === 'receive'" class="space-y-4"><p class="text-gray-700">You have no items to receive.</p></div>
    <div x-show="tab === 'rate'" class="space-y-4"><p class="text-gray-700">You have no items to rate.</p></div>
    <div x-show="tab === 'history'" class="space-y-4"><p class="text-gray-700">Your past purchase history will appear here.</p></div>
  </section>
</div>

</body>
</html>
