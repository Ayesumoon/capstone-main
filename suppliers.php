<?php
require 'conn.php';
include 'auth_session.php';

// ======================================
// FETCH ADMIN DETAILS
// ======================================

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $query = "
        SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
    ";
    $adminStmt = $conn->prepare($query);
    $adminStmt->bind_param("i", $admin_id);
    $adminStmt->execute();
    $result = $adminStmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $adminStmt->close();
}


// ======================================
// ADD SUPPLIER (NO MORE CATEGORY FIELD)
// ======================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $supplier_name  = trim($_POST['supplier_name']);
    $supplier_email = trim($_POST['supplier_email'] ?? '');
    $supplier_phone = trim($_POST['supplier_phone'] ?? '');

    if (!empty($supplier_name)) {

        $sql = "INSERT INTO suppliers (supplier_name, supplier_email, supplier_phone, created_at)
                VALUES (?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $supplier_name, $supplier_email, $supplier_phone);
        $stmt->execute();
        $stmt->close();

        header("Location: suppliers.php?success=1");
        exit;
    }
}


// ======================================
// FETCH SUPPLIERS
// ======================================
$suppliers = $conn->query("
    SELECT supplier_id, supplier_name, supplier_email, supplier_phone, created_at
    FROM suppliers
    ORDER BY created_at ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Suppliers | Seven Dwarfs Boutique</title>
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

<body class="text-sm" x-data="{ userMenu:false, productMenu:false, addModal:false }">
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
      <a href="dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-100 transition"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>

      <!-- User Management -->
      <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
        <i class="fas fa-chevron-down" :class="{ 'rotate-180': userMenu }"></i>
      </button>
      <ul x-show="userMenu" x-transition class="pl-8 space-y-1 text-sm">
        <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
        <li><a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a></li>
        <li><a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customers</a></li>
      </ul>

      <!-- Product Management -->
      <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
        <i class="fas fa-chevron-down" :class="{ 'rotate-180': productMenu }"></i>
      </button>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li><a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li><a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
        <li><a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
      </ul>

      <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="cashier_sales_report.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>

      <a href="suppliers.php" class="block px-4 py-2 active-link"><i class="fas fa-industry mr-2"></i>Suppliers</a>

      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>
      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded transition"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a>
    </nav>
  </aside>

  <!-- Main Content Area -->
  <main class="flex-1 p-8 bg-gray-50 overflow-auto">

    <!-- Status Messages -->
    <?php if (isset($_GET['success'])): ?>
      <div class="bg-green-100 text-green-700 px-4 py-2 rounded-lg mb-4">
        ✅ Supplier added successfully.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
      <div class="bg-green-100 text-green-700 px-4 py-2 rounded-lg mb-4">
        🗑 Supplier deleted successfully.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="bg-red-100 text-red-700 px-4 py-2 rounded-lg mb-4">
        ⚠ <?= htmlspecialchars($_GET['error']); ?>
      </div>
    <?php endif; ?>

    <div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm flex justify-between items-center">
      <h1 class="text-2xl font-semibold">Suppliers Information</h1>
      <button @click="addModal = true" class="flex items-center gap-2 bg-[var(--rose-hover)] hover:bg-[var(--rose)] text-white px-4 py-2 rounded-lg shadow transition">
        <i class="fas fa-plus"></i> Add Supplier
      </button>
    </div>

    <!-- ADD SUPPLIER MODAL -->
    <div x-show="addModal" x-cloak @click.self="addModal=false"
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6">
        <h2 class="text-xl font-semibold text-[var(--rose-hover)] mb-4">Add Supplier</h2>

        <form action="" method="POST" class="space-y-4">
          <div>
            <label class="block font-medium text-gray-700">Supplier Name</label>
            <input type="text" name="supplier_name" required class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
          </div>

          <div>
            <label class="block font-medium text-gray-700">Email</label>
            <input type="email" name="supplier_email" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
          </div>

          <div>
            <label class="block font-medium text-gray-700">Phone</label>
            <input type="text" name="supplier_phone" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
          </div>

          <div class="flex justify-end gap-3">
            <button type="button" @click="addModal=false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-[var(--rose-hover)] hover:bg-[var(--rose)] text-white rounded-md">Save</button>
          </div>
        </form>
      </div>
    </div>

    <!-- SUPPLIERS TABLE -->
    <section class="bg-white p-6 rounded-b-2xl shadow">
      <h2 class="text-gray-700 font-medium mb-4">All Registered Suppliers</h2>

      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg text-sm">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
            <tr>
              <th class="px-4 py-3 text-left">Supplier</th>
              <th class="px-4 py-3 text-left">Date Added</th>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-left">Phone</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100 text-gray-700">
            <?php if ($suppliers->num_rows > 0): ?>
              <?php while ($row = $suppliers->fetch_assoc()): ?>
                <tr class="hover:bg-gray-50 transition">
                  <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($row['supplier_name']); ?></td>
                  <td class="px-4 py-2"><?= date('M d, Y', strtotime($row['created_at'])); ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($row['supplier_email']); ?></td>
                  <td class="px-4 py-2"><?= htmlspecialchars($row['supplier_phone']); ?></td>

                  <td class="px-4 py-2 flex gap-2">
                    <a href="edit_supplier.php?id=<?= $row['supplier_id'] ?>" 
                       class="text-green-600 hover:text-green-700 border border-green-300 rounded px-2 py-1" title="Edit">
                      <i class="fas fa-edit"></i>
                    </a>

                    <a href="delete_supplier.php?id=<?= $row['supplier_id'] ?>" 
                       onclick="return confirm('Delete this supplier?')" 
                       class="text-red-600 hover:text-red-700 border border-red-300 rounded px-2 py-1" title="Delete">
                      <i class="fas fa-trash-alt"></i>
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5" class="text-center py-6 text-gray-500">No suppliers found.</td></tr>
            <?php endif; ?>
          </tbody>

        </table>
      </div>
    </section>

  </main>
</div>
</body>
</html>
