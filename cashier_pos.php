<?php
session_start();
require 'conn.php';

// --- 1. SETTINGS: Hide errors from output so JSON doesn't break ---
ini_set('display_errors', 0);
error_reporting(E_ALL);

$admin_id = $_SESSION['admin_id'] ?? null;

/* ========================================================
   API: GET ORDER DETAILS
======================================================== */
if (isset($_GET['action']) && $_GET['action'] === 'get_order_details') {
    
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    try {
        if (!isset($_GET['oid'])) throw new Exception("Missing Order ID");
        $oid = intval($_GET['oid']);

        if ($conn->connect_error) throw new Exception("Database Connection Failed");

        // 1. Validate Order
        $stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? LIMIT 1");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) throw new Exception("Order #$oid not found.");
        $stmt->close();

        // 2. Fetch Items
        // REMOVED order_item_id. We select order_id just for reference.
        $sql = "
            SELECT 
                oi.order_id,
                oi.product_id,
                oi.stock_id,
                oi.qty,
                oi.price,
                p.product_name,
                COALESCE(oi.size, 'Free Size') AS size_name,
                COALESCE(oi.color, 'Standard') AS color_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) { $items[] = $row; }
        $stmt->close();

        echo json_encode(['status' => 'success', 'items' => $items]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

/* ========================================================
   API: PROCESS REFUND (UPGRADED FULL VERSION)
======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'process_refund') {

    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    $conn->begin_transaction();

    try {
        if (!$admin_id) throw new Exception("Not authenticated");
        if (empty($input['items'])) throw new Exception("No items selected");

        $oid = intval($input['order_id']);

        // Prepare statements
        $refund_stmt = $conn->prepare("
            INSERT INTO refunds 
            (order_id, order_item_id, product_id, stock_id, size_id, color_id, refund_amount, refunded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stock_stmt = $conn->prepare("
            UPDATE stock SET current_qty = current_qty + ? WHERE stock_id = ?
        ");

        $update_order_total = $conn->prepare("
            UPDATE orders SET total_amount = total_amount - ? WHERE order_id = ?
        ");

        $insert_negative_transaction = $conn->prepare("
            INSERT INTO transactions (order_id, payment_method_id, total, order_status_id, date_time)
            VALUES (?, NULL, ?, 2, NOW())
        ");

        $total_refund = 0;

        foreach ($input['items'] as $item) {

            $product_id = intval($item['product_id']);
            $stock_id   = intval($item['stock_id']);

            // get size and color from stock table
            $sc = $conn->query("SELECT size_id, color_id FROM stock WHERE stock_id = $stock_id")->fetch_assoc();
            $size_id  = $sc['size_id'] ?? null;
            $color_id = $sc['color_id'] ?? null;

            $refund_qty = intval($item['refund_qty']);
            $price      = floatval($item['price']);
            $amount     = $refund_qty * $price;

            $total_refund += $amount;

            // find order_item_id
            $oi = $conn->query("
                SELECT id FROM order_items 
                WHERE order_id = $oid AND product_id = $product_id AND stock_id = $stock_id LIMIT 1
            ")->fetch_assoc();
            $order_item_id = $oi['id'] ?? null;

            // insert refund record
            $refund_stmt->bind_param(
                "iiiiiddi",
                $oid,
                $order_item_id,
                $product_id,
                $stock_id,
                $size_id,
                $color_id,
                $amount,
                $admin_id
            );
            $refund_stmt->execute();

            // restock
            $stock_stmt->bind_param("ii", $refund_qty, $stock_id);
            $stock_stmt->execute();
        }

        // reduce order total
        $update_order_total->bind_param("di", $total_refund, $oid);
        $update_order_total->execute();

        // insert negative transaction
        $neg = -$total_refund;
        $insert_negative_transaction->bind_param("id", $oid, $neg);
        $insert_negative_transaction->execute();

        // update order status to refunded (4)
        $conn->query("UPDATE orders SET order_status_id = 4 WHERE order_id = $oid");

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'refund_total' => $total_refund
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit;
}




if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }

// Cashier Info
$cashierRes = $conn->prepare("SELECT first_name FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$r = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $r ? $r['first_name'] : 'Unknown';
$cashierRes->close();

// Data
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$payments = $conn->query("SELECT payment_method_id, payment_method_name FROM payment_methods ORDER BY payment_method_id ASC");
$products = $conn->query("SELECT p.product_id, p.product_name, p.price_id AS price, p.image_url, c.category_name, COALESCE(SUM(s.current_qty), 0) AS total_stock FROM products p LEFT JOIN categories c ON p.category_id = c.category_id LEFT JOIN stock s ON p.product_id = s.product_id GROUP BY p.product_id ORDER BY p.product_id DESC");

// Helpers
function getSizes($conn, $pid) { $a=[]; $res=$conn->query("SELECT DISTINCT s.size FROM stock st JOIN sizes s ON st.size_id=s.size_id WHERE st.product_id=".intval($pid)); while($r=$res->fetch_assoc())$a[]=$r['size']; return $a; }
function getColors($conn, $pid) { $a=[]; $res=$conn->query("SELECT DISTINCT c.color FROM stock st JOIN colors c ON st.color_id=c.color_id WHERE st.product_id=".intval($pid)); while($r=$res->fetch_assoc())$a[]=$r['color']; return $a; }

// CHECKOUT LOGIC
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $payment_method_id = intval($_POST['payment_method_id']);
    $cash_given = floatval($_POST['cash_given']);
    $total = floatval($_POST['total']);

    if (empty($cart)) echo "<script>alert('Cart empty');</script>";
    elseif (($cash_given - $total) < 0) echo "<script>alert('Insufficient cash');</script>";
    else {
        $conn->begin_transaction();
        try {
            $changes = $cash_given - $total;

            // 1. INSERT ORDER
            $sql = "INSERT INTO orders (admin_id, total_amount, cash_given, changes, order_status_id, created_at, payment_method_id) VALUES (?, ?, ?, ?, 0, NOW(), ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("idddi", $admin_id, $total, $cash_given, $changes, $payment_method_id);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            $stmt->close();

            // 2. INSERT ITEMS
            $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, color, size, stock_id, qty, price) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($cart as $item) {
                $pid = intval($item['product_id']);
                $qty = intval($item['quantity']);
                $price = floatval($item['price']);
                $color = $item['color'] ?? null;
                $size = $item['size'] ?? null;

                $cid=null; $sid=null;
                if($color){ $r=$conn->query("SELECT color_id FROM colors WHERE color='$color'"); if($d=$r->fetch_assoc())$cid=$d['color_id']; }
                if($size){ $r=$conn->query("SELECT size_id FROM sizes WHERE size='$size'"); if($d=$r->fetch_assoc())$sid=$d['size_id']; }

                $st=$conn->prepare("SELECT stock_id, current_qty FROM stock WHERE product_id=? AND (color_id=? OR ? IS NULL) AND (size_id=? OR ? IS NULL) LIMIT 1");
                $st->bind_param("iiiii", $pid, $cid, $cid, $sid, $sid);
                $st->execute();
                $sd = $st->get_result()->fetch_assoc();
                $st->close();

                if (!$sd || $sd['current_qty'] < $qty) throw new Exception("Out of stock: " . $item['name']);

                $stock_id = $sd['stock_id'];
                
                $itemStmt->bind_param("iissiid", $order_id, $pid, $color, $size, $stock_id, $qty, $price);
                $itemStmt->execute();

                $conn->query("UPDATE stock SET current_qty = current_qty - $qty WHERE stock_id = $stock_id");
            }
            $itemStmt->close();

            // 3. INSERT TRANSACTION
            $transStmt = $conn->prepare("INSERT INTO transactions (order_id, customer_id, payment_method_id, total, order_status_id, date_time) VALUES (?, NULL, ?, ?, 0, NOW())");
            $transStmt->bind_param("iid", $order_id, $payment_method_id, $total);
            $transStmt->execute();
            $transStmt->close();

            $conn->commit();
            echo "<script>window.location.href='receipt.php?order_id=$order_id';</script>";
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier POS | Seven Dwarfs Boutique</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { rose: { 50: '#fff1f2', 100: '#ffe4e6', 400: '#fb7185', 500: '#f43f5e', 600: '#e11d48', 700: '#be123c' } }, fontFamily: { sans: ['Poppins', 'sans-serif'] } } } }</script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 10px; }
        .fade-in { animation: fadeIn 0.3s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.03); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 h-screen w-screen overflow-hidden flex antialiased">

    <!-- SIDEBAR -->
    <aside class="w-[260px] bg-white border-r border-slate-100 flex flex-col z-30 transition-all duration-300" id="sidebar">
        <div class="h-20 flex items-center px-6 border-b border-slate-50">
            <div><h1 class="text-xl font-bold text-rose-600 tracking-tight">Seven Dwarfs</h1><p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest">Boutique POS</p></div>
            <button id="sidebarToggle" class="ml-auto lg:hidden text-slate-400"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2"/></svg></button>
        </div>
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <a href="cashier_pos.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-rose-50 text-rose-600 font-semibold transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg> POS Terminal</a>
            <a href="cashier_transactions.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg> Transactions</a>
            <a href="cashier_inventory.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-rose-600 font-medium transition-colors"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg> Inventory</a>
        </nav>
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-600 font-bold text-sm shadow-sm"><?= strtoupper(substr($cashier_name, 0, 1)) ?></div>
                <div><p class="text-sm font-bold text-slate-700 truncate"><?= htmlspecialchars($cashier_name) ?></p><p class="text-xs text-slate-400">Logged in</p></div>
            </div>
            <form action="logout.php" method="POST"><button class="w-full flex justify-center items-center gap-2 py-2.5 rounded-lg border border-slate-200 text-slate-500 text-sm font-medium hover:bg-white hover:text-rose-600 hover:border-rose-200 shadow-sm transition-all">Sign Out</button></form>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 flex flex-col relative overflow-hidden">
        <header class="h-20 glass-header flex items-center justify-between px-6 z-20 absolute top-0 w-full">
            <h2 class="text-xl font-bold text-slate-800">Menu</h2>
            <div class="flex gap-3">
                <div class="relative">
                    <select id="categoryFilter" class="appearance-none bg-white border border-slate-200 text-slate-600 py-2.5 pl-4 pr-10 rounded-xl focus:outline-none focus:ring-2 focus:ring-rose-200 text-sm font-medium shadow-sm cursor-pointer"><option value="">All Categories</option><?php while ($cat = $categories->fetch_assoc()): ?><option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']); ?></option><?php endwhile; ?></select>
                </div>
                <div class="relative"><input id="productSearch" type="search" placeholder="Search products..." class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-rose-200 w-64 shadow-sm"><svg class="w-4 h-4 absolute left-3.5 top-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg></div>
            </div>
        </header>

        <!-- Grid -->
        <div class="flex-1 overflow-y-auto p-6 pt-24" id="productContainer">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5 gap-5" id="productGrid">
                <?php while ($p = $products->fetch_assoc()): 
                    $sizes = getSizes($conn, $p['product_id']); $colors = getColors($conn, $p['product_id']);
                    $total_stock = intval($p['total_stock']);
                ?>
                <div class="group bg-white border border-slate-100 rounded-2xl p-4 flex flex-col justify-between transition-all duration-200 hover:shadow-lg hover:-translate-y-1 cursor-pointer <?= ($total_stock <= 0 ? 'opacity-60 grayscale' : '') ?>" onclick="<?= ($total_stock > 0 ? "openProductModal(this)" : "") ?>" data-id="<?= $p['product_id'] ?>" data-name="<?= htmlspecialchars($p['product_name']) ?>" data-price="<?= $p['price'] ?>" data-category="<?= htmlspecialchars($p['category_name']) ?>" data-sizes='<?= htmlspecialchars(json_encode($sizes), ENT_QUOTES) ?>' data-colors='<?= htmlspecialchars(json_encode($colors), ENT_QUOTES) ?>'>
                    <div>
                        <div class="flex justify-between items-start mb-2"><span class="text-[10px] font-bold tracking-wide text-slate-400 uppercase bg-slate-100 px-2 py-1 rounded-md"><?= htmlspecialchars($p['category_name']) ?></span></div>
                        <h3 class="font-bold text-slate-800 leading-tight mb-1 line-clamp-2 min-h-[2.5rem]"><?= htmlspecialchars($p['product_name']) ?></h3>
                        <div class="text-lg font-bold text-rose-600">₱<?= number_format($p['price'],2) ?></div>
                    </div>
                    <button class="w-full mt-4 py-2 rounded-xl font-semibold text-sm transition-colors <?= ($total_stock > 0 ? 'bg-rose-50 text-rose-600 group-hover:bg-rose-600 group-hover:text-white' : 'bg-slate-100 text-slate-400 cursor-not-allowed') ?>"><?= $total_stock > 0 ? 'Add to Cart' : 'Out of Stock' ?></button>
                </div>
                <?php endwhile; ?>
            </div>
            <div id="productPagination" class="flex justify-center gap-2 mt-8 pb-4"></div>
        </div>
    </main>

    <!-- CART SIDEBAR -->
    <aside class="w-[400px] bg-white border-l border-slate-100 flex flex-col shadow-xl z-40">
        <div class="h-20 glass-header flex items-center px-6"><h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">Current Order</h3></div>
        <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50/50" id="cartList"><div id="emptyCartState" class="h-full flex flex-col items-center justify-center text-slate-400"><p class="text-sm font-medium">No items added yet</p></div></div>
        <div class="p-6 bg-white border-t border-slate-100 shadow-[0_-4px_20px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-end mb-4"><span class="text-sm font-semibold text-slate-500">Total Amount</span><span id="cartTotal" class="text-3xl font-bold text-slate-800 tracking-tight">₱0.00</span></div>
            <form method="POST" onsubmit="prepareCartData()" class="space-y-4">
                <input type="hidden" name="cart_data" id="cartData"><input type="hidden" name="total" id="totalField">
                <div class="grid grid-cols-2 gap-3">

                    <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Payment</label><select name="payment_method_id" id="paymentMethodSelect" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium focus:ring-2 focus:ring-rose-200 outline-none"><?php $payments->data_seek(0); while ($pay = $payments->fetch_assoc()): ?><option value="<?= $pay['payment_method_id'] ?>"><?= htmlspecialchars($pay['payment_method_name']) ?></option><?php endwhile; ?></select></div>

                    <div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Cash Given</label><input type="number" name="cash_given" id="cashGiven" step="0.01" placeholder="0.00" oninput="updateChange()" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm font-medium text-right focus:ring-2 focus:ring-rose-200 outline-none"></div>
                </div>
                <div id="gcashRefDiv" class="hidden"><label class="block text-xs font-bold text-rose-500 uppercase mb-1">GCash Ref No.</label><input type="text" name="gcash_ref" placeholder="Enter Reference #" class="w-full border-2 border-rose-100 rounded-xl px-3 py-2 text-sm focus:border-rose-400 outline-none"></div>
                <div id="cartChange" class="min-h-[1.5rem]"></div>
                <div class="flex gap-3 pt-2"><button type="button" id="openReturnModal" class="p-3.5 rounded-xl bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition-colors" title="Return Item"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg></button><button type="submit" name="checkout" class="flex-1 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl py-3.5 shadow-lg shadow-rose-200 transition-all transform active:scale-[0.98]">Process Payment</button></div>
            </form>
        </div>
    </aside>

    <!-- MODALS -->
    <div id="optionModal" class="fixed inset-0 z-50 bg-slate-900/30 backdrop-blur-sm hidden flex items-center justify-center opacity-0 transition-opacity duration-300"><div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl transform scale-95 transition-transform duration-300 p-6" id="optionModalContent"><div class="flex justify-between items-center mb-5"><h4 class="text-lg font-bold text-slate-800" id="modalProductName">Select Options</h4><button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button></div><div class="space-y-4"><div id="sizeDiv"><label class="text-xs font-bold text-slate-400 uppercase mb-2 block">Size</label><div id="sizeOptions" class="flex flex-wrap gap-2"></div></div><div id="colorDiv"><label class="text-xs font-bold text-slate-400 uppercase mb-2 block">Color</label><div id="colorOptions" class="flex flex-wrap gap-2"></div></div></div><div class="flex gap-3 mt-8"><button onclick="closeModal()" class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-medium hover:bg-slate-50 transition">Cancel</button><button id="confirmAdd" class="flex-1 py-2.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700 shadow-lg shadow-rose-200 transition">Add Item</button></div></div></div>
    
    <div id="returnModalBackdrop" class="fixed inset-0 z-50 bg-slate-900/20 backdrop-blur-sm hidden transition-opacity duration-300"></div>
    <div id="returnModal" class="fixed inset-y-0 right-0 z-50 w-[500px] bg-white shadow-2xl transform translate-x-full transition-transform duration-300 flex flex-col">
        <div class="h-20 flex items-center justify-between px-6 border-b border-slate-100 bg-rose-50/50"><div><h4 class="text-lg font-bold text-rose-800">Process Return</h4><p class="text-xs text-rose-500">Look up order and select items</p></div><button id="closeReturnModal" class="text-slate-400 hover:text-rose-600 transition text-2xl">&times;</button></div>
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200"><label class="block text-xs font-bold text-slate-500 uppercase mb-2">Find Order</label><div class="flex gap-2"><input type="number" id="searchOrderId" class="flex-1 bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-rose-200 transition" placeholder="Order ID"><button onclick="fetchOrderItems()" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-slate-900 transition">Find</button></div><p id="orderMsg" class="text-xs mt-2 font-medium hidden"></p></div>
            <div id="returnItemsContainer" class="hidden space-y-4"><h5 class="font-bold text-slate-700">Select Items to Return</h5><form id="refundForm" class="space-y-3"><input type="hidden" name="order_id" id="finalOrderId"><div id="itemsList" class="space-y-3"></div><div class="pt-4 border-t border-slate-100 space-y-3"><div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Reason</label><select name="return_reason" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm"><option>Damaged</option><option>Wrong Size</option><option>Change of Mind</option></select></div><div><label class="block text-xs font-bold text-slate-500 uppercase mb-1">Notes</label><textarea name="return_notes" rows="2" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm resize-none"></textarea></div></div></form></div>
        </div>
        <div class="p-6 border-t border-slate-100 bg-slate-50"><button type="button" onclick="submitRefund()" id="submitRefundBtn" class="w-full bg-rose-600 text-white font-bold py-3 rounded-xl hover:bg-rose-700 transition shadow-lg disabled:opacity-50 disabled:cursor-not-allowed" disabled>Confirm Refund</button></div>
    </div>

    <script>
        /* --- JS LOGIC --- */
        const productCards=Array.from(document.querySelectorAll('.group[data-id]'));const searchInput=document.getElementById('productSearch');const catFilter=document.getElementById('categoryFilter');const paginationEl=document.getElementById('productPagination');let currentPage=1;const itemsPerPage=12;
        function renderProducts(){const q=searchInput.value.toLowerCase().trim();const cat=catFilter.value;const filtered=productCards.filter(el=>{const name=(el.dataset.name||'').toLowerCase();const category=el.dataset.category||'';return name.includes(q)&&(!cat||category===cat);});const totalPages=Math.ceil(filtered.length/itemsPerPage)||1;if(currentPage>totalPages)currentPage=1;productCards.forEach(el=>el.classList.add('hidden'));filtered.slice((currentPage-1)*itemsPerPage,currentPage*itemsPerPage).forEach((el,i)=>{el.classList.remove('hidden');el.style.animation=`fadeIn 0.3s ease-out ${i*0.05}s forwards`;});paginationEl.innerHTML='';if(totalPages>1){for(let i=1;i<=totalPages;i++){const b=document.createElement('button');b.textContent=i;b.className=`w-8 h-8 rounded-lg text-sm font-bold transition ${i===currentPage?'bg-rose-600 text-white shadow-md':'bg-white text-slate-600 hover:bg-slate-100 border'}`;b.onclick=()=>{currentPage=i;renderProducts();};paginationEl.appendChild(b);}}}
        searchInput.addEventListener('input',()=>{currentPage=1;renderProducts();});catFilter.addEventListener('change',()=>{currentPage=1;renderProducts();});window.addEventListener('DOMContentLoaded',renderProducts);

        let cart=[];let currentProduct=null;const modal=document.getElementById('optionModal');const modalContent=document.getElementById('optionModalContent');
        window.openProductModal=(card)=>{currentProduct={id:card.dataset.id,name:card.dataset.name,price:parseFloat(card.dataset.price),sizes:JSON.parse(card.dataset.sizes||'[]'),colors:JSON.parse(card.dataset.colors||'[]')};document.getElementById('modalProductName').textContent=currentProduct.name;const sDiv=document.getElementById('sizeOptions');sDiv.innerHTML='';if(currentProduct.sizes.length){document.getElementById('sizeDiv').style.display='block';currentProduct.sizes.forEach(s=>{const b=document.createElement('button');b.textContent=s;b.className='px-4 py-2 border rounded-lg text-sm font-medium hover:border-rose-500';b.onclick=()=>{Array.from(sDiv.children).forEach(x=>x.className='px-4 py-2 border rounded-lg text-sm font-medium hover:border-rose-500');b.className='px-4 py-2 border rounded-lg text-sm font-medium bg-rose-600 text-white border-rose-600 selected';};sDiv.appendChild(b);});}else document.getElementById('sizeDiv').style.display='none';const cDiv=document.getElementById('colorOptions');cDiv.innerHTML='';if(currentProduct.colors.length){document.getElementById('colorDiv').style.display='block';currentProduct.colors.forEach(c=>{const b=document.createElement('button');b.textContent=c;b.className='px-4 py-2 border rounded-lg text-sm font-medium hover:border-rose-500';b.onclick=()=>{Array.from(cDiv.children).forEach(x=>x.className='px-4 py-2 border rounded-lg text-sm font-medium hover:border-rose-500');b.className='px-4 py-2 border rounded-lg text-sm font-medium bg-rose-600 text-white border-rose-600 selected';};cDiv.appendChild(b);});}else document.getElementById('colorDiv').style.display='none';modal.classList.remove('hidden');setTimeout(()=>{modal.classList.remove('opacity-0');modalContent.classList.remove('scale-95');modalContent.classList.add('scale-100');},10);}
        window.closeModal=()=>{modal.classList.add('opacity-0');modalContent.classList.remove('scale-100');modalContent.classList.add('scale-95');setTimeout(()=>modal.classList.add('hidden'),300);}
        document.getElementById('confirmAdd').onclick=()=>{const size=document.querySelector('#sizeOptions .selected')?.textContent;const color=document.querySelector('#colorOptions .selected')?.textContent;if(document.getElementById('sizeDiv').style.display!=='none'&&!size)return alert("Select size");const exist=cart.find(i=>i.product_id===currentProduct.id&&i.size===size&&i.color===color);if(exist)exist.quantity++;else cart.push({product_id:currentProduct.id,name:currentProduct.name,price:currentProduct.price,quantity:1,size,color});renderCart();closeModal();}
        function renderCart(){const list=document.getElementById('cartList');list.innerHTML='';if(!cart.length){list.innerHTML='<div class="h-full flex flex-col items-center justify-center text-slate-400 text-sm">Empty Cart</div>';document.getElementById('cartTotal').textContent='₱0.00';document.getElementById('totalField').value=0;return;}let total=0;cart.forEach((item,i)=>{total+=item.price*item.quantity;list.innerHTML+=`<div class="bg-white p-3 rounded-xl border border-slate-100 flex justify-between items-center fade-in"><div class="flex-1 min-w-0"><div class="font-bold text-sm truncate">${item.name}</div><div class="text-xs text-slate-500">${item.size||''} ${item.color||''}</div><div class="text-rose-600 font-bold mt-1">₱${(item.price*item.quantity).toFixed(2)}</div></div><div class="flex flex-col items-end gap-2"><div class="flex items-center bg-slate-100 rounded-lg"><button onclick="upd(${i},-1)" class="w-6 h-6">-</button><span class="text-xs font-bold w-6 text-center">${item.quantity}</span><button onclick="upd(${i},1)" class="w-6 h-6">+</button></div><button onclick="rm(${i})" class="text-[10px] text-rose-500">Remove</button></div></div>`;});document.getElementById('cartTotal').textContent='₱'+total.toFixed(2);document.getElementById('totalField').value=total;updateChange();}
        window.upd=(i,d)=>{cart[i].quantity+=d;if(cart[i].quantity<1)cart[i].quantity=1;renderCart();}
        window.rm=(i)=>{cart.splice(i,1);renderCart();}
        window.prepareCartData=()=>{document.getElementById('cartData').value=JSON.stringify(cart);}
        window.updateChange=()=>{const t=parseFloat(document.getElementById('totalField').value)||0,c=parseFloat(document.getElementById('cashGiven').value)||0,d=c-t;document.getElementById('cartChange').innerHTML=c>0?(d>=0?`<div class="text-green-600 font-bold text-sm bg-green-50 p-2 rounded">Change: ₱${d.toFixed(2)}</div>`:`<div class="text-red-500 font-bold text-sm bg-red-50 p-2 rounded">Short: ₱${Math.abs(d).toFixed(2)}</div>`):'';}
        document.getElementById('paymentMethodSelect').addEventListener('change',function(){document.getElementById('gcashRefDiv').className=this.options[this.selectedIndex].text.toLowerCase().includes('gcash')?'block fade-in':'hidden';});

        const rBackdrop=document.getElementById('returnModalBackdrop');const rModal=document.getElementById('returnModal');
        document.getElementById('openReturnModal').onclick=()=>{rBackdrop.classList.remove('hidden');setTimeout(()=>rBackdrop.classList.add('opacity-100'),10);rModal.classList.remove('translate-x-full');document.getElementById('searchOrderId').value='';document.getElementById('returnItemsContainer').classList.add('hidden');document.getElementById('itemsList').innerHTML='';document.getElementById('submitRefundBtn').disabled=true;document.getElementById('orderMsg').classList.add('hidden');}
        const closeReturn=()=>{rModal.classList.add('translate-x-full');rBackdrop.classList.remove('opacity-100');setTimeout(()=>rBackdrop.classList.add('hidden'),300);}
        document.getElementById('closeReturnModal').onclick=closeReturn;rBackdrop.onclick=closeReturn;

        async function fetchOrderItems() {
            const oid=document.getElementById('searchOrderId').value;const msg=document.getElementById('orderMsg');if(!oid)return alert("Enter Order ID");msg.textContent="Searching...";msg.className="text-xs mt-2 text-slate-500 block";
            try{
                const response=await fetch(`?action=get_order_details&oid=${oid}`);
                const textData=await response.text();
                let data; try{data=JSON.parse(textData);}catch(e){console.error(textData);return alert("Server Error (Check Console)");}
                if(data.status==='error'){msg.textContent="❌ "+data.message;msg.className="text-xs mt-2 text-rose-500 font-bold block";document.getElementById('returnItemsContainer').classList.add('hidden');return;}
                msg.textContent="✅ Found";msg.className="text-xs mt-2 text-green-600 font-bold block";document.getElementById('finalOrderId').value=oid;document.getElementById('returnItemsContainer').classList.remove('hidden');const list=document.getElementById('itemsList');list.innerHTML='';
                data.items.forEach(item=>{
                    const row=document.createElement('div');row.className="group flex items-start gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:border-rose-400 cursor-pointer transition";
                    row.onclick=(e)=>{if(e.target.tagName!=='INPUT'){const cb=row.querySelector('.item-cb');cb.checked=!cb.checked;cb.dispatchEvent(new Event('change'));}};
                    // Removed order_item_id from data attributes, using product_id and stock_id to identify
                    row.innerHTML=`<div class="pt-1"><input type="checkbox" class="w-5 h-5 text-rose-600 item-cb" data-stock="${item.stock_id}" data-price="${item.price}" data-pid="${item.product_id}" data-qty="${item.qty}"></div><div class="flex-1"><div class="flex justify-between"><h6 class="font-bold text-sm text-slate-800">${item.product_name}</h6><span class="text-xs font-bold bg-slate-100 px-2 py-1 rounded">₱${parseFloat(item.price).toFixed(2)}</span></div><div class="flex gap-2 mt-2"><span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold">${item.size_name}</span><span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold">${item.color_name}</span><span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-1 rounded font-bold">Qty: ${item.qty}</span></div><div class="qty-control hidden mt-3 pt-3 border-t border-dashed border-slate-100 flex items-center gap-2"><span class="text-xs font-bold text-rose-600">Return Qty:</span><input type="number" min="1" max="${item.qty}" value="1" class="return-qty w-12 border rounded text-center text-xs font-bold" onclick="event.stopPropagation()"></div></div>`;
                    list.appendChild(row);
                });document.querySelectorAll('.item-cb').forEach(cb=>cb.addEventListener('change',validateRefund));
            }catch(err){console.error(err);msg.textContent="Network Error";}
        }

        function validateRefund(){let any=false;document.querySelectorAll('.item-cb').forEach(box=>{const qtyDiv=box.closest('.group').querySelector('.qty-control');if(box.checked){any=true;qtyDiv.classList.remove('hidden');}else qtyDiv.classList.add('hidden');});document.getElementById('submitRefundBtn').disabled=!any;}

        async function submitRefund(){
            const items=[];const boxes=document.querySelectorAll('.item-cb:checked');
            for(const box of boxes){const row=box.closest('.group');const rQty=parseInt(row.querySelector('.return-qty').value);const maxQty=parseInt(box.dataset.qty);if(rQty>maxQty||rQty<=0)return alert("Invalid quantity");items.push({stock_id:box.dataset.stock,product_id:box.dataset.pid,qty:maxQty,refund_qty:rQty,price:box.dataset.price});}
            if(!items.length)return;
            try{
                const res=await fetch('?action=process_refund',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({order_id:document.getElementById('finalOrderId').value,items:items})});
                const text=await res.text();let data;try{data=JSON.parse(text);}catch(e){console.error(text);return alert("Server Error");}
                if(data.status==='success'){alert("Refund Success! Total: ₱"+data.refund_total);closeReturn();location.reload();}else alert("Error: "+data.message);
            }catch(e){alert("System Error");}
        }
        document.getElementById('sidebarToggle').onclick=()=>{const s=document.getElementById('sidebar');s.style.position=s.style.position==='absolute'?'relative':'absolute';s.style.height='100%';s.style.zIndex=50;s.classList.toggle('-translate-x-full');}
    </script>
</body>
</html>
