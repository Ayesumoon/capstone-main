<?php
require 'conn.php';
include 'auth_session.php';

// ✅ Get admin info
$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details
if ($admin_id) {
    $query = "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $stmt->close();
}

// ✅ Fetch current system settings
$settingsRes = $conn->query("SELECT * FROM store_settings LIMIT 1");
$settings = $settingsRes->fetch_assoc();

// ✅ Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_name = trim($_POST['store_name']);
    $store_email = trim($_POST['store_email']);
    $timezone_locale = trim($_POST['timezone_locale']);
    $theme = trim($_POST['theme']);

    $update = $conn->prepare("
        UPDATE store_settings 
        SET store_name = ?, store_email = ?, timezone_locale = ?, theme = ?
        WHERE id = 1
    ");
    $update->bind_param("ssss", $store_name, $store_email, $timezone_locale, $theme);
    $update->execute();
    $update->close();

    $success = "✅ Settings updated successfully!";
    header("Refresh:1"); // Reload to reflect changes
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>System Settings | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --rose: #e5a5b2;
      --rose-hover: #d48b98;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
      color: #374151;
    }
    [x-cloak] { display: none !important; }
    .active-link {
      background-color: #fef3f5;
      color: var(--rose);
      font-weight: 600;
      border-radius: 0.5rem;
    }
  </style>
</head>

<body class="text-sm" x-data="{ userMenu:false, productMenu:false }">
<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white shadow-md">
    <div class="p-5 border-b">
      <div class="flex items-center gap-3">
        <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
        <h2 class="text-lg font-semibold text-[var(--rose)]">SevenDwarfs</h2>
      </div>
      <div class="mt-4 flex items-center gap-3">
        <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
        <div>
          <h3 class="text-sm font-semibold"><?= htmlspecialchars($admin_name); ?></h3>
          <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
        </div>
      </div>
    </div>

    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
      <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
      </button>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
        <li><a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a></li>
        <li><a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customers</a></li>
      </ul>

      <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
      </button>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
        <li><a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
      </ul>

      <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="cashier_sales_report.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>
      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_settings.php" class="block px-4 py-2 active-link"><i class="fas fa-cogs mr-2"></i>System Settings</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>
      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded transition"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a>
    </nav>
  </aside>

  <!-- 🌸 Main Content -->
  <main class="flex-1 p-8 bg-gray-50 overflow-auto">
    <div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm flex justify-between items-center">
      <h1 class="text-2xl font-semibold">System Settings</h1>
    </div>

    <section class="bg-white p-6 rounded-b-2xl shadow mt-6 max-w-3xl mx-auto">
      <?php if (!empty($success)): ?>
        <div class="bg-green-100 text-green-700 p-2 mb-4 rounded"><?= $success ?></div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <div>
          <label class="block text-gray-700 font-medium">Store Name</label>
          <input type="text" name="store_name" value="<?= htmlspecialchars($settings['store_name'] ?? '') ?>" required class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div>
          <label class="block text-gray-700 font-medium">Store Email</label>
          <input type="email" name="store_email" value="<?= htmlspecialchars($settings['store_email'] ?? '') ?>" required class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div>
          <label class="block text-gray-700 font-medium">Timezone Locale</label>
          <input type="text" name="timezone_locale" value="<?= htmlspecialchars($settings['timezone_locale'] ?? 'Asia/Manila') ?>" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
        </div>
        <div>
          <label class="block text-gray-700 font-medium">Theme</label>
          <select name="theme" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
            <option value="light" <?= ($settings['theme'] ?? '') === 'light' ? 'selected' : '' ?>>Light</option>
            <option value="dark" <?= ($settings['theme'] ?? '') === 'dark' ? 'selected' : '' ?>>Dark</option>
          </select>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-6 py-2 rounded-lg shadow">Save Changes</button>
        </div>
      </form>
    </section>
  </main>
</div>
</body>
</html>
