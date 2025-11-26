<?php
session_start();
require 'conn.php'; 

$admin_id   = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

// 🔹 Fetch admin details
if ($admin_id) {
    $query = "SELECT CONCAT(first_name, ' ', last_name) AS full_name, r.role_name 
              FROM adminusers a LEFT JOIN roles r ON a.role_id = r.role_id WHERE a.admin_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
}

// 🔹 Fetch dropdowns
$categories = [];
$resultCategories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = $resultCategories->fetch_assoc()) $categories[] = $row;

$colors = [];
$resultColors = $conn->query("SELECT color_id, color FROM colors ORDER BY color ASC");
while ($row = $resultColors->fetch_assoc()) $colors[] = $row;

$sizes = [];
$resultSizes = $conn->query("SELECT size_id, size FROM sizes ORDER BY size ASC");
while ($row = $resultSizes->fetch_assoc()) $sizes[] = $row;

// 🔹 Get Filters
$selectedCategory = $_GET['category'] ?? 'all';
$selectedColor    = $_GET['color'] ?? 'all';
$selectedSize     = $_GET['size'] ?? 'all';
$selectedStock    = $_GET['stock_status'] ?? 'all'; // <--- NEW STOCK FILTER
$searchQuery      = $_GET['search'] ?? ''; 

// 🔹 Build Query
$sqlProducts = "
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        col.color,
        sz.size,
        st.current_qty AS total_stock,
        p.created_at,
        s.supplier_name
    FROM stock st
    INNER JOIN products p ON st.product_id = p.product_id
    INNER JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN colors col ON st.color_id = col.color_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
";

// 🔹 Dynamic WHERE filters
$where  = ["1=1"];
$params = [];
$types  = "";

if ($selectedCategory !== 'all') {
    $where[] = "c.category_name = ?";
    $params[] = $selectedCategory;
    $types   .= "s";
}
if ($selectedColor !== 'all') {
    $where[] = "col.color = ?";
    $params[] = $selectedColor;
    $types   .= "s";
}
if ($selectedSize !== 'all') {
    $where[] = "sz.size = ?";
    $params[] = $selectedSize;
    $types   .= "s";
}

// 🔍 Stock Status Logic (NEW)
if ($selectedStock === 'in_stock') {
    $where[] = "st.current_qty > 0";
} elseif ($selectedStock === 'out_of_stock') {
    $where[] = "st.current_qty <= 0";
}

// 🔍 Add Search Logic
if (!empty($searchQuery)) {
    $where[] = "p.product_name LIKE ?";
    $params[] = "%" . $searchQuery . "%";
    $types   .= "s";
}

if ($where) {
    $sqlProducts .= " WHERE " . implode(" AND ", $where);
}

$sqlProducts .= " ORDER BY p.created_at DESC, p.product_name ASC";

// 🔹 Execute query
$stmt = $conn->prepare($sqlProducts);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultProducts = $stmt->get_result();

$inventory = [];
if ($resultProducts && $resultProducts->num_rows > 0) {
    while ($row = $resultProducts->fetch_assoc()) {
        $inventory[] = $row;
    }
}

