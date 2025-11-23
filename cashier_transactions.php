<?php
session_start();
require 'conn.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. ✅ CONFIG: Fix SQL Modes & Limits
$conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
$conn->query("SET SESSION group_concat_max_len = 100000");

// Ensure cashier logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Fetch cashier info
$cashierRes = $conn->prepare("SELECT first_name FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$cashierRow = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashierRow ? $cashierRow['first_name'] : 'Unknown Cashier';
$cashierRes->close();

// Filter Logic
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'today';
switch ($filter) {
    case 'week':
        $dateCondition = "YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)";
        $label = "This Week";
        break;
    case 'month':
        $dateCondition = "YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
        $label = "This Month";
        break;
    default:
        $dateCondition = "DATE(o.created_at) = CURDATE()";
        $label = "Today";
}


$query = "
    SELECT
        o.order_id,
        o.total_amount,
        o.cash_given,
        o.changes,
        o.created_at,
        pm.payment_method_name,
        os.order_status_name AS status,
        COUNT(oi.id) AS total_items,
        GROUP_CONCAT(
            CONCAT(
                oi.qty, '***',
                COALESCE(p.product_name, 'Unknown Product'), '***',
                COALESCE(sz.size, oi.size, '-'), '***',
                COALESCE(cl.color, oi.color, '-'), '***',
                COALESCE(p.price_id, 0)
            )
            SEPARATOR '///'
        ) as item_details,
        (
            SELECT GROUP_CONCAT(
                CONCAT(
                    r.refund_id, '***',
                    r.order_item_id, '***',
                    r.refund_amount, '***',
                    COALESCE(pr.product_name, 'Unknown Product'), '***',
                    COALESCE(sz2.size, '-'), '***',
                    COALESCE(cl2.color, '-'), '***',
                    r.refunded_at
                ) SEPARATOR '///'
            )
            FROM refunds r
            LEFT JOIN order_items oi2 ON r.order_item_id = oi2.id
            LEFT JOIN products pr ON r.product_id = pr.product_id
            LEFT JOIN sizes sz2 ON r.size_id = sz2.size_id
            LEFT JOIN colors cl2 ON r.color_id = cl2.color_id
            WHERE r.order_id = o.order_id
        ) as refund_details
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN payment_methods pm ON o.payment_method_id = pm.payment_method_id
    LEFT JOIN order_status os ON o.order_status_id = os.order_status_id
    LEFT JOIN stock st ON oi.stock_id = st.stock_id
    LEFT JOIN sizes sz ON st.size_id = sz.size_id
    LEFT JOIN colors cl ON st.color_id = cl.color_id

    WHERE $dateCondition AND o.admin_id = ?
    GROUP BY o.order_id
    ORDER BY o.created_at DESC
";

$stmt = $conn->prepare($query);
if (!$stmt) { die("SQL Error: " . $conn->error); }
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$transactionCount = $result->num_rows;

// Compute Total Sales
$totalStmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) AS total_sales FROM orders o WHERE $dateCondition AND o.admin_id = ?");
$totalStmt->bind_param("i", $admin_id);
$totalStmt->execute();
$totalSales = $totalStmt->get_result()->fetch_assoc()['total_sales'] ?? 0;
$totalStmt->close();

