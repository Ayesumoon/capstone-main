<?php
session_start();
require 'conn.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$isLoggedIn = true;
$user_id = $_SESSION['customer_id'];

// 🧾 Fetch user info
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, address, profile_picture FROM customers WHERE customer_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 🧠 Fetch all orders for this user
$orderQuery = $conn->prepare("
  SELECT 
      o.order_id, 
      o.total_amount, 
      o.created_at, 
      os.order_status_name AS status_name,
      o.order_status_id,
      pm.payment_method_name
  FROM orders o
  LEFT JOIN order_status os ON o.order_status_id = os.order_status_id
  LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
  WHERE o.customer_id = ?
  ORDER BY o.created_at DESC
");
$orderQuery->bind_param("i", $user_id);
$orderQuery->execute();
$ordersResult = $orderQuery->get_result();
$orders = [];

if ($ordersResult) {
    while ($row = $ordersResult->fetch_assoc()) {
        $orders[] = $row;
    }
} else {
    $orders = [];
}
$orderQuery->close();

// ✅ Group orders by status
$groupedOrders = [
    'pay' => [],
    'ship' => [],
    'receive' => [],
    'rate' => [],
    'history' => []
];

foreach ($orders as $order) {
    switch (strtolower($order['status_name'])) {
        case 'to pay':
            $groupedOrders['pay'][] = $order;
            break;
        case 'to ship':
            $groupedOrders['ship'][] = $order;
            break;
        case 'to receive':
            $groupedOrders['receive'][] = $order;
            break;
        case 'to rate':
            $groupedOrders['rate'][] = $order;
            break;
        default:
            $groupedOrders['history'][] = $order;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Purchases | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    :root {
      --rose-muted: #d37689;
      --rose-hover: #b75f6f;
      --soft-bg: #fef9fa;
    }
    body {
      background-color: var(--soft-bg);
      color: #444;
      font-family: 'Poppins', sans-serif;
    }
    [x-cloak] { display: none !important; }
  </style>
</head>
<body x-data="{ tab: 'pay' }">

<!-- 🌸 Navbar -->
<nav class="bg-white border-b border-gray-200 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">

    <!-- Logo -->
    <div class="flex items-center gap-3">
      <img src="logo.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h1 class="text-xl font-semibold text-[var(--rose-muted)]">Seven Dwarfs Boutique</h1>
    </div>

    <!-- Search -->
    <form action="shop.php" method="get" class="flex-1 max-w-sm mx-4 w-full">
      <input type="text" name="search" placeholder="Search products..."
        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--rose-muted)] outline-none text-sm">
    </form>

    <!-- Right: Nav + Icons -->
    <div class="flex items-center gap-6">
      <ul class="hidden md:flex space-x-5 text-sm font-medium">
        <li><a href="homepage.php" class="hover:text-[var(--rose-muted)]">Home</a></li>
        <li><a href="shop.php" class="hover:text-[var(--rose-muted)]">Shop</a></li>
        <li><a href="about.php" class="hover:text-[var(--rose-muted)]">About</a></li>
        <li><a href="contact.php" class="hover:text-[var(--rose-muted)]">Contact</a></li>
      </ul>

      <!-- Cart -->
      <a href="cart.php" class="text-[var(--rose-muted)] hover:text-[var(--rose-hover)] relative">
        <i class="fa-solid fa-cart-shopping text-lg"></i>
      </a>
      <!-- Profile -->
      <div class="relative" x-data="{ open: false }">
        <?php if ($isLoggedIn): ?>
          <button @click="open = !open" class="hover:text-[var(--rose-hover)]" title="Profile">
            <i class="fa-solid fa-user text-[var(--rose-muted)] text-lg"></i>
          </button>
          <div x-show="open" @click.away="open = false"
               class="absolute right-0 mt-2 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <a href="profile.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Profile</a>
            <a href="purchases.php" class="block px-4 py-2 text-sm hover:bg-[var(--soft-bg)]">My Purchases</a>
            <form action="logout.php" method="POST">
              <button type="submit"
                class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-[var(--soft-bg)]">Logout</button>
            </form>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<!-- 🌿 Layout -->
<div class="max-w-6xl mx-auto mt-10 bg-white shadow-lg rounded-2xl flex flex-col md:flex-row overflow-hidden">

  <!-- Sidebar -->
  <aside class="w-full md:w-1/4 border-r border-gray-100 bg-[#fef9fa] p-6">
    <div class="flex flex-col items-center text-center">
      <img src="<?= htmlspecialchars($user['profile_picture'] ?? 'default-avatar.png') ?>" 
           class="w-24 h-24 rounded-full object-cover mb-3 border-2 border-[var(--rose-muted)]" />
      <p class="text-lg font-semibold"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
      <a href="edit_profile.php" class="text-sm text-[var(--rose-muted)] hover:underline">Edit Profile</a>
    </div>

    <nav class="mt-8 space-y-2 text-sm font-medium">
      <a href="profile.php" class="block py-2 px-3 rounded hover:bg-white hover:text-[var(--rose-muted)] transition">👤 My Profile</a>
      <a href="purchases.php" class="block py-2 px-3 rounded bg-white text-[var(--rose-muted)] font-semibold">🛍 My Purchases</a>
      <a href="#" class="block py-2 px-3 rounded hover:bg-white hover:text-[var(--rose-muted)] transition">🔔 Notifications</a>
      <a href="#" class="block py-2 px-3 rounded hover:bg-white hover:text-[var(--rose-muted)] transition">⚙️ Settings</a>
    </nav>
  </aside>

  <!-- Purchases Content -->
  <section class="w-full md:w-3/4 p-8">
    <h1 class="text-2xl font-bold text-[var(--rose-muted)] mb-6">My Purchases</h1>

    <!-- Tabs -->
    <div class="flex space-x-4 mb-6 border-b border-gray-200">
      <template x-for="(label, key) in {pay:'To Pay', ship:'To Ship', receive:'To Receive', rate:'To Rate', history:'History'}">
        <button 
          @click="tab = key"
          class="pb-2 font-medium border-b-2 transition"
          :class="tab === key 
            ? 'text-[var(--rose-muted)] border-[var(--rose-muted)]' 
            : 'text-gray-600 border-transparent hover:text-[var(--rose-hover)]'">
          <span x-text="label"></span>
        </button>
      </template>
    </div>

    <!-- Orders Display -->
    <?php foreach ($groupedOrders as $key => $list): ?>
      <div x-show="tab === '<?= $key ?>'" class="space-y-4">
        <?php if (empty($list)): ?>
          <p class="text-gray-600 text-sm">No orders under this status.</p>
        <?php else: ?>
          <?php foreach ($list as $order): ?>
            <div class="p-4 border rounded-lg shadow-sm bg-white flex justify-between items-center">
              <div>
                <p class="font-semibold text-[var(--rose-muted)]">Order #<?= htmlspecialchars($order['order_id']); ?></p>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($order['payment_method_name']); ?> • <?= htmlspecialchars($order['status_name']); ?></p>
                <p class="text-gray-500 text-sm"><?= htmlspecialchars($order['created_at']); ?></p>
              </div>
              <div class="text-right">
                <p class="font-bold text-[var(--rose-hover)]">₱<?= number_format($order['total_amount'], 2); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </section>
</div>

<footer class="bg-white border-t border-gray-200 py-6 mt-10 text-center text-sm text-gray-600">
  © <?= date('Y') ?> Seven Dwarfs Boutique | All Rights Reserved.
</footer>

</body>
</html>
