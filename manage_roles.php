<?php
session_start();
require 'conn.php';

// 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// 🧩 Fetch current admin details
$stmt = $conn->prepare("
    SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, r.role_name 
    FROM adminusers a 
    LEFT JOIN roles r ON a.role_id = r.role_id
    WHERE a.admin_id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_name = $admin['full_name'] ?? "Admin";
$admin_role = $admin['role_name'] ?? "Administrator";

// ➕ Add Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $role_name = trim($_POST['role_name']);
    if (!empty($role_name)) {
        $stmt = $conn->prepare("INSERT INTO roles (role_name) VALUES (?)");
        $stmt->bind_param("s", $role_name);
        $stmt->execute();
        $_SESSION['success'] = "Role added successfully!";
        $stmt->close();
    } else {
        $_SESSION['message'] = "Role name cannot be empty!";
    }
    header("Location: manage_roles.php");
    exit();
}

// ✏️ Edit Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_role'])) {
    $role_id = intval($_POST['role_id']);
    $role_name = trim($_POST['role_name']);

    if ($role_id == 2) {
        $_SESSION['message'] = "Admin role cannot be renamed!";
    } else {
        $stmt = $conn->prepare("UPDATE roles SET role_name = ? WHERE role_id = ?");
        $stmt->bind_param("si", $role_name, $role_id);
        $stmt->execute();
        $_SESSION['success'] = "Role updated successfully!";
        $stmt->close();
    }
    header("Location: manage_roles.php");
    exit();
}

// 🗑️ Delete Role
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    $role_id = intval($_POST['role_id']);

    if ($role_id == 2) {
        $_SESSION['message'] = "Admin role cannot be deleted!";
    } else {

        // Check if role is assigned to users
        $check = $conn->prepare("SELECT COUNT(*) FROM adminusers WHERE role_id = ?");
        $check->bind_param("i", $role_id);
        $check->execute();
        $check->bind_result($count);
        $check->fetch();
        $check->close();

        if ($count > 0) {
            $_SESSION['message'] = "Cannot delete role assigned to users!";
        } else {
            $stmt = $conn->prepare("DELETE FROM roles WHERE role_id = ?");
            $stmt->bind_param("i", $role_id);
            $stmt->execute();
            $_SESSION['success'] = "Role deleted successfully!";
            $stmt->close();
        }
    }
    header("Location: manage_roles.php");
    exit();
}