// Trending Products
$trendingStmt = $conn->prepare("
    SELECT p.product_name, p.image_url, p.price_id as price, SUM(oi.qty) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.admin_id = ?
    GROUP BY oi.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");
$trendingStmt->bind_param("i", $admin_id);
$trendingStmt->execute();
$trendingResult = $trendingStmt->get_result();
$trendingItems = [];
while($row = $trendingResult->fetch_assoc()) {
    $img = 'uploads/default.png';
    if (!empty($row['image_url'])) {
        if (str_starts_with(trim($row['image_url']), '[')) {
            $decoded = json_decode($row['image_url'], true);
            $img = !empty($decoded) ? $decoded[0] : 'uploads/default.png';
        } elseif (str_contains($row['image_url'], ',')) {
            $parts = explode(',', $row['image_url']);
            $img = trim($parts[0]);
        } else {
            $img = trim($row['image_url']);
        }
    }
    $row['final_image'] = $img;
    $trendingItems[] = $row;
}
$trendingStmt->close();

// Chart Data
$groupBy = ($filter === 'today') ? 'HOUR(o.created_at)' : 'DATE(o.created_at)';
$orderBy = ($filter === 'today') ? 'HOUR(o.created_at)' : 'DATE(o.created_at)';
$selectLabel = ($filter === 'today') ? 'HOUR(o.created_at)' : 'DATE(o.created_at)';

$chartStmt = $conn->prepare("SELECT $selectLabel as label, SUM(o.total_amount) as total FROM orders o WHERE $dateCondition AND o.admin_id = ? GROUP BY $groupBy ORDER BY $orderBy");
$chartStmt->bind_param("i", $admin_id);
$chartStmt->execute();
$chartDataRes = $chartStmt->get_result();

$chartLabels = [];
$chartTotals = [];
while ($row = $chartDataRes->fetch_assoc()) {
    $chartLabels[] = ($filter === 'today') ? date("g A", strtotime($row['label'] . ":00")) : date("M d", strtotime($row['label']));
    $chartTotals[] = $row['total'];
}
$chartStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transactions | Seven Dwarfs Boutique</title>
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { rose: { 50: '#fff1f2', 100: '#ffe4e6', 400: '#fb7185', 500: '#f43f5e', 600: '#e11d48', 700: '#be123c' } },
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
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg> POS Terminal
            </a>
            <a href="cashier_transactions.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-rose-50 text-rose-600 font-semibold transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg> Transactions
            </a>
            <a href="cashier_inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg> Inventory
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
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg> Sign Out
                </button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative overflow-hidden h-full">
        <!-- Header -->
        <header class="h-20 glass-header flex items-center justify-between px-8 z-20 shrink-0">
            <div>
                <h2 class="text-2xl font-bold text-slate-800">Transactions</h2>
                <p class="text-xs text-slate-500 font-medium">Overview for <span class="text-rose-600 font-bold"><?= $label ?></span></p>
            </div>
            <form method="GET" class="flex items-center bg-white rounded-xl border border-slate-200 p-1 shadow-sm">
                <button type="submit" name="filter" value="today" class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all <?= $filter=='today'?'bg-rose-100 text-rose-700 shadow-sm':'text-slate-500 hover:bg-slate-50' ?>">Today</button>
                <button type="submit" name="filter" value="week" class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all <?= $filter=='week'?'bg-rose-100 text-rose-700 shadow-sm':'text-slate-500 hover:bg-slate-50' ?>">Week</button>
                <button type="submit" name="filter" value="month" class="px-4 py-1.5 rounded-lg text-sm font-medium transition-all <?= $filter=='month'?'bg-rose-100 text-rose-700 shadow-sm':'text-slate-500 hover:bg-slate-50' ?>">Month</button>
            </form>
        </header>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-8">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Total Revenue</p>
                        <h3 class="text-3xl font-bold text-rose-600 mt-1">₱<?= number_format($totalSales, 2) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-rose-50 rounded-full flex items-center justify-center text-rose-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Transactions</p>
                        <h3 class="text-3xl font-bold text-slate-800 mt-1"><?= number_format($transactionCount) ?></h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center text-blue-500">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-rose-500 to-pink-600 p-6 rounded-2xl shadow-lg text-white flex items-center justify-between relative overflow-hidden">
                    <div class="relative z-10">
                        <p class="text-xs font-bold text-rose-100 uppercase tracking-wider">Overall Top Item</p>
                        <h3 class="text-xl font-bold mt-1 truncate w-40"><?= !empty($trendingItems) ? htmlspecialchars($trendingItems[0]['product_name']) : 'N/A' ?></h3>
                        <p class="text-sm mt-1 text-rose-100"><?= !empty($trendingItems) ? $trendingItems[0]['total_sold'] . ' sold (All Time)' : '-' ?></p>
                    </div>
                    <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center backdrop-blur-sm relative z-10">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                    </div>
                </div>
            </div>

            <!-- Charts & List -->
            <div class="flex flex-col lg:flex-row gap-6 mb-8 min-h-[350px]">
                <div class="flex-1 bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg text-slate-800">Sales Analytics</h3>
                        <span class="text-xs font-medium text-slate-400 bg-slate-100 px-2 py-1 rounded"><?= $label ?></span>
                    </div>
                    <div class="flex-1 relative w-full"><canvas id="salesChart"></canvas></div>
                </div>
                <div class="w-full lg:w-[350px] bg-white p-6 rounded-2xl shadow-sm border border-slate-100 flex flex-col">
                    <div class="flex justify-between items-end mb-4">
                        <h3 class="font-bold text-lg text-slate-800">Best Sellers</h3>
                        <span class="text-[10px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded">All Time</span>
                    </div>
                    <?php if (count($trendingItems) > 0): $top = $trendingItems[0]; $rest = array_slice($trendingItems, 1); ?>
                        <div class="bg-rose-50 rounded-xl p-4 flex gap-4 items-center mb-4 border border-rose-100 relative overflow-hidden group">
                            <div class="absolute top-0 left-0 bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-br-lg z-10">#1</div>
                            <img src="<?= htmlspecialchars($top['final_image']) ?>" class="w-16 h-16 rounded-lg object-cover bg-white shadow-sm group-hover:scale-105 transition-transform duration-300">
                            <div class="min-w-0">
                                <h4 class="font-bold text-slate-800 text-sm truncate"><?= htmlspecialchars($top['product_name']) ?></h4>
                                <div class="text-xs text-rose-600 font-bold mt-0.5"><?= $top['total_sold'] ?> units sold</div>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                            <?php foreach ($rest as $index => $item): ?>
                            <div class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg transition-colors">
                                <div class="font-bold text-slate-300 text-sm w-4 text-center"><?= $index + 2 ?></div>
                                <img src="<?= htmlspecialchars($item['final_image']) ?>" class="w-10 h-10 rounded-md object-cover bg-slate-100">
                                <div class="flex-1 min-w-0">
                                    <h5 class="text-xs font-bold text-slate-700 truncate"><?= htmlspecialchars($item['product_name']) ?></h5>
                                    <p class="text-[10px] text-slate-500"><?= $item['total_sold'] ?> sold</p>
                                </div>
                                <div class="text-xs font-semibold text-slate-400">₱<?= number_format($item['price'], 0) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex flex-col items-center justify-center text-slate-400"><p class="text-sm">No sales yet</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TABLE -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                    <h3 class="font-bold text-slate-800">Transaction History</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider font-semibold border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Order ID</th>
                                <th class="px-6 py-4">Order Details</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Received</th>
                                <th class="px-6 py-4">Payment</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Date/Time</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): 
                                    // Always show status as "Completed" for display
                                    $statusName = "Completed";
                                    $isRefunded = false;
                                    
                                    // Parse item details: Qty *** Name *** Size *** Color
                                    $itemStrings = !empty($row['item_details']) ? explode('///', $row['item_details']) : [];
                                ?>
                                <tr class="hover:bg-rose-50/30 transition-colors group">
                                    <td class="px-6 py-4 font-medium text-rose-600 align-top">#<?= $row['order_id']; ?></td>
                                    
                                    <!-- ORDER DETAILS COLUMN -->
