<?php
session_start();
include '../conn.php'; // adjust path if needed

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user information
$user_id = $_SESSION['customer_id'];
$query = "SELECT * FROM customers WHERE customer_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    echo "User not found.";
    exit();
}

// Check login status for UI (set $isLoggedIn manually)
$isLoggedIn = isset($_SESSION['customer_id']);
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Edit Profile</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <style>[x-cloak] { display: none !important; }</style>
</head>
<body x-data="{ showLogin: false, showSignup: false }" class="bg-pink-50 text-gray-800 font-sans">

<!-- Navbar -->
<nav class="bg-pink-100 shadow-md">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">

    <!-- Left: Logo -->
    <div class="flex items-center space-x-4">
      <img src="logo.png" alt="Logo" class="rounded-full" width="60" height="50">
      <h1 class="text-2xl font-bold text-pink-600">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Center: Search -->
    <form action="shop.php" method="get" class="flex-1 max-w-sm mx-4 w-full">
      <input type="text" name="search" placeholder="Search products..."
        class="w-full px-3 py-2 border border-pink-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-400 text-sm">
    </form>

    <!-- Right: Links and Icons -->
    <div class="flex items-center space-x-4">
      <!-- Navigation Links -->
      <ul class="hidden md:flex space-x-4 text-sm md:text-base">
        <li><a href="homepage.php" class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-full transition">Home</a></li>
        <li><a href="shop.php" class="hover:text-pink-500">Shop</a></li>
        <li><a href="about.php" class="hover:text-pink-500">About</a></li>
        <li><a href="contact.php" class="hover:text-pink-500">Contact</a></li>
      </ul>

      <!-- Cart Icon -->
      <a href="cart.php" class="hover:text-pink-500" title="Cart">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h11.1a1 1 0 001-.8l1.4-5.2H7zm0 0l-1-4H4" />
        </svg>
      </a>

      <!-- Profile Dropdown -->
      <div class="relative" x-data="{ open: false }">
        <button @click="open = !open" class="hover:text-pink-500" title="Profile">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>
        <?php if ($isLoggedIn): ?>
        <div x-show="open" @click.away="open = false" x-cloak
          class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Profile</a>
          <a href="purchases.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Purchases</a>
          <form action="logout.php" method="POST">
            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-pink-100">Logout</button>
          </form>
        </div>
        <?php else: ?>
        <div x-show="open" @click.away="open = false" x-cloak
          class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <button @click="showLogin = true; open = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">Login</button>
          <button @click="showSignup = true; open = false" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">Sign Up</button>
        </div>
        <?php endif; ?>
      </div>
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

<div class="max-w-6xl mx-auto mt-10 bg-white shadow-xl rounded-2xl flex overflow-hidden">
  <!-- Sidebar Navigation -->
  <aside class="w-1/4 border-r border-gray-200 p-6">
    <div class="flex flex-col items-center text-center">
      <img src="<?= htmlspecialchars($user['profile_picture'] ?? 'default-avatar.png') ?>" class="w-20 h-20 rounded-full object-cover mb-2" />
      <p class="text-lg font-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
      <a href="edit_profile.php" class="text-sm text-pink-500 hover:underline">Edit Profile</a>
    </div>

    <!-- Dropdown Navigation -->
    <div x-data="{ open: false }" class="mt-8 space-y-4 text-sm">
      <button @click="open = !open" class="w-full text-left text-pink-600 font-medium py-2 px-4 rounded-md hover:bg-pink-100">
        Account Settings
      </button>
      <div x-show="open" x-cloak class="space-y-2 pl-4">
        <a href="profile.php" class="block text-pink-600 font-medium hover:text-pink-800">Profile</a>
        <a href="#" class="block hover:text-pink-600">Banks & Cards</a>
        <a href="#" class="block hover:text-pink-600">Addresses</a>
        <a href="#" class="block hover:text-pink-600">Change Password</a>
        <a href="#" class="block hover:text-pink-600">Privacy Settings</a>
        <a href="#" class="block hover:text-pink-600">Notification Settings</a>
      </div>
      <a href="purchases.php" class="block text-pink-600 font-medium hover:text-pink-800">My Purchase</a>
      <a href="#" class="block text-gray-700 hover:text-pink-600">Notifications</a>
    </div>
  </aside>

  <!-- Profile Form -->
  <main class="w-3/4 bg-white p-10">
    <h2 class="text-2xl font-bold text-pink-600 mb-8">My Profile</h2>
    <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-6">
      <!-- Hidden file input -->
      <input type="file" name="profile_picture" id="profileInput" accept="image/*" class="hidden" onchange="previewImage(event)">

      <!-- Full Name Display -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
        <input 
          type="text" 
          value="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>" 
          disabled 
          class="w-full bg-gray-100 border border-gray-300 p-3 rounded-md"
        >
      </div>

      <!-- Editable Name -->
      <div>
        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">Name</label>
        <input 
          type="text" 
          name="first_name" 
          id="first_name" 
          value="<?= htmlspecialchars($user['first_name']) ?>" 
          class="w-full border p-3 rounded-md focus:ring-2 focus:ring-pink-500"
        >
      </div>

      <!-- Email -->
      <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
        <input 
          type="email" 
          value="<?= htmlspecialchars($user['email']) ?>" 
          disabled 
          class="w-full bg-gray-100 border border-gray-300 p-3 rounded-md"
        >
      </div>

      <!-- Phone -->
      <div>
        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
        <input 
          type="text" 
          name="phone" 
          id="phone" 
          value="<?= htmlspecialchars($user['phone']) ?>" 
          class="w-full border p-3 rounded-md focus:ring-2 focus:ring-pink-500"
        >
      </div>

      <!-- Address -->
      <div class="md:col-span-2">
        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
        <input 
          type="text" 
          name="address" 
          id="address" 
          value="<?= htmlspecialchars($user['address']) ?>" 
          class="w-full border p-3 rounded-md focus:ring-2 focus:ring-pink-500"
        >
      </div>

      <!-- Actions -->
      <div class="md:col-span-2 flex justify-end gap-4 mt-6">
        <a href="profile.php" class="text-sm text-pink-600 hover:underline">Cancel</a>
        <button type="submit" class="bg-pink-600 text-white px-6 py-2 rounded-md hover:bg-pink-700 transition">
          Save Changes
        </button>
      </div>
    </form>
  </main>
</div>

<script>
function previewImage(event) {
  const reader = new FileReader();
  reader.onload = function () {
    const output = document.getElementById('profilePreview');
    output.src = reader.result;
  };
  reader.readAsDataURL(event.target.files[0]);
}
</script>


</body>
</html>
