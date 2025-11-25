<?php
session_start();
require 'conn.php';

// 🔐 Ensure logged-in admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// 🔹 Fetch current admin details (Including role_id for permissions)
$stmt = $conn->prepare("
    SELECT CONCAT(a.first_name, ' ', a.last_name) AS full_name, 
           r.role_name, a.role_id 
    FROM adminusers a 
    LEFT JOIN roles r ON a.role_id = r.role_id
    WHERE a.admin_id = ?
");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? "Admin";
$admin_role = $admin['role_name'] ?? "Administrator";
$admin_role_id = $admin['role_id'] ?? 0; // Needed for the delete button check

// 🔎 Search Logic
$search = $_GET['search'] ?? '';
$search_query = "";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $search_query = " AND (u.username LIKE '%$s%' OR u.first_name LIKE '%$s%' OR u.last_name LIKE '%$s%' OR u.admin_email LIKE '%$s%') ";
}

// 🧩 Fetch users with Search Filter
$query = "
    SELECT u.admin_id, u.username, u.admin_email, 
           CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
           r.role_name, s.status_id, s.status_name
    FROM adminusers u
    LEFT JOIN roles r ON u.role_id = r.role_id
    LEFT JOIN status s ON u.status_id = s.status_id
    WHERE 1=1 $search_query
    ORDER BY u.admin_id ASC
";
$result = $conn->query($query);

// Notifications (Mock logic to match dashboard layout consistency)
$newOrdersNotif = 0; $lowStockNotif = 0; $totalNotif = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users | Seven Dwarfs</title>

<!-- Libraries -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<!-- NProgress (Loading Bar) -->
<script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
<link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    
    /* Sidebar specific */
    .active { background-color: #fce8eb; color: var(--rose); font-weight: 600; border-radius: 0.5rem; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* 1. View Transitions API */
    @view-transition {
        navigation: auto;
    }

    /* 2. Fade In Animation */
    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
        animation: fadeInSlide 0.4s ease-out;
    }

    /* 3. NProgress Customization */
    #nprogress .bar { background: var(--rose) !important; height: 3px !important; }
    #nprogress .peg { box-shadow: 0 0 10px var(--rose), 0 0 5px var(--rose) !important; }
    #nprogress .spinner-icon { border-top-color: var(--rose) !important; border-left-color: var(--rose) !important; }
</style>
</head>

<body class="text-sm animate-fade-in">