<td class="px-6 py-4 align-top">
    <?php if(empty($itemStrings)): ?>
        <span class="text-xs text-slate-400">No items</span>
    <?php else:
        // 1. Parse all items into a usable array
        $allItems = [];
        foreach($itemStrings as $str) {
            $parts = explode('***', $str);
            $qty = isset($parts[0]) ? abs($parts[0]) : 0;
            $name = isset($parts[1]) ? $parts[1] : 'Unknown';
            $size = (isset($parts[2]) && $parts[2] !== '-') ? $parts[2] : null;
            $color = (isset($parts[3]) && $parts[3] !== '-') ? $parts[3] : null;
            $price = (isset($parts[4])) ? $parts[4] : 0;

            // Format Name like: "Product Name (Size, Color)"
            $specs = [];
            if ($size) $specs[] = $size;
            if ($color) $specs[] = ucfirst($color);
            $specString = !empty($specs) ? ' (' . implode(', ', $specs) . ')' : '';
            
            $allItems[] = [
                'qty' => $qty,
                'name' => $name . $specString,
                'price' => $price,
                'total_refund' => $qty * $price
            ];
        }

        // Parse refund details
        $refundDetails = [];
        if (!empty($row['refund_details'])) {
            $refundStrings = explode('///', $row['refund_details']);
            foreach ($refundStrings as $rstr) {
                $rparts = explode('***', $rstr);
                if (count($rparts) >= 7) {
                    $refundDetails[] = [
                        'refund_id' => $rparts[0],
                        'order_item_id' => $rparts[1],
                        'refund_amount' => $rparts[2],
                        'product_name' => $rparts[3],
                        'size' => $rparts[4],
                        'color' => $rparts[5],
                        'refunded_at' => $rparts[6]
                    ];
                }
            }
        }
    ?>
        <div class="flex flex-col gap-4">
            <!-- SECTION A: PURCHASED LIST -->
            <div>
                <div class="font-bold text-slate-800 text-[11px] uppercase tracking-wide mb-1.5">
                    Purchased:
                </div>
                <div class="space-y-1">
                    <?php foreach($allItems as $item): ?>
                        <div class="text-sm text-slate-600 leading-tight">
                            <?= htmlspecialchars($item['name']) ?>
                            <span class="text-slate-400 font-medium">x<?= $item['qty'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SECTION B: REFUNDED LIST -->
            <?php if(!empty($refundDetails)): ?>
                <div class="border-t border-slate-100 pt-3">
                    <div class="font-bold text-red-600 text-[11px] uppercase tracking-wide mb-1.5">
                        Refunded:
                    </div>
                    <div class="space-y-1">
                        <?php foreach($refundDetails as $r): ?>
                            <div class="text-sm text-red-500 font-medium flex flex-wrap items-center gap-1 leading-tight">
                                <span class="text-[10px] font-bold border border-red-200 bg-red-50 px-1 rounded">REFUND</span>
                                <span><?= htmlspecialchars($r['product_name']) ?><?= ($r['size'] !== '-' || $r['color'] !== '-') ? ' (' . ($r['size'] !== '-' ? $r['size'] : '') . (($r['size'] !== '-' && $r['color'] !== '-') ? ', ' : '') . ($r['color'] !== '-' ? ucfirst($r['color']) : '') . ')' : '' ?></span>
                                <span class="text-red-400">— ₱<?= number_format($r['refund_amount'], 2) ?></span>
                                <span class="text-red-400 text-[10px] ml-2"><?= date('M d, Y h:i A', strtotime($r['refunded_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</td>
                                    
                                    <td class="px-6 py-4 font-bold text-slate-800 align-top">₱<?= number_format($row['total_amount'], 2); ?></td>
                                    <td class="px-6 py-4 text-slate-500 align-top">
                                        ₱<?= number_format($row['cash_given'], 2); ?>
                                        <span class="text-[10px] text-slate-400 block">Change: ₱<?= number_format($row['changes'], 2); ?></span>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide
                                            <?= strtolower($row['payment_method_name']) == 'gcash' ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-green-50 text-green-600 border border-green-100' ?>">
                                            <?= htmlspecialchars($row['payment_method_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 align-top">
                                        <span class="px-2 py-1 rounded text-xs font-semibold bg-green-100 text-green-600 border border-green-200">
                                            <?= htmlspecialchars($statusName); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-500 align-top">
                                        <?= date('M d, Y', strtotime($row['created_at'])); ?>
                                        <span class="text-slate-400 text-xs block"><?= date('h:i A', strtotime($row['created_at'])); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-right align-top">
                                        <a href="receipt.php?order_id=<?= $row['order_id']; ?>" class="text-slate-400 hover:text-rose-600 transition font-medium text-xs group-hover:underline">View Receipt</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-slate-400 text-sm">No transactions found for this period.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(225, 29, 72, 0.2)');
    gradient.addColorStop(1, 'rgba(225, 29, 72, 0)');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels); ?>,
            datasets: [{ label: 'Sales (₱)', data: <?= json_encode($chartTotals); ?>, backgroundColor: gradient, borderColor: '#e11d48', borderWidth: 2, pointBackgroundColor: '#fff', pointBorderColor: '#e11d48', pointRadius: 4, pointHoverRadius: 6, fill: true, tension: 0.4 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1f2937', padding: 12, cornerRadius: 8, displayColors: false, callbacks: { label: function(context) { return 'Sales: ₱' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2}); } } } }, scales: { x: { grid: { display: false }, ticks: { color: '#9ca3af', font: { size: 11 } } }, y: { grid: { color: '#f3f4f6', borderDash: [5, 5] }, ticks: { color: '#9ca3af', font: { size: 11 }, callback: function(value) { return '₱' + value; } }, beginAtZero: true } } }
    });
    </script>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>