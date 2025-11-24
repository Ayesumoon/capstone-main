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

// 🔎 Search Logic (Added for UI consistency)
$search = $_GET['search'] ?? '';
$search_query = "";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $search_query = " WHERE role_name LIKE '%$s%' ";
}

// 📌 Fetch Roles
$roles = $conn->query("SELECT * FROM roles $search_query ORDER BY role_id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Roles | Seven Dwarfs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root { --rose: #e898a8; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; }
    
    /* Exact colors from your screenshot reference */
    .bg-theme-pink { background-color: #e898a8; }
    .bg-theme-pink-hover { background-color: #d68294; }
    .text-theme-pink { color: #e898a8; }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: #e898a8; border-radius: 3px; }
</style>
</head>

<body class="flex h-screen text-sm text-gray-700">

<!-- 🧭 Sidebar -->
<aside class="w-64 bg-white shadow-xl flex flex-col z-20 shrink-0" x-data="{ userMenu: true, productMenu: false }">
  <div class="p-4 border-b">
    <div class="flex items-center space-x-3">
      <img src="logo2.png" alt="Logo" class="w-10 h-10 rounded-full">
      <h2 class="text-lg font-bold text-theme-pink">SevenDwarfs</h2>
    </div>
  </div>

  <div class="p-4 border-b flex items-center space-x-3">
      <img src="newID.jpg" alt="Admin" class="w-10 h-10 rounded-full border">
      <div>
        <p class="font-semibold"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
  </div>

  <nav class="flex-1 overflow-y-auto p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition">
        <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
      </a>

      <!-- User Management -->
      <div>
        <button @click="userMenu = !userMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-pink-50 hover:text-theme-pink transition">
          <span><i class="fas fa-users-cog mr-2"></i>User Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>

        <div x-show="userMenu" x-collapse class="pl-8 mt-1 space-y-1">
          <a href="manage_users.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-user mr-2"></i>Users</a>
          <a href="manage_roles.php" class="block py-1 bg-pink-50 text-[var(--rose)] font-medium rounded-md"><i class="fas fa-id-badge mr-2"></i>Roles</a>
        </div>
      </div>

      <!-- Product Management -->
      <div>
        <button @click="productMenu = !productMenu" 
          class="w-full text-left px-4 py-2 flex justify-between items-center rounded-md hover:bg-pink-50 hover:text-theme-pink transition">
          <span><i class="fas fa-box-open mr-2"></i>Product Management</span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>

        <div x-show="productMenu" x-collapse class="pl-8 mt-1 space-y-1">
          <a href="categories.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-tags mr-2"></i>Category</a>
          <a href="products.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-box mr-2"></i>Products</a>
          <a href="inventory.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-warehouse mr-2"></i>Inventory</a>
          <a href="stock_management.php" class="block py-1 px-2 hover:text-theme-pink"><i class="fas fa-boxes mr-2"></i>Stock Management</a>
        </div>
      </div>

      <a href="orders.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition"><i class="fas fa-shopping-cart mr-2"></i>Orders</a>
      <a href="cashier_sales_report.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition"><i class="fas fa-chart-line mr-2"></i>Cashier Sales</a>
      <a href="suppliers.php" class="block px-4 py-2 rounded-md hover:bg-pink-50 hover:text-theme-pink transition"><i class="fas fa-industry mr-2"></i>Suppliers</a>
      <a href="system_logs.php" class="block px-4 py-2 hover:bg-pink-50 hover:text-theme-pink rounded transition"><i class="fas fa-file-alt mr-2"></i>System Logs</a>
      <a href="logout.php" class="block px-4 py-2 text-red-500 hover:bg-red-50 rounded-md transition mt-4"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
  </nav>
</aside>

<!-- 🧩 Main Content -->
<main class="flex-1 p-8 overflow-y-auto h-screen bg-white">
  
    <!-- 🎴 The Card Container -->
    <div class="w-full bg-white rounded-lg shadow-sm border border-gray-100 flex flex-col h-auto min-h-[600px]">
        
        <!-- 🟥 Card Header -->
        <div class="bg-theme-pink px-6 py-4 rounded-t-lg">
            <h1 class="text-xl font-bold text-white tracking-wide">Manage Roles</h1>
        </div>

        <!-- 📄 Card Body -->
        <div class="p-6">

            <!-- Flash Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="mb-6 p-3 bg-red-100 text-red-700 rounded-md border border-red-200">
                    <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-6 p-3 bg-green-100 text-green-700 rounded-md border border-green-200">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- 1️⃣ Title & Add Button Row -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4 md:mb-0">Role List</h2>
                
                <button onclick="openModal('addModal')" class="bg-theme-pink hover:bg-theme-pink-hover text-white px-5 py-2 rounded shadow-sm transition flex items-center gap-2 font-medium">
                    <i class="fas fa-plus text-xs"></i> Add Role
                </button>
            </div>

            <!-- 3️⃣ Roles Table (Centered) -->
            <div class="overflow-x-auto text-center"> <!-- Added text-center to parent just in case -->
                
                <!-- Added 'mx-auto' to center the table -->
                <table class="w-full md:w-1/2 lg:w-5/12 text-left text-sm border-collapse mx-auto border border-gray-100">
                    <thead class="bg-gray-100 text-gray-600 uppercase text-xs font-bold border-t border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3 text-left tracking-wider">Role Name</th>
                            <th class="px-6 py-3 text-left tracking-wider whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($roles->num_rows > 0): ?>
                            <?php while ($role = $roles->fetch_assoc()): ?>
                                <tr class="hover:bg-pink-50/20 transition duration-150">
                                    <td class="px-6 py-4 text-gray-700 font-medium"><?= htmlspecialchars($role['role_name']); ?></td>
                                    <td class="px-6 py-4 flex gap-3">
                                        <!-- Edit -->
                                        <button onclick="openEditModal(<?= $role['role_id']; ?>, '<?= $role['role_name']; ?>')" 
                                           class="bg-blue-500 text-white p-1.5 rounded hover:bg-blue-600 transition w-7 h-7 flex items-center justify-center">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>

                                        <?php if ($role['role_id'] != 2): ?>
                                            <!-- Delete -->
                                            <button onclick="openDeleteModal(<?= $role['role_id']; ?>)" 
                                               class="bg-red-500 text-white p-1.5 rounded hover:bg-red-600 transition w-7 h-7 flex items-center justify-center">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="px-6 py-8 text-center text-gray-500">
                                    No roles found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</main>

<!-- ➕ Add Role Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 transform transition-all">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Role</h2>
    <form method="POST">
      <label class="block text-gray-700 text-sm font-bold mb-2">Role Name</label>
      <input type="text" name="role_name" required 
             class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:border-pink-400">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Cancel</button>
        <button type="submit" name="add_role" class="px-4 py-2 bg-theme-pink text-white rounded hover:bg-theme-pink-hover transition">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- ✏️ Edit Role Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 transform transition-all">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Edit Role</h2>
    <form method="POST">
      <input type="hidden" name="role_id" id="editRoleId">
      <label class="block text-gray-700 text-sm font-bold mb-2">Role Name</label>
      <input type="text" name="role_name" id="editRoleName" required 
             class="w-full border border-gray-300 rounded px-3 py-2 mb-4 focus:outline-none focus:border-pink-400">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Cancel</button>
        <button type="submit" name="edit_role" class="px-4 py-2 bg-theme-pink text-white rounded hover:bg-theme-pink-hover transition">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- 🗑️ Delete Role Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg w-96 transform transition-all">
    <h2 class="text-xl font-bold text-gray-800 mb-3">Delete Role</h2>
    <p class="mb-5 text-gray-600 text-sm">Are you sure you want to delete this role?</p>
    <form method="POST">
      <input type="hidden" name="role_id" id="deleteRoleId">
      <div class="flex justify-end gap-3">
        <button type="button" onclick="closeModal('deleteModal')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition">Cancel</button>
        <button type="submit" name="delete_role" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition">Delete</button>
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