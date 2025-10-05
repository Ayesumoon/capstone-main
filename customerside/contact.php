<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
?> 
<!DOCTYPE html>
<html lang="en" x-data="{ profileOpen: false, showLogin: false, showSignup: false }" 
      @keydown.escape.window="showLogin = false; showSignup = false">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
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
      transition: all 0.25s ease;
    }
    .btn-rose:hover {
      background-color: var(--rose-hover);
      transform: translateY(-1px);
    }
    .nav-link:hover {
      color: var(--rose-muted);
    }
    [x-cloak] { display: none !important; }
  </style>
</head>

<body>

<!-- 🌸 Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap justify-between items-center gap-4">
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose-muted)]">Seven Dwarfs Boutique</h1>
    </div>

    <form action="shop.php" method="get" class="flex flex-1 max-w-lg">
      <input type="text" name="search" placeholder="Search products..."
             class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none text-sm">
    </form>

    <ul class="hidden md:flex space-x-5 text-sm font-medium">
      <li><a href="homepage.php" class="nav-link">Home</a></li>
      <li><a href="shop.php" class="nav-link">Shop</a></li>
      <li><a href="about.php" class="nav-link">About</a></li>
      <li><a href="contact.php" class="text-white bg-[var(--rose-muted)] px-4 py-2 rounded-full hover:bg-[var(--rose-hover)] transition">Contact</a></li>
    </ul>

    <div class="flex items-center gap-5">
      <!-- 🛒 Cart -->
      <a href="cart.php" class="text-[var(--rose-muted)] hover:text-[var(--rose-hover)] relative">
        <i class="fa-solid fa-cart-shopping text-lg"></i>
      </a>

      <!-- 👤 Profile -->
      <div class="relative">
        <?php if ($isLoggedIn): ?>
          <div x-data="{ open: false }">
            <img src="<?= $avatar ?>" class="w-8 h-8 rounded-full border border-[var(--rose-muted)] cursor-pointer" @click="open=!open">
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
          <button @click="showLogin=true" class="text-[var(--rose-muted)] hover:text-[var(--rose-hover)]">
            <i class="fa-solid fa-user text-xl"></i>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>


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

<!-- 💌 Contact Section -->
<section class="max-w-6xl mx-auto my-16 px-6 lg:px-8">
  <div class="grid md:grid-cols-2 gap-10">

    <!-- Contact Info Card -->
    <div class="bg-[var(--rose-muted)] text-white rounded-2xl p-10 shadow-md flex flex-col justify-center space-y-6">
      <h2 class="text-2xl font-semibold">Contact Us</h2>
      <ul class="space-y-4 text-base">
        <li class="flex items-start">
          <i class="fa-solid fa-location-dot mr-3 text-xl mt-1"></i>
          <span>Rizal Avenue, Brgy. Zone 2<br>Bayambang, Pangasinan</span>
        </li>
        <li class="flex items-center">
          <i class="fa-solid fa-envelope mr-3 text-xl"></i>
          <span>regiefernandez16@gmail.com</span>
        </li>
        <li class="flex items-center">
          <i class="fa-solid fa-phone mr-3 text-xl"></i>
          <span>0919 003 4561</span>
        </li>
        <li class="flex items-center">
          <i class="fab fa-facebook text-xl mr-3 text-white"></i>
          <a href="https://www.facebook.com/profile.php?id=61556665545916" 
             target="_blank" class="hover:underline">
            facebook.com/SevenDwarfsBoutique
          </a>
        </li>
      </ul>
    </div>

    <!-- Contact Form -->
    <div class="bg-white rounded-2xl p-10 shadow-md">
      <h2 class="text-2xl font-semibold text-[var(--rose-muted)] mb-2">Get in Touch</h2>
      <p class="text-gray-600 mb-6">We’d love to hear from you. Send us a message below!</p>
      <form action="send_message.php" method="POST" class="space-y-4">
        <?php if (!$isLoggedIn): ?>
          <input type="text" name="name" placeholder="Your Name" required 
                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
          <input type="email" name="email" placeholder="Your Email" required 
                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-[var(--rose-muted)] outline-none">
        <?php endif; ?>
        <textarea name="message" placeholder="Type your message here..." required 
                  class="w-full border border-gray-300 rounded-lg px-4 py-3 h-32 resize-none focus:ring-2 focus:ring-[var(--rose-muted)] outline-none"></textarea>
        <button type="submit" class="btn-rose w-full py-3 rounded-lg font-medium shadow-md">Send Message</button>
      </form>
    </div>
  </div>
</section>

<!-- 🌿 Footer -->
<footer class="bg-white border-t border-gray-200 py-6 text-center text-sm text-gray-600">
  <p>&copy; 2025 Seven Dwarfs Boutique | All Rights Reserved</p>
</footer>

</body>
</html>
