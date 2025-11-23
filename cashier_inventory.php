<?php
session_start();
require 'conn.php';
ini_set('display_errors', 1);
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

// Only Cashiers allowed (assuming role_id 1 is restricted or allowed based on your logic)
// Adjust this check based on your actual role definitions
if ($role_id != 1) {
    // header("Location: dashboard.php"); // Uncomment if strict checking needed
    // exit;
}

// Filters
$selected_category = $_GET['category'] ?? 'all';
$selected_size = $_GET['size'] ?? 'all';
$selected_color = $_GET['color'] ?? 'all';

// Fetch Filter Options
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$sizes = $conn->query("SELECT DISTINCT s.size FROM stock st INNER JOIN sizes s ON st.size_id = s.size_id ORDER BY s.size ASC");
$colors = $conn->query("SELECT DISTINCT c.color FROM stock st INNER JOIN colors c ON st.color_id = c.color_id ORDER BY c.color ASC");

// Build Product Query
$query = "
    SELECT 
        p.product_id,
        p.product_name,
        p.price_id AS price,
        p.supplier_price,
        c.category_name,
        p.image_url,
        SUM(s.current_qty) AS total_stock
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

$query .= " GROUP BY p.product_id ORDER BY p.product_name ASC";

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
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        rose: { 50: '#fff1f2', 100: '#ffe4e6', 400: '#fb7185', 500: '#f43f5e', 600: '#e11d48', 700: '#be123c' }
                    },
                    fontFamily: { sans: ['Poppins', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.03); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex h-screen overflow-hidden antialiased">

    <!-- SIDEBAR -->
    <aside class="w-[260px] bg-white border-r border-slate-100 flex flex-col z-30 transition-all duration-300" id="sidebar">
        <div class="h-20 flex items-center px-6 border-b border-slate-50">
            <div>
                <h1 class="text-xl font-bold text-rose-600 tracking-tight">Seven Dwarfs</h1>
                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest">Boutique POS</p>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <a href="cashier_pos.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                POS Terminal
            </a>
            <a href="cashier_transactions.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Transactions
            </a>
            <a href="cashier_inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-rose-50 text-rose-600 font-semibold transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Inventory
            </a>
        </nav>

        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-sm shadow-sm">
                    <?= strtoupper(substr($cashier_name, 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($cashier_name) ?></p>
                    <p class="text-xs text-slate-400">Logged in</p>
                </div>
            </div>
            <form action="logout.php" method="POST">
                <button class="w-full flex justify-center items-center gap-2 py-2.5 rounded-lg border border-slate-200 text-slate-500 text-sm font-medium hover:bg-white hover:text-rose-600 hover:border-rose-200 shadow-sm transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative overflow-hidden h-full">
        
        <!-- Header -->
        <header class="h-20 glass-header flex items-center justify-between px-8 z-20 shrink-0">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">Inventory</h2>
                <p class="text-xs text-slate-500 font-medium">Track stock levels and prices</p>
            </div>

            <!-- Search Bar -->
            <div class="relative">
                <input id="searchInput" type="search" placeholder="Quick search product..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-rose-200 w-72 shadow-sm transition-all">
                <svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8">
            
            <!-- Filters -->
            <form method="GET" class="flex flex-wrap gap-4 mb-6 bg-white p-4 rounded-2xl shadow-sm border border-slate-100 items-end">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Category</label>
                    <select name="category" class="w-40 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-rose-500 focus:border-rose-500 p-2.5" onchange="this.form.submit()">
                        <option value="all">All Categories</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= $selected_category == $cat['category_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Size</label>
                    <select name="size" class="w-32 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-rose-500 focus:border-rose-500 p-2.5" onchange="this.form.submit()">
                        <option value="all">All Sizes</option>
                        <?php while ($s = $sizes->fetch_assoc()): ?>
                            <option value="<?= $s['size'] ?>" <?= $selected_size == $s['size'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['size']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Color</label>
                    <select name="color" class="w-32 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-rose-500 focus:border-rose-500 p-2.5" onchange="this.form.submit()">
                        <option value="all">All Colors</option>
                        <?php while ($c = $colors->fetch_assoc()): ?>
                            <option value="<?= $c['color'] ?>" <?= $selected_color == $c['color'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['color']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="ml-auto">
                    <a href="cashier_inventory.php" class="text-xs font-bold text-rose-600 hover:underline">Clear Filters</a>
                </div>
            </form>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider font-semibold border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Product Details</th>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Selling Price</th>
                                <th class="px-6 py-4">Supplier Price</th>
                                <th class="px-6 py-4 text-center">Stock Level</th>
                                <th class="px-6 py-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="divide-y divide-slate-50">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): 
                                    // Handle image logic
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
                                    $stock = $row['total_stock'] ?? 0;
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group search-row">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-4">
                                            <img src="<?= htmlspecialchars($img) ?>" class="w-12 h-12 rounded-lg object-cover border border-slate-100 bg-slate-50">
                                            <div>
                                                <div class="font-bold text-slate-800 text-search"><?= htmlspecialchars($row['product_name']) ?></div>
                                                <div class="text-xs text-slate-400">ID: <?= $row['product_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-md text-xs font-bold uppercase tracking-wide">
                                            <?= htmlspecialchars($row['category_name']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 font-bold text-rose-600">
                                        ₱<?= number_format($row['price'], 2) ?>
                                    </td>
                                    <td class="px-6 py-3 font-medium text-slate-500">
                                        ₱<?= number_format($row['supplier_price'], 2) ?>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="font-bold <?= $stock <= 5 ? 'text-red-500' : 'text-slate-700' ?>"><?= $stock ?></span>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <?php if($stock <= 0): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-red-600"></span> Out of Stock
                                            </span>
                                        <?php elseif($stock <= 5): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-600"></span> Low Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> In Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 text-sm">No products found matching these filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <div class="px-6 py-4 border-t border-slate-50 flex justify-center" id="pagination"></div>
            </div>

        </div>
    </main>

    <script>
        const searchInput = document.getElementById("searchInput");
        const tableBody = document.getElementById("tableBody");
        const rows = Array.from(document.querySelectorAll(".search-row"));
        const pagination = document.getElementById("pagination");

        let currentPage = 1;
        const rowsPerPage = 8;

        function updateTable() {
            const query = searchInput.value.toLowerCase().trim();
            
            // Filter Rows
            const filtered = rows.filter(row => {
                const text = row.querySelector('.text-search').textContent.toLowerCase();
                return text.includes(query);
            });

            // Pagination Logic
            const totalPages = Math.ceil(filtered.length / rowsPerPage);
            if (currentPage > totalPages) currentPage = 1;
            
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;

            // Hide all, show slice
            rows.forEach(r => r.classList.add('hidden'));
            filtered.slice(start, end).forEach(r => r.classList.remove('hidden'));

            // Render Pagination Buttons
            pagination.innerHTML = "";
            if (totalPages > 1) {
                // Prev
                const prev = document.createElement("button");
                prev.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>`;
                prev.className = "w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-50 mr-2";
                prev.disabled = currentPage === 1;
                prev.onclick = () => { currentPage--; updateTable(); };
                pagination.appendChild(prev);

                // Numbers
                for (let i = 1; i <= totalPages; i++) {
                    const btn = document.createElement("button");
                    btn.textContent = i;
                    btn.className = `w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition ${i === currentPage ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-50'}`;
                    btn.onclick = () => { currentPage = i; updateTable(); };
                    pagination.appendChild(btn);
                }

                // Next
                const next = document.createElement("button");
                next.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>`;
                next.className = "w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-50 ml-2";
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