<?php
require 'conn.php';
require 'auth_session.php';


$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details if logged in
if ($admin_id) {
    $query = "
        SELECT 
            CONCAT(first_name, ' ', last_name) AS full_name, 
            r.role_name 
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
}


$suppliers = $conn->query("SELECT * FROM suppliers");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>Suppliers Information | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
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

<body class="text-sm" x-data="{ userMenu: false, productMenu: false }">
<div class="flex min-h-screen">

  <!-- 🧭 Sidebar -->
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

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 rounded hover:bg-gray-100 transition"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>

      <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100 rounded transition">
        <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
        <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
      </button>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm space-y-1">
        <li><a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Manage Users</a></li>
        <a href="manage_roles.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-id-badge mr-2"></i>Manage Roles</a>
        <li><a href="customers.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-users mr-2"></i>Customer</a></li>
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
      <a href="suppliers.php" class="block px-4 py-2 active-link"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>
      <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-50 rounded transition"><i class="fas fa-sign-out-alt mr-2"></i>Log out</a>
    </nav>
  </aside>

  <!-- 🌸 Main Content -->
  <main class="flex-1 p-8 bg-gray-50 overflow-auto">
    <div class="bg-[var(--rose)] text-white p-5 rounded-t-2xl shadow-sm flex justify-between items-center">
      <h1 class="text-2xl font-semibold">Suppliers Information</h1>

      <!-- Add Supplier Button -->
      <div x-data="{ addModal: false }">
        <button 
          @click="addModal = true"
          class="flex items-center gap-2 bg-[var(--rose-hover)] hover:bg-[var(--rose)] text-white px-4 py-2 rounded-lg shadow transition"
        >
          <i class="fas fa-plus"></i> Add Supplier
        </button>

        <!-- Add Supplier Modal -->
        <div x-show="addModal" @click.away="addModal = false" x-transition.opacity x-cloak
             class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-6 transform transition-all">
          <h2 class="text-xl font-semibold text-[#c97f91] mb-4">Add Supplier</h2>

            <form action="add_supplier.php" method="POST" class="space-y-4">
              <div>
                <label class="block text-gray-700 font-medium">Supplier Name</label>
                <input type="text" name="supplier_name" required class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
              </div>

              <div>
                <label class="block text-gray-700 font-medium">Category</label>
                <select name="category_id" required class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
                  <option disabled selected>Select Category</option>
                  <?php
                    require 'conn.php';
                    $catRes = $conn->query("SELECT * FROM categories");
                    while ($cat = $catRes->fetch_assoc()):
                  ?>
                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>

              <div>
                <label class="block text-gray-700 font-medium">Email</label>
                <input type="email" name="supplier_email" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
              </div>

              <div>
                <label class="block text-gray-700 font-medium">Phone</label>
                <input type="text" name="supplier_phone" class="w-full border p-2 rounded-lg focus:ring-2 focus:ring-[var(--rose)]">
              </div>

              <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="addModal=false" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-md">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-[var(--rose-hover)] hover:bg-[var(--rose)] text-white rounded-md">Save</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Supplier Table -->
    <section class="bg-white p-6 rounded-b-2xl shadow mt-6">
      <div class="flex justify-between items-center mb-4">
        <div class="text-gray-700 font-medium">All Registered Suppliers</div>
        <div class="flex items-center gap-2">
          <label for="search" class="text-sm text-gray-600">Search:</label>
          <input id="search" name="search" type="search"
                 class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 rounded-lg text-sm">
          <thead class="bg-gray-100 text-gray-600 uppercase text-xs font-semibold">
            <tr>
              <th class="px-4 py-3 text-left">ID</th>
              <th class="px-4 py-3 text-left">Category</th>
              <th class="px-4 py-3 text-left">Suppliers</th>
              <th class="px-4 py-3 text-left">Reg Date</th>
              <th class="px-4 py-3 text-left">Email</th>
              <th class="px-4 py-3 text-left">Phone</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 text-gray-700">
            <?php
              require 'conn.php';
              $sql = "SELECT s.*, c.category_name FROM suppliers s LEFT JOIN categories c ON s.category_id = c.category_id";
              $result = $conn->query($sql);
              if ($result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
            ?>
            <tr class="hover:bg-gray-50 transition">
              <td class="px-4 py-2"><?= htmlspecialchars($row['supplier_id']); ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['category_name']); ?></td>
              <td class="px-4 py-2 font-semibold text-gray-800"><?= htmlspecialchars($row['supplier_name']); ?></td>
              <td class="px-4 py-2"><?= date('d/m/Y', strtotime($row['reg_date'] ?? $row['created_at'] ?? '')); ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['supplier_email']); ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['supplier_phone']); ?></td>
              <td class="px-4 py-2 flex gap-2">
                <!-- Edit -->
                <div x-data="{ open: false }">
                  <button @click="open = true" class="text-green-600 hover:text-green-700 border border-green-300 rounded px-2 py-1" title="Edit">
                    <i class="fas fa-edit"></i>
                  </button>
                  <div x-show="open" @click.away="open = false" x-transition.opacity x-cloak
                       class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
                      <h2 class="text-lg font-semibold text-[var(--rose)] mb-3">Edit Supplier</h2>
                      <form action="update_supplier.php" method="POST" class="space-y-3">
                        <input type="hidden" name="supplier_id" value="<?= $row['supplier_id'] ?>">
                        <div>
                          <label class="block text-gray-700 font-medium">Category</label>
                          <select name="category_id" class="w-full border p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
                            <?php
                              $catRes = $conn->query("SELECT * FROM categories");
                              while ($cat = $catRes->fetch_assoc()):
                                $selected = ($cat['category_id'] == $row['category_id']) ? 'selected' : '';
                            ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $selected ?>>
                              <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                            <?php endwhile; ?>
                          </select>
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium">Supplier Name</label>
                          <input type="text" name="supplier_name" value="<?= htmlspecialchars($row['supplier_name']) ?>" class="w-full border p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium">Email</label>
                          <input type="email" name="supplier_email" value="<?= htmlspecialchars($row['supplier_email']) ?>" class="w-full border p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
                        </div>
                        <div>
                          <label class="block text-gray-700 font-medium">Phone</label>
                          <input type="text" name="supplier_phone" value="<?= htmlspecialchars($row['supplier_phone']) ?>" class="w-full border p-2 rounded focus:ring-2 focus:ring-[var(--rose)]">
                        </div>
                        <div class="flex justify-end gap-2">
                          <button type="button" @click="open=false" class="bg-gray-200 px-4 py-2 rounded hover:bg-gray-300">Cancel</button>
                          <button type="submit" class="bg-[var(--rose-hover)] text-white px-4 py-2 rounded hover:bg-[var(--rose)]">Save</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Delete -->
                <a href="delete_supplier.php?id=<?= $row['supplier_id'] ?>" 
                   onclick="return confirm('Are you sure you want to delete this supplier?')"
                   class="text-red-600 hover:text-red-700 border border-red-300 rounded px-2 py-1" title="Delete">
                  <i class="fas fa-trash-alt"></i>
                </a>
              </td>
            </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="7" class="text-center text-gray-500 py-6">No suppliers found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