// ---------------------------------------------------------
// 🔄 AJAX HANDLER (Output ONLY Table Rows)
// ---------------------------------------------------------
if (isset($_GET['ajax'])) {
    if (!empty($inventory)) {
        foreach ($inventory as $item) {
            $stock = (int)$item['total_stock'];
            // Status Logic
            if ($stock == 0) {
                $status_label = "Out of Stock";
                $status_class = "bg-red-100 text-red-700 border-red-200";
            } elseif ($stock < 0) { 
                $status_label = "Low Stock"; // Assuming logic: usually < 0 is error or backorder
                $status_class = "bg-yellow-100 text-yellow-700 border-yellow-200";
            } else {
                $status_label = "In Stock";
                $status_class = "bg-green-100 text-green-700 border-green-200";
            }

            $prodName = htmlspecialchars($item['product_name']);
            $catName = htmlspecialchars($item['category_name']);
            $colorBadge = !empty($item['color']) 
                ? "<span class='inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-gray-200 shadow-sm bg-white text-gray-800'><span class='w-2 h-2 mr-1.5 rounded-full bg-gray-400'></span>" . htmlspecialchars($item['color']) . "</span>" 
                : "<span class='text-gray-400'>—</span>";
            $sizeBadge = !empty($item['size']) 
                ? "<span class='inline-block w-8 h-8 leading-8 text-center rounded bg-white border border-gray-300 text-xs font-bold text-gray-700 shadow-sm'>" . htmlspecialchars($item['size']) . "</span>" 
                : "<span class='text-gray-400'>—</span>";
            $date = date('M d, Y', strtotime($item['created_at']));

            echo "
            <tr class='hover:bg-gray-50 transition duration-150'>
                <td class='px-6 py-4 font-medium text-gray-900'>{$prodName}</td>
                <td class='px-6 py-4 text-gray-600'><span class='bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs border'>{$catName}</span></td>
                <td class='px-6 py-4 text-center'>{$colorBadge}</td>
                <td class='px-6 py-4 text-center'>{$sizeBadge}</td>
                <td class='px-6 py-4 text-center font-bold text-gray-700'>{$stock}</td>
                <td class='px-6 py-4 text-center'><span class='px-2 py-1 rounded-md text-xs font-semibold border {$status_class}'>{$status_label}</span></td>
                <td class='px-6 py-4 text-right text-gray-500 text-xs'>{$date}</td>
            </tr>";
        }
    } else {
        echo "<tr><td colspan='7' class='text-center px-6 py-8 text-gray-400'><i class='fas fa-box-open text-2xl mb-2 block'></i>No inventory items found matching your filters.</td></tr>";
    }
    exit; // 🛑 STOP HERE FOR AJAX
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventory | Seven Dwarfs Boutique</title>
  
  <!-- Libraries -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  
  <!-- NProgress (Loading Bar) -->
  <script src="https://unpkg.com/nprogress@0.2.0/nprogress.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/nprogress@0.2.0/nprogress.css" />

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />

  <style>
    :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
    body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
    [x-cloak] { display: none !important; }

    /* Custom scrollbar */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-thumb { background: var(--rose); border-radius: 3px; }
    
    /* Sidebar specific */
    .active { background-color: #fce8eb; color: var(--rose); font-weight: 600; border-radius: 0.5rem; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* 1. View Transitions API */
    @view-transition { navigation: auto; }

    /* 2. Fade In Animation */
    @keyframes fadeInSlide {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeInSlide 0.4s ease-out; }

    /* 3. NProgress Customization */
    #nprogress .bar { background: var(--rose) !important; height: 3px !important; }
    #nprogress .peg { box-shadow: 0 0 10px var(--rose), 0 0 5px var(--rose) !important; }
  </style>
</head>

<body class="text-sm animate-fade-in">

<!-- Global State Wrapper -->
<div class="flex min-h-screen" 
     x-data="{ 
        sidebarOpen: localStorage.getItem('sidebarOpen') === 'false' ? false : true, 
        userMenu: false, 
        productMenu: true
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

      <!-- User Management -->
      <div>
        <button @click="userMenu = !userMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-users-cog w-5 text-center text-lg"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap">User Management</span>
          </div>
          <i x-show="sidebarOpen" class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </button>
        <!-- Submenu -->
        <ul x-show="userMenu" class="text-sm text-gray-700 space-y-1 mt-1 bg-gray-50 rounded-md overflow-hidden transition-all" :class="sidebarOpen ? 'pl-8' : 'pl-0 text-center'">
          <li>
            <a href="manage_users.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Users">
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
     <a href="suppliers.php" class="block px-4 py-3 hover:bg-gray-100 rounded-md transition-all duration-300 flex items-center" :class="sidebarOpen ? 'space-x-2' : 'justify-center px-0'">
        <i class="fas fa-industry w-5 text-center text-lg"></i>
        <span x-show="sidebarOpen" class="whitespace-nowrap">Suppliers</span>
      </a>
      <!-- Product Management (Active) -->
      <div>
        <button @click="productMenu = !productMenu" class="w-full text-left px-4 py-3 flex items-center hover:bg-gray-100 rounded-md transition-all duration-300" :class="sidebarOpen ? 'justify-between' : 'justify-center px-0'">
          <div class="flex items-center" :class="sidebarOpen ? 'space-x-2' : ''">
            <i class="fas fa-box-open w-5 text-center text-lg text-[var(--rose)]"></i>
            <span x-show="sidebarOpen" class="whitespace-nowrap text-[var(--rose)] font-semibold">Product Management</span>
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
            <a href="stock_management.php" class="block py-2 hover:text-[var(--rose)] flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Stock">
              <i class="fas fa-boxes w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Stock In</span>
            </a>
          </li>
          <li>
            <a href="inventory.php" class="block py-2 active flex items-center" :class="sidebarOpen ? '' : 'justify-center'" title="Inventory">
              <i class="fas fa-warehouse w-4 mr-2" :class="sidebarOpen ? '' : 'mr-0 text-md'"></i>
              <span x-show="sidebarOpen">Inventory</span>
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
          <h1 class="text-xl font-semibold">Inventory Management</h1>
      </div>

      <div class="flex items-center gap-4">
      </div>
    </header>

    <!-- 📄 Page Content -->
    <section class="p-6">
        
        <!-- The White Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <!-- Filters -->
            <div class="p-6 border-b border-gray-100 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex flex-wrap gap-4 items-center">
                    
                    <!-- Category Dropdown -->
                    <div>
                        <label for="category" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Category</label>
                        <select id="category" onchange="fetchInventory()" 
                        class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm min-w-[140px] cursor-pointer bg-gray-50">
                        <option value="all">All</option>
                        <?php foreach ($categories as $category) { ?>
                            <option value="<?php echo $category['category_name']; ?>" <?php echo ($selectedCategory == $category['category_name']) ? 'selected' : ''; ?>>
                            <?php echo $category['category_name']; ?>
                            </option>
                        <?php } ?>
                        </select>
                    </div>

                    <!-- Color Dropdown -->
                    <div>
                        <label for="color" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Color</label>
                        <select id="color" onchange="fetchInventory()" 
                        class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm min-w-[120px] cursor-pointer bg-gray-50">
                        <option value="all">All</option>
                        <?php foreach ($colors as $color) { ?>
                            <option value="<?php echo $color['color']; ?>" <?php echo ($selectedColor == $color['color']) ? 'selected' : ''; ?>>
                            <?php echo $color['color']; ?>
                            </option>
                        <?php } ?>
                        </select>
                    </div>

                    <!-- Size Dropdown -->
                    <div>
                        <label for="size" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Size</label>
                        <select id="size" onchange="fetchInventory()" 
                        class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm min-w-[100px] cursor-pointer bg-gray-50">
                        <option value="all">All</option>
                        <?php foreach ($sizes as $size) { ?>
                            <option value="<?php echo $size['size']; ?>" <?php echo ($selectedSize == $size['size']) ? 'selected' : ''; ?>>
                            <?php echo $size['size']; ?>
                            </option>
                        <?php } ?>
                        </select>
                    </div>

                    <!-- Stock Status Dropdown (NEW) -->
                    <div>
                        <label for="stock_status" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                        <select id="stock_status" onchange="fetchInventory()" 
                        class="px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm min-w-[120px] cursor-pointer bg-gray-50">
                            <option value="all" <?php echo ($selectedStock == 'all') ? 'selected' : ''; ?>>All</option>
                            <option value="in_stock" <?php echo ($selectedStock == 'in_stock') ? 'selected' : ''; ?>>In Stock</option>
                            <option value="out_of_stock" <?php echo ($selectedStock == 'out_of_stock') ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>

                </div>

                <!-- 🔍 Search Bar -->
                <div class="w-full md:w-auto">
                     <label for="search" class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                     <div class="relative">
                         <input type="text" id="search" placeholder="Search product..." oninput="debounceSearch()"
                             class="w-full md:w-64 pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--rose)] text-sm shadow-sm">
                         <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                     </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-gray-500 uppercase font-bold text-xs">
                        <tr>
                            <th class="px-6 py-3">Product Name</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3 text-center">Color</th>
                            <th class="px-6 py-3 text-center">Size</th>
                            <th class="px-6 py-3 text-center">Stock</th>
                            <th class="px-6 py-3 text-center">Status</th>
                            <th class="px-6 py-3 text-right">Created At</th>
                        </tr>
                    </thead>
                    <tbody id="inventoryTableBody" class="divide-y divide-gray-100">
                        <!-- PHP Initial Load Loop -->
                        <?php 
                          if (!empty($inventory)) { 
                            foreach ($inventory as $item) {
                              $stock = (int)$item['total_stock'];
                              
                              if ($stock == 0) {
                                  $status_label = "Out of Stock";
                                  $status_class = "bg-red-100 text-red-700 border-red-200";
                              } elseif ($stock < 0) { 
                                  $status_label = "Low Stock";
                                  $status_class = "bg-yellow-100 text-yellow-700 border-yellow-200";
                              } else {
                                  $status_label = "In Stock";
                                  $status_class = "bg-green-100 text-green-700 border-green-200";
                              }
                        ?>
                        <tr class="hover:bg-gray-50 transition duration-150">
                          <td class="px-6 py-4 font-medium text-gray-800"><?php echo htmlspecialchars($item['product_name']); ?></td>
                          <td class="px-6 py-4"><span class="bg-gray-100 text-gray-600 px-2.5 py-1 rounded-full text-xs font-medium"><?php echo htmlspecialchars($item['category_name']); ?></span></td>
                          <td class="px-6 py-4 text-center">
                              <?php if (!empty($item['color'])): ?>
                                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border border-gray-200 shadow-sm bg-white text-gray-800"><span class="w-2 h-2 mr-1.5 rounded-full bg-gray-400"></span><?php echo htmlspecialchars($item['color']); ?></span>
                              <?php else: ?><span class="text-gray-400">—</span><?php endif; ?>
                          </td>
                          <td class="px-6 py-4 text-center">
                              <?php if (!empty($item['size'])): ?>
                                  <span class="inline-block px-2 py-0.5 rounded bg-white border border-gray-200 text-xs font-bold text-gray-700 shadow-sm"><?php echo htmlspecialchars($item['size']); ?></span>
                              <?php else: ?><span class="text-gray-400">—</span><?php endif; ?>
                          </td>
                          <td class="px-6 py-4 text-center font-bold text-gray-700"><?php echo $stock; ?></td>
                          <td class="px-6 py-4 text-center"><span class="px-2.5 py-1 rounded-full text-xs font-bold border <?php echo $status_class; ?>"><?php echo $status_label; ?></span></td>
                          <td class="px-6 py-4 text-right text-gray-400 text-xs"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                        </tr>
                        <?php 
                            }
                          } else { 
                        ?>
                        <tr><td colspan="7" class="text-center px-6 py-10 text-gray-400"><i class="fas fa-box-open text-2xl mb-2 block"></i>No inventory items found.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </div> 
    </section>

  </main>
</div>

<!-- Scripts -->
<script>
// NProgress Logic
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', (e) => {
            if(link.getAttribute('href').startsWith('#') || link.getAttribute('href').startsWith('javascript') || link.target === '_blank') return;
            NProgress.start();
        });
    });
    window.addEventListener('load', () => NProgress.done());
    window.addEventListener('pageshow', () => NProgress.done());
});

let timeout = null;

function fetchInventory() {
    const category = document.getElementById('category').value;
    const color    = document.getElementById('color').value;
    const size     = document.getElementById('size').value;
    const stock    = document.getElementById('stock_status').value; // <--- NEW JS LOGIC
    const search   = document.getElementById('search').value;

    const params = new URLSearchParams({
        ajax: '1',
        category: category,
        color: color,
        size: size,
        stock_status: stock, // <--- NEW PARAMETER
        search: search
    });

    fetch('inventory.php?' + params.toString())
        .then(res => res.text())
        .then(html => {
            document.getElementById('inventoryTableBody').innerHTML = html;
        })
        .catch(err => console.error('Error fetching inventory:', err));
}

function debounceSearch() {
    clearTimeout(timeout);
    timeout = setTimeout(fetchInventory, 300);
}
</script>

</body>
</html>