<!-- Global State Wrapper with Persistence Logic -->
<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: true, 
        productMenu: false 
     }" 
     x-init="$watch('sidebarOpen', val => localStorage.setItem('sidebarOpen', val))">

  <!-- 🌸 Sidebar (Dynamic Width) -->
  <aside 
    class="bg-white shadow-md fixed top-0 left-0 h-screen z-30 transition-all duration-300 ease-in-out no-scrollbar overflow-y-auto overflow-x-hidden"
    :class="sidebarOpen ? 'w-64' : 'w-20'"
  >
    <!-- Logo -->
    <div class="p-5 border-b flex items-center h-20 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
        <img src="logo2.png" alt="Logo" class="rounded-full w-10 h-10 flex-shrink-0" />
        <h2 class="text-lg font-bold text-[var(--rose)] whitespace-nowrap overflow-hidden transition-all duration-300" 
            x-show="sidebarOpen" x-transition.opacity>SevenDwarfs</h2>
    </div>

    <!-- Admin Profile -->
    <div class="p-5 border-b flex items-center h-24 transition-all duration-300" :class="sidebarOpen ? 'space-x-3' : 'justify-center pl-0'">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10 flex-shrink-0" />
      <div x-show="sidebarOpen" x-transition.opacity class="whitespace-nowrap overflow-hidden">
        <p class="font-semibold text-gray-800"><?= htmlspecialchars($admin_name); ?></p>
        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="p-4 space-y-1">
      <a href="dashboard.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-tachometer-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Dashboard</span>
      </a>

      <!-- User Management (Active) -->
      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-users-cog w-5 text-center text-lg text-[var(--rose)]"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap text-[var(--rose)] font-semibold">User Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <!-- Submenu -->
        <ul x-show="userMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden transition-all" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li>
            <a href="manage_users.php" class="block py-2 active flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Users">
              <i class="fas fa-user w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Users</span>
            </a>
          </li>
          <li>
            <a href="manage_roles.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Roles">
              <i class="fas fa-user-tag w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Roles</span>
            </a>
          </li>
        </ul>
      </div>

      <!-- Product Management -->
      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-box-open w-5 text-center text-lg"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap">Product Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </button>
        <ul x-show="productMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li>
            <a href="categories.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Category">
              <i class="fas fa-tags w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Category</span>
            </a>
          </li>
          <li>
            <a href="products.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Product">
              <i class="fas fa-box w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Product</span>
            </a>
          </li>
          <li>
            <a href="inventory.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Inventory">
              <i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Inventory</span>
            </a>
          </li>
          <li>
            <a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Stock">
              <i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Stock In</span>
            </a>
          </li>
        </ul>
      </div>

      <a href="orders.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-shopping-cart w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Orders</span>
      </a>
      <a href="cashier_sales_report.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-chart-line w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Cashier Sales</span>
      </a>
      <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-industry w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Suppliers</span>
      </a>
      <a href="system_logs.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-file-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">System Logs</span>
      </a>
      <a href="logout.php" class="block px-4 py-3 text-red-600 hover:bg-red-50 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-sign-out-alt w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Logout</span>
      </a>
    </nav>
  </aside>

  <!-- 🌸 Main Content (Dynamic Margin) -->
  <main class="flex-1 flex flex-col pt-20 bg-gray-50 transition-all duration-300 ease-in-out" 
        :class="sidebarOpen ? 'ml-64' : 'ml-20'">
    
    <!-- Header (Dynamic Position) -->
    <header class="bg-[var(--rose)] text-white p-4 flex justify-between items-center shadow-md rounded-bl-2xl fixed top-0 right-0 z-20 transition-all duration-300 ease-in-out"
            :class="sidebarOpen ? 'left-64' : 'left-20'">
      
      <div class="flex items-center gap-4">
          <!-- Toggle Button -->
          <button @click="sidebarOpen = !sidebarOpen" class="text-white hover:bg-white/20 p-2 rounded-full transition focus:outline-none">
             <i class="fas fa-bars text-xl"></i>
          </button>
          <h1 class="text-xl font-semibold">User Management</h1>
      </div>

      <div class="flex items-center gap-4">
        
      </div>
    </header>

    <!-- 📄 Page Content -->
    <section class="p-6">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-xl border border-red-200 flex items-center animate-fade-in">
                <i class="fas fa-exclamation-circle mr-3"></i>
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-xl border border-green-200 flex items-center animate-fade-in">
                <i class="fas fa-check-circle mr-3"></i>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <!-- The White Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <!-- Card Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                <h2 class="text-lg font-bold text-gray-800">Administrator Accounts</h2>
                
                <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                    <!-- Search Form -->
                    <form method="GET" action="" class="flex items-center relative">
                         <i class="fas fa-search absolute left-3 text-gray-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" 
                               class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[var(--rose)] w-full md:w-64"
                               placeholder="Search users...">
                    </form>
                    
                    <!-- Add Button -->
                    <a href="add_user.php" class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-4 py-2 rounded-lg shadow-sm transition flex items-center justify-center gap-2 font-medium text-sm">
                        <i class="fas fa-plus"></i> Add User
                    </a>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                        <tr>
                            <th class="px-6 py-3">Username</th>
                            <th class="px-6 py-3">Full Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition duration-150">
                                    <td class="px-6 py-4 font-medium text-gray-800"><?= htmlspecialchars($user['username']); ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($user['full_name']); ?></td>
                                    <td class="px-6 py-4"><?= htmlspecialchars($user['admin_email']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            <?= htmlspecialchars($user['role_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($user['status_id'] == 1): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-1.5"></span> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <span class="w-1.5 h-1.5 bg-red-600 rounded-full mr-1.5"></span> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-center gap-2">
                                            <!-- Edit -->
                                            <a href="edit_user.php?id=<?= $user['admin_id']; ?>" 
                                               class="text-blue-500 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 p-2 rounded-lg transition" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <?php if ($admin_role_id == 2 && $user['admin_id'] != $admin_id): ?>
                                                <!-- Toggle Status -->
                                                <a href="toggle_user.php?id=<?= $user['admin_id']; ?>&status=<?= $user['status_id']==1?2:1; ?>" 
                                                   class="text-yellow-500 hover:text-yellow-700 bg-yellow-50 hover:bg-yellow-100 p-2 rounded-lg transition"
                                                   title="<?= $user['status_id']==1 ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas <?= $user['status_id']==1 ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                                </a>
                                                <!-- Delete -->
                                                <a href="delete_user.php?id=<?= $user['admin_id']; ?>" 
                                                   onclick="return confirm('Are you sure you want to delete this user?')" 
                                                   class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-500 flex flex-col items-center">
                                    <div class="bg-gray-100 p-4 rounded-full mb-3"><i class="fas fa-search text-gray-400 text-2xl"></i></div>
                                    <p>No users found matching "<strong><?= htmlspecialchars($search) ?></strong>"</p>
                                    <a href="manage_users.php" class="text-[var(--rose)] text-xs font-bold mt-2 hover:underline">Clear Search</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination (Placeholder if needed later) -->
            <div class="px-6 py-4 border-t border-gray-100 text-xs text-gray-500 flex justify-between items-center">
                <span>Showing results for users</span>
                <!-- Add pagination logic here if needed -->
            </div>

        </div> 
    </section>

  </main>
</div>

<!-- NProgress Script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Start bar on link clicks
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', (e) => {
                // Avoid triggers on hash links (#), new tabs, or javascript calls
                if(link.getAttribute('href').startsWith('#') || 
                   link.getAttribute('href').startsWith('javascript') || 
                   link.target === '_blank') return;
                
                NProgress.start();
            });
        });

        // Finish bar when page is fully loaded
        window.addEventListener('load', () => NProgress.done());
        // Failsafe if back button is used
        window.addEventListener('pageshow', () => NProgress.done());
    });
</script>

</body>
</html>