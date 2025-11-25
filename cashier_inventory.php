<?php
session_start();
require 'conn.php';
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure cashier logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Get cashier info
$cashierRes = $conn->prepare("SELECT first_name, role_id FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$cashier = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashier['first_name'] ?? 'Cashier';
$role_id = $cashier['role_id'] ?? 0;
$cashierRes->close();

// Filters
$selected_category = $_GET['category'] ?? 'all';
$selected_size = $_GET['size'] ?? 'all';
$selected_color = $_GET['color'] ?? 'all';
$selected_status = $_GET['status'] ?? 'all'; // New Filter

// Fetch Filter Options
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$sizes = $conn->query("SELECT DISTINCT s.size FROM stock st INNER JOIN sizes s ON st.size_id = s.size_id ORDER BY s.size ASC");
$colors = $conn->query("SELECT DISTINCT c.color FROM stock st INNER JOIN colors c ON st.color_id = c.color_id ORDER BY c.color ASC");

// --- QUERY BUILDER ---
$query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.price_id AS price,
        p.supplier_price,
        c.category_name,
        p.image_url,
        COALESCE(sz.size, 'Free Size') as size,
        COALESCE(cl.color, 'Standard') as color,
        s.current_qty AS stock_qty
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock s ON p.product_id = s.product_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    LEFT JOIN colors cl ON s.color_id = cl.color_id
    WHERE 1=1
";

$params = [];
$types = "";

if ($selected_category !== 'all') {
    $query .= " AND p.category_id = ? ";
    $params[] = $selected_category;
    $types .= "i";
}
if ($selected_size !== 'all') {
    $query .= " AND sz.size = ? ";
    $params[] = $selected_size;
    $types .= "s";
}
if ($selected_color !== 'all') {
    $query .= " AND cl.color = ? ";
    $params[] = $selected_color;
    $types .= "s";
}
// NEW: Stock Status Filter Logic
if ($selected_status === 'in_stock') {
    $query .= " AND s.current_qty > 0 ";
} elseif ($selected_status === 'out_of_stock') {
    $query .= " AND s.current_qty <= 0 ";
}

// Order by Name then Size
$query .= " ORDER BY p.product_name ASC, s.current_qty ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory | Seven Dwarfs Boutique</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    
    <!-- Tailwind CSS & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>

    <style>
        :root { --rose: #e59ca8; --rose-hover: #d27b8c; }
        body { font-family: 'Poppins', sans-serif; background-color: #f9fafb; color: #374151; }
        
        /* Scrollbar */
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
    
        /* Animations */
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in-up { animation: fadeInUp 0.4s ease-out forwards; opacity: 0; } /* Start hidden for stagger */
        
        /* Transitions */
        #sidebar { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar-text { transition: opacity 0.2s ease-in-out, transform 0.2s ease; white-space: nowrap; }
        .w-20 .sidebar-text { opacity: 0; transform: translateX(-10px); pointer-events: none; }
    
        /* Glass Header */
        .glass-header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.6);
        }
    </style>
</head>
<body class="flex h-screen overflow-hidden" x-data="{ sidebarOpen: true }">

    <!-- SIDEBAR -->
    <aside id="sidebar" :class="sidebarOpen ? 'w-64' : 'w-20'" class="bg-white border-r border-slate-100 flex flex-col h-full z-30 shadow-sm relative shrink-0">
        <div class="flex items-center justify-between h-20 px-0 pl-6 border-b border-slate-100">
            <div class="flex items-center gap-2 overflow-hidden">
                <div class="shrink-0 text-xl font-bold text-rose-600 tracking-tight">SD</div>
                <div class="sidebar-text" :class="!sidebarOpen && 'opacity-0'">
                    <h1 class="text-xl font-bold text-rose-600 tracking-tight">Seven Dwarfs</h1>
                    <p class="text-[10px] font-semibold text-slate-300 uppercase tracking-widest">Boutique POS</p>
                </div>
            </div>
        </div>
        
        <!-- Toggle -->
        <button @click="sidebarOpen = !sidebarOpen" class="absolute -right-3 top-24 bg-white border border-slate-200 rounded-full p-1 shadow-md text-slate-400 hover:text-rose-600 transition-colors z-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-300" :class="!sidebarOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto no-scrollbar">
            <a href="cashier_pos.php" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-all group overflow-hidden">
                <i class="fas fa-cash-register w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                <span class="sidebar-text">POS Terminal</span>
            </a>
            <a href="cashier_transactions.php" class="flex items-center gap-3 px-3 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-all group overflow-hidden">
                <i class="fas fa-receipt w-6 text-center text-lg group-hover:scale-110 transition-transform"></i>
                <span class="sidebar-text">Transactions</span>
            </a>
            <a href="cashier_inventory.php" class="flex items-center gap-3 px-3 py-3 rounded-xl bg-rose-50 text-rose-600 font-semibold transition-all group overflow-hidden relative">
                <i class="fas fa-boxes w-6 text-center text-lg"></i>
                <span class="sidebar-text">Inventory</span>
            </a>
        </nav>
        <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center gap-3 overflow-hidden">
            <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-sm shadow-sm shrink-0"><?= strtoupper(substr($cashier_name,0,1)) ?></div>
            <div class="sidebar-text overflow-hidden">
                <p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($cashier_name) ?></p>
                <form action="logout.php" method="POST">
                    <button class="text-xs text-rose-500 font-medium hover:underline">Sign Out</button>
                </form>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative overflow-hidden h-full bg-[#f9fafb]">
        
        <!-- Header -->
        <header class="h-20 glass-header flex items-center justify-between px-8 z-20 shrink-0">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Inventory</h2>
                <p class="text-xs text-slate-500 font-medium">Track stock levels by variant</p>
            </div>

            <!-- Search Bar -->
            <div class="relative">
                <input id="searchInput" type="search" placeholder="Search product variant..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-rose-200 w-72 shadow-sm transition-all hover:border-rose-300">
                <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8 custom-scroll">
            
            <!-- Filters -->
            <form method="GET" class="flex flex-wrap gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 items-end fade-in-up" style="animation-delay: 0s;">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Stock Status</label>
                    <div class="relative">
                        <select name="status" class="w-40 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 pr-8 font-medium cursor-pointer hover:bg-slate-100 transition" onchange="this.form.submit()">
                            <option value="all">All Status</option>
                            <option value="in_stock" <?= $selected_status == 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                            <option value="out_of_stock" <?= $selected_status == 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500"><i class="fas fa-chevron-down text-xs"></i></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Category</label>
                    <div class="relative">
                        <select name="category" class="w-40 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 pr-8 font-medium cursor-pointer hover:bg-slate-100 transition" onchange="this.form.submit()">
                            <option value="all">All Categories</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500"><i class="fas fa-chevron-down text-xs"></i></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Size</label>
                    <div class="relative">
                        <select name="size" class="w-32 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 pr-8 font-medium cursor-pointer hover:bg-slate-100 transition" onchange="this.form.submit()">
                            <option value="all">All Sizes</option>
                            <?php while ($s = $sizes->fetch_assoc()): ?>
                                <option value="<?= $s['size'] ?>" <?= $selected_size == $s['size'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['size']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500"><i class="fas fa-chevron-down text-xs"></i></div>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Color</label>
                    <div class="relative">
                        <select name="color" class="w-32 appearance-none bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-2 focus:ring-rose-200 outline-none p-2.5 pr-8 font-medium cursor-pointer hover:bg-slate-100 transition" onchange="this.form.submit()">
                            <option value="all">All Colors</option>
                            <?php while ($c = $colors->fetch_assoc()): ?>
                                <option value="<?= $c['color'] ?>" <?= $selected_color == $c['color'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['color']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500"><i class="fas fa-chevron-down text-xs"></i></div>
                    </div>
                </div>
                <div class="ml-auto pb-1">
                    <a href="cashier_inventory.php" class="text-xs font-bold text-rose-500 hover:text-rose-700 transition flex items-center gap-1"><i class="fas fa-times"></i> Clear Filters</a>
                </div>
            </form>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden fade-in-up" style="animation-delay: 0.1s;">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider font-semibold border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Product</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4 text-center">Size</th>
                                <th class="px-6 py-4 text-center">Color</th>
                                <th class="px-6 py-4">Price</th>
                                <th class="px-6 py-4 text-center">Stock</th>
                                <th class="px-6 py-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="divide-y divide-slate-50">
                            <?php if ($result->num_rows > 0): ?>
                                <?php $delay = 0; while ($row = $result->fetch_assoc()): 
                                    $imagePath = $row['image_url'];
                                    $img = 'uploads/default.png';
                                    if (!empty($imagePath)) {
                                        if (str_starts_with(trim($imagePath), '[')) {
                                            $decoded = json_decode($imagePath, true);
                                            $img = is_array($decoded) && count($decoded) > 0 ? $decoded[0] : 'uploads/default.png';
                                        } elseif (str_contains($imagePath, ',')) {
                                            $parts = explode(',', $imagePath);
                                            $img = trim($parts[0]);
                                        } else {
                                            $img = trim($imagePath);
                                        }
                                    }
                                    $stock = $row['stock_qty'] ?? 0;
                                    $delay += 0.05;
                                ?>
                                <tr class="hover:bg-rose-50/30 transition-colors group search-row fade-in-up" style="animation-delay: <?= $delay ?>s; animation-fill-mode: forwards;">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-4">
                                            <div class="relative">
                                                <img src="<?= htmlspecialchars($img) ?>" class="w-12 h-12 rounded-lg object-cover border border-slate-100 bg-slate-50 group-hover:scale-105 transition-transform">
                                                <?php if($stock <= 0): ?><div class="absolute inset-0 bg-white/60 backdrop-blur-[1px] rounded-lg"></div><?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-800 text-search"><?= htmlspecialchars($row['product_name']) ?></div>
                                                <div class="text-[10px] text-slate-400 font-mono">ID: #<?= $row['product_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide border border-slate-200">
                                            <?= htmlspecialchars($row['category_name']) ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-3 text-center">
                                        <span class="text-slate-700 font-bold text-xs border border-slate-200 px-2.5 py-1 rounded-md bg-white shadow-sm">
                                            <?= htmlspecialchars($row['size'] ?? 'N/A') ?>
                                        </span>
                                    </td>

                                    <td class="px-6 py-3 text-center">
                                        <?php if(!empty($row['color'])): ?>
                                            <div class="flex items-center justify-center gap-2">
                                                <div class="w-3 h-3 rounded-full border border-slate-300 shadow-sm" style="background-color: <?= htmlspecialchars($row['color']) ?>;"></div>
                                                <span class="text-xs font-medium text-slate-600"><?= htmlspecialchars($row['color']) ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="px-6 py-3 font-bold text-rose-600 text-sm">
                                        ₱<?= number_format($row['price'], 2) ?>
                                    </td>
                                    
                                    <td class="px-6 py-3 text-center">
                                        <span class="font-bold <?= $stock <= 0 ? 'text-slate-400' : ($stock < 5 ? 'text-amber-500' : 'text-slate-700') ?>"><?= $stock ?></span>
                                    </td>
                                    
                                    <td class="px-6 py-3 text-center">
                                        <?php if($stock <= 0): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 uppercase tracking-wide">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Out of Stock
                                            </span>
                                        <?php elseif($stock < 5): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-100 uppercase tracking-wide">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Low Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase tracking-wide">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> In Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-400 text-sm bg-white">
                                        <div class="flex flex-col items-center">
                                            <i class="fas fa-search text-2xl mb-2 opacity-20"></i>
                                            <p>No products found matching these filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <div class="px-6 py-4 border-t border-slate-50 flex justify-between items-center bg-white" id="paginationContainer">
                    <div class="text-xs text-slate-400 font-medium" id="pageInfo">Showing results</div>
                    <div class="flex gap-2" id="pagination"></div>
                </div>
            </div>

        </div>
    </main>

    <script>
        const searchInput = document.getElementById("searchInput");
        const rows = Array.from(document.querySelectorAll(".search-row"));
        const pagination = document.getElementById("pagination");
        const pageInfo = document.getElementById("pageInfo");

        let currentPage = 1;
        const rowsPerPage = 10;

        function updateTable() {
            const query = searchInput.value.toLowerCase().trim();
            
            // Filter Rows
            const filtered = rows.filter(row => {
                const text = row.querySelector('.text-search').textContent.toLowerCase();
                return text.includes(query);
            });

            // Pagination Logic
            const totalPages = Math.ceil(filtered.length / rowsPerPage) || 1;
            if (currentPage > totalPages) currentPage = 1;
            
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            // Hide all first
            rows.forEach(r => {
                r.classList.add('hidden');
                r.style.animation = 'none'; // reset animation to re-trigger
            });

            // Show current slice with fresh animation
            const slice = filtered.slice(start, end);
            slice.forEach((r, index) => {
                r.classList.remove('hidden');
                // Trigger reflow
                void r.offsetWidth; 
                r.style.animation = `fadeInUp 0.3s ease-out ${index * 0.05}s forwards`;
            });

            // Update Info text
            pageInfo.textContent = `Showing ${slice.length > 0 ? start + 1 : 0}-${Math.min(end, filtered.length)} of ${filtered.length} variants`;

            // Render Pagination Buttons
            pagination.innerHTML = "";
            if (totalPages > 1) {
                // Prev
                const prev = document.createElement("button");
                prev.innerHTML = '<i class="fas fa-chevron-left"></i>';
                prev.className = "w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition text-xs";
                prev.disabled = currentPage === 1;
                prev.onclick = () => { currentPage--; updateTable(); };
                pagination.appendChild(prev);

                // Page Numbers (Simple logic for now)
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    const btn = document.createElement("button");
                    btn.textContent = i;
                    btn.className = `w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition ${i === currentPage ? 'bg-rose-600 text-white shadow-md' : 'text-slate-600 hover:bg-slate-50 border border-slate-200'}`;
                    btn.onclick = () => { currentPage = i; updateTable(); };
                    pagination.appendChild(btn);
                }

                // Next
                const next = document.createElement("button");
                next.innerHTML = '<i class="fas fa-chevron-right"></i>';
                next.className = "w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed transition text-xs";
                next.disabled = currentPage === totalPages;
                next.onclick = () => { currentPage++; updateTable(); };
                pagination.appendChild(next);
            }
        }

        searchInput.addEventListener("input", () => { currentPage = 1; updateTable(); });
        
        // Initial Load
        updateTable();
    </script>

</body>
</html>
<?php $stmt->close(); $conn->close(); ?>