// 📌 Fetch Roles
$roles = $conn->query("SELECT * FROM roles ORDER BY role_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Roles | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background: #f9fafb; color: #374151; }
  </style>
</head>

<body class="min-h-screen flex bg-gray-100">


<!-- 🧭 Sidebar -->
<aside id="sidebar" class="w-64 bg-white shadow-lg flex flex-col justify-between transition-all duration-300">
  <div class="p-4">
    <div class="flex items-center space-x-3">
      <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h2 class="text-lg font-bold text-[var(--rose)]">SevenDwarfs</h2>
    </div>

    <div class="mt-4 flex items-center space-x-3 border-t pt-3">
      <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full">
      <div>
        <p class="font-semibold"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <nav class="mt-6 space-y-1" x-data="{ userMenu: true, productMenu: false }">
      <a href="dashboard.php" class="block px-4 py-2 hover:bg-gray-100">
        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
      </a>

      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100">
          <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <div x-show="userMenu" x-transition class="pl-8 space-y-1">
          <a href="manage_users.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-user mr-2"></i>Users</a>
          <a href="manage_roles.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md"><i class="fas fa-id-badge mr-2"></i>Roles</a>
        </div>
      </div>

      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-2 flex justify-between items-center hover:bg-gray-100">
          <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
          <i class="fas fa-chevron-down" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <div x-show="productMenu" x-transition class="pl-8 space-y-1">
          <a href="categories.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-tags mr-2"></i>Category</a>
          <a href="products.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-box mr-2"></i>Products</a>
          <a href="inventory.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-warehouse mr-2"></i>Inventory</a>
          <a href="stock_management.php" class="block py-1 hover:text-[var(--rose)]"><i class="fas fa-boxes mr-2"></i>Stock Management</a>
        </div>
      </div>

      <a href="orders.php" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
            <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-gray-100 transitio"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>

      <a href="suppliers.php" class="block px-4 py-2 hover:bg-gray-100"><i class="fas fa-industry mr-2"></i>Suppliers</a>

      <a href="system_logs.php" class="block px-4 py-2 hover:bg-gray-100 rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>

      <a href="logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
    </nav>
  </div>
</aside>

<!-- 🧩 Main Content -->
<main class="flex-1 p-8">
  <header class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Manage Roles</h1>
    <button onclick="openModal('addModal')" class="bg-[var(--rose)] text-white px-4 py-2 rounded-lg hover:bg-[var(--rose-hover)] transition">
      <i class="fas fa-plus mr-2"></i>Add Role
    </button>
  </header>

  <!-- Flash Messages -->
  <?php if (isset($_SESSION['message'])): ?>
    <div class="mb-4 px-4 py-2 bg-red-100 text-red-700 rounded shadow-sm"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['success'])): ?>
    <div class="mb-4 px-4 py-2 bg-green-100 text-green-700 rounded shadow-sm"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
  <?php endif; ?>

  <!-- Roles Table -->
  <section class="bg-white p-6 rounded-xl shadow-md">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">🧩 Roles List</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-200 rounded-lg text-sm">
        <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left">Role ID</th>
            <th class="px-4 py-3 text-left">Role Name</th>
            <th class="px-4 py-3 text-center">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
          <?php while ($role = $roles->fetch_assoc()): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3"><?= $role['role_id']; ?></td>
              <td class="px-4 py-3"><?= htmlspecialchars($role['role_name']); ?></td>

              <!-- FIXED SPACING (THIS PART ONLY) -->
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-4">

                  <!-- Edit Icon -->
                  <button onclick="openEditModal(<?= $role['role_id']; ?>, '<?= $role['role_name']; ?>')" 
                          class="text-blue-500 hover:text-blue-700 text-base">
                    <i class="fas fa-pen-to-square"></i>
                  </button>

                  <?php if ($role['role_id'] != 2): ?>
                    <!-- Delete Icon -->
                    <button onclick="openDeleteModal(<?= $role['role_id']; ?>)" 
                            class="text-red-500 hover:text-red-700 text-base">
                      <i class="fas fa-trash"></i>
                    </button>
                  <?php endif; ?>

                </div>
              </td>

            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<!-- ➕ Add Role Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-xl shadow-lg w-96">
    <h2 class="text-xl font-semibold mb-4">Add New Role</h2>
    <form method="POST">
      <input type="text" name="role_name" placeholder="Role Name" required 
             class="w-full border border-gray-300 rounded-lg p-2 mb-4 focus:ring-2 focus:ring-[var(--rose)]">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
        <button type="submit" name="add_role" class="px-4 py-2 bg-[var(--rose)] text-white rounded-lg hover:bg-[var(--rose-hover)]">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ✏️ Edit Role Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-xl shadow-lg w-96">
    <h2 class="text-xl font-semibold mb-4">Edit Role</h2>
    <form method="POST">
      <input type="hidden" name="role_id" id="editRoleId">
      <input type="text" name="role_name" id="editRoleName" required 
             class="w-full border border-gray-300 rounded-lg p-2 mb-4 focus:ring-2 focus:ring-[var(--rose)]">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
        <button type="submit" name="edit_role" class="px-4 py-2 bg-[var(--rose)] text-white rounded-lg hover:bg-[var(--rose-hover)]">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- 🗑️ Delete Role Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-xl shadow-lg w-96">
    <h2 class="text-xl font-semibold mb-3">Delete Role</h2>
    <p class="mb-4 text-gray-600">Are you sure you want to delete this role?</p>
    <form method="POST">
      <input type="hidden" name="role_id" id="deleteRoleId">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
        <button type="submit" name="delete_role" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.remove('hidden'); }
function closeModal(id){ document.getElementById(id).classList.add('hidden'); }

function openEditModal(id, name){
  document.getElementById("editRoleId").value = id;
  document.getElementById("editRoleName").value = name;
  openModal('editModal');
}

function openDeleteModal(id){
  document.getElementById("deleteRoleId").value = id;
  openModal('deleteModal');
}
</script>

</body>
</html>
