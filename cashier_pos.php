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

$admin_id = $_SESSION['admin_id'] ?? null;

// Fetch cashier info
$cashierRes = $conn->prepare("SELECT first_name FROM adminusers WHERE admin_id = ?");
$cashierRes->bind_param("i", $admin_id);
$cashierRes->execute();
$cashierRow = $cashierRes->get_result()->fetch_assoc();
$cashier_name = $cashierRow ? $cashierRow['first_name'] : 'Unknown Cashier';
$cashierRes->close();

// Fetch categories
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");

// Fetch sizes and colors directly from stock (return names, not IDs)
function getSizes($conn, $pid) {
    $sizes = [];
    $pid = intval($pid);
    $res = $conn->query("SELECT DISTINCT s.size FROM stock st INNER JOIN sizes s ON st.size_id = s.size_id WHERE st.product_id = $pid AND st.size_id IS NOT NULL");
    while ($r = $res->fetch_assoc()) $sizes[] = $r['size'];
    return $sizes;
}
function getColors($conn, $pid) {
    $colors = [];
    $pid = intval($pid);
    $res = $conn->query("SELECT DISTINCT c.color FROM stock st INNER JOIN colors c ON st.color_id = c.color_id WHERE st.product_id = $pid AND st.color_id IS NOT NULL");
    while ($r = $res->fetch_assoc()) $colors[] = $r['color'];
    return $colors;
}

// Fetch products — include total stock from `stock` table
$products = $conn->query("
    SELECT 
        p.product_id, 
        p.product_name, 
        p.price_id AS price, 
        p.image_url, 
        c.category_name, 
        COALESCE(SUM(s.current_qty), 0) AS total_stock 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.category_id 
    LEFT JOIN stock s ON p.product_id = s.product_id 
    GROUP BY p.product_id 
    ORDER BY p.product_id DESC
");

// Fetch payment methods
$payments = $conn->query("SELECT payment_method_id, payment_method_name FROM payment_methods ORDER BY payment_method_id ASC");

// (checkout logic unchanged) ...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $payment_method_id = intval($_POST['payment_method_id']);
    $cash_given = floatval($_POST['cash_given']);
    $total = floatval($_POST['total']);
    $gcash_ref = isset($_POST['gcash_ref']) ? trim($_POST['gcash_ref']) : null;

    if (empty($cart)) {
        echo "<script>alert('Cart is empty!');</script>";
        exit;
    }

    $changes = $cash_given - $total;
    if ($changes < 0) {
        echo "<script>alert('Cash given is insufficient.');</script>";
        exit;
    }

    $conn->begin_transaction();

    try {
        if ($payment_method_id == 1) { // GCash
            $stmt = $conn->prepare("INSERT INTO orders (admin_id, total_amount, cash_given, changes, order_status_id, created_at, payment_method_id, gcash_ref) VALUES (?, ?, ?, ?, 0, NOW(), ?, ?)");
            $stmt->bind_param("idddis", $admin_id, $total, $cash_given, $changes, $payment_method_id, $gcash_ref);
        } else {
            $stmt = $conn->prepare("INSERT INTO orders (admin_id, total_amount, cash_given, changes, order_status_id, created_at, payment_method_id) VALUES (?, ?, ?, ?, 0, NOW(), ?)");
            $stmt->bind_param("idddi", $admin_id, $total, $cash_given, $changes, $payment_method_id);
        }
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, color, size, stock_id, qty, price) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($cart as $item) {
            $product_id = intval($item['product_id']);
            $qty = intval($item['quantity']);
            $price = floatval($item['price']);
            $color = $item['color'] ?? null;
            $size = $item['size'] ?? null;

            $color_id = null;
            $size_id = null;

            if (!empty($color)) {
                $colorStmt = $conn->prepare("SELECT color_id FROM colors WHERE color = ? LIMIT 1");
                $colorStmt->bind_param("s", $color);
                $colorStmt->execute();
                $colorRow = $colorStmt->get_result()->fetch_assoc();
                $colorStmt->close();
                if ($colorRow) $color_id = $colorRow['color_id'];
            }

            if (!empty($size)) {
                $sizeStmt = $conn->prepare("SELECT size_id FROM sizes WHERE size = ? LIMIT 1");
                $sizeStmt->bind_param("s", $size);
                $sizeStmt->execute();
                $sizeRow = $sizeStmt->get_result()->fetch_assoc();
                $sizeStmt->close();
                if ($sizeRow) $size_id = $sizeRow['size_id'];
            }

            $stockRes = $conn->prepare("SELECT stock_id, current_qty FROM stock WHERE product_id = ? AND (color_id = ? OR ? IS NULL) AND (size_id = ? OR ? IS NULL) LIMIT 1");
            $stockRes->bind_param("iiiii", $product_id, $color_id, $color_id, $size_id, $size_id);
            $stockRes->execute();
            $stockData = $stockRes->get_result()->fetch_assoc();
            $stockRes->close();

            if (!$stockData || $stockData['current_qty'] < $qty) {
                throw new Exception("Insufficient stock for product ID: $product_id");
            }

            $stock_id = $stockData['stock_id'];

            $itemStmt->bind_param("iissiid", $order_id, $product_id, $color, $size, $stock_id, $qty, $price);
            $itemStmt->execute();

            $newQty = $stockData['current_qty'] - $qty;
            $updateStock = $conn->prepare("UPDATE stock SET current_qty = ? WHERE stock_id = ?");
            $updateStock->bind_param("ii", $newQty, $stock_id);
            $updateStock->execute();
            $updateStock->close();
        }
        $itemStmt->close();

        $t = $conn->prepare("INSERT INTO transactions (order_id, customer_id, payment_method_id, total, order_status_id, date_time) VALUES (?, NULL, ?, ?, 0, NOW())");
        $t->bind_param("iid", $order_id, $payment_method_id, $total);
        $t->execute();
        $t->close();

        $conn->commit();

        echo "<script>window.location.href='receipt.php?order_id=$order_id';</script>";
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Transaction failed: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// NOTE: Database dump available at: /mnt/data/dbms (16).sql
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cashier POS | Seven Dwarfs Boutique</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root{--rose:#d37689;--rose-600:#b75f6f;--card-bg:#fff;--cart-bg:#f9e9ed}
body{background:#fef9fa;font-family:'Poppins',sans-serif}
/* Sidebar */
.sidebar {
  width: 260px;
  background: linear-gradient(135deg,#fef2f4 0%,#f9e9ed 100%);
  border-right: 1px solid #f3dbe2;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  z-index: 30;
  border-top-right-radius: 18px;
  border-bottom-right-radius: 18px;
  transition: width 0.2s;
}
.sidebar.collapsed {
  width: 60px;
  padding: 1.25rem 0.5rem;
}
.sidebar.collapsed .sidebar-title,
.sidebar.collapsed .text-xs,
.sidebar.collapsed nav a span,
.sidebar.collapsed .sidebar-footer {
  display: none !important;
}
.sidebar-header{display:flex;align-items:center;gap:12px;margin-bottom:1.2rem}
/* .sidebar-logo removed: no longer used */
.sidebar-title{font-size:1.2rem;font-weight:700;color:var(--rose)}
.sidebar nav{margin-top:8px}
.sidebar a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0.6rem 0.8rem;
  border-radius: 10px;
  color: #374151;
  text-decoration: none;
  font-weight: 600;
  margin-bottom: 6px;
  position: relative;
}
.sidebar.collapsed a {
  justify-content: center;
  padding: 0.6rem 0.2rem;
}
.sidebar.collapsed a svg {
  margin-right: 0;
}
.sidebar.collapsed a[title]:hover::after {
  content: attr(title);
  position: absolute;
  left: 60px;
  top: 50%;
  transform: translateY(-50%);
  background: #fff;
  color: #d37689;
  border-radius: 6px;
  padding: 4px 10px;
  font-size: 0.95rem;
  box-shadow: 0 2px 8px rgba(211,118,137,0.12);
  white-space: nowrap;
  z-index: 100;
}
.sidebar a:hover{background:#fff2f5;color:var(--rose-600)}
.sidebar .active-link{background:linear-gradient(90deg,var(--rose) 65%,#fff);color:#fff}
.sidebar-footer{margin-top:auto;border-top:1px solid #f3dbe2;padding-top:12px}
/* Main */
.main-content {
  margin-left: 260px;
  padding: 0;
  height: 100vh;
  width: calc(100vw - 260px);
  display: flex;
  align-items: stretch;
  justify-content: stretch;
  background: #fef9fa;
}
.pos-card {
  background: var(--card-bg);
  border-radius: 16px;
  box-shadow: 0 8px 30px rgba(211,118,137,0.06);
  padding: 24px;
  display: flex;
  gap: 24px;
  align-items: stretch;
  width: 100%;
  height: 100%;
  margin: 0;
  max-width: none;
}
.product-section {
  flex: 1 1 0%;
  display: flex;
  flex-direction: column;
  height: 100%;
}
#productGrid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 16px;
  grid-auto-rows: 1fr;
}
.product {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #f3dbe2;
  padding: 8px 10px;
  display: flex;
  flex-direction: column;
  gap: 7px;
  box-shadow: 0 2px 8px rgba(211,118,137,0.06);
  margin-top: 0;
  width: 100%;
  height: 100%;
  justify-content: space-between;
  box-sizing: border-box;
}
.product.opacity-50{opacity:0.5}
/* .product img removed: no longer used */
.product .meta {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  margin-top: 0;
}
.addToCart{background:var(--rose);color:#fff;padding:8px;border-radius:8px;font-weight:600}
.addToCart:hover{background:var(--rose-600)}
/* Cart */
.cart-section {
  width: 380px;
  background: var(--cart-bg);
  border-radius: 12px;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  height: 100%;
  min-height: 0;
}
.cart-table-wrapper{background:#fff;border-radius:8px;padding:8px;overflow:auto;flex:1}
.cart-item{display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid #f3dbe2}
.qty-control{display:inline-flex;align-items:center;gap:6px}
.cart-footer{display:flex;flex-direction:column;gap:8px}
.checkout-btn{background:var(--rose);color:#fff;padding:12px;border-radius:10px;font-weight:700}
/* Modal */
.modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:60}
.modal{background:#fff;padding:18px;border-radius:12px;width:360px}
.size-btn,.color-btn{padding:6px 10px;border-radius:8px;border:1px solid #e6e6e6;background:#fafafa}
.size-btn.selected,.color-btn.selected{background:var(--rose);color:#fff;border-color:var(--rose)}
/* Responsive */
@media (max-width:1100px){#productGrid{grid-template-columns:repeat(3,1fr)}.cart-section{position:relative;width:100%;height:auto}}@media (max-width:760px){#productGrid{grid-template-columns:repeat(2,1fr)}.main-content{margin-left:20px;padding:12px}.sidebar{display:none}.pos-card{flex-direction:column}.cart-section{order:2}}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <button id="sidebarToggle" style="background:#fff;border-radius:8px;padding:6px 10px;border:1px solid #f3dbe2;color:var(--rose);cursor:pointer;position:absolute;top:12px;right:12px;z-index:40;">
    ☰
  </button>
  <div>
    <div class="sidebar-header">
      <!-- Logo image removed -->
      <div>
        <div class="sidebar-title">Seven Dwarfs</div>
        <div class="text-xs text-gray-500">Point of Sale</div>
      </div>
    </div>
    <nav>
      <a href="cashier_pos.php" class="active-link" title="POS">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M6 7V6a6 6 0 1 1 12 0v1"/><rect x="4" y="7" width="16" height="13" rx="3"/><path d="M9 11v2m6-2v2"/></svg>
        <span>POS</span>
      </a>
      <a href="cashier_transactions.php" title="Transactions">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M3 10h18"/><path d="M7 15h2"/></svg>
        <span>Transactions</span>
      </a>
      <a href="cashier_inventory.php" title="Inventory">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M3 7l9 5 9-5"/><path d="M12 12v5"/></svg>
        <span>Inventory</span>
      </a>
    </nav>
  </div>
  <div class="sidebar-footer">
    <div class="sidebar-cashier">👤 <span class="ml-2">Cashier: <strong style="color:var(--rose)"><?= htmlspecialchars($cashier_name); ?></strong></span></div>
    <form action="logout.php" method="POST">
      <button class="sidebar-logout-btn flex items-center justify-center gap-2 w-full px-4 py-3 rounded-lg border border-pink-200 bg-white text-pink-600 font-bold text-base hover:bg-pink-50 hover:text-pink-700 transition shadow-sm"
        style="margin-top:10px;">
        <span class="inline-block bg-pink-100 text-pink-600 rounded-full p-1">
          <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 16l-4-4 4-4"/><path d="M5 12h12"/><path d="M17 16v1a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v1"/></svg>
        </span>
        Logout
      </button>
    </form>
  </div>
</aside>

<!-- Main Content -->
<div class="main-content">
  <div class="pos-card">

    <!-- Products -->
    <div class="product-section">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="font-size:1.05rem;font-weight:700;color:var(--rose)">Products</h2>
        <div style="display:flex;gap:10px;align-items:center">
          <select id="categoryFilter" class="border rounded px-3 py-1 text-sm" aria-label="Filter category">
            <option value="">All Categories</option>
            <?php while ($cat = $categories->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
            <?php endwhile; ?>
          </select>
          <input id="productSearch" type="search" placeholder="Search product..." class="border rounded px-3 py-1 text-sm" aria-label="Search products">
        </div>
      </div>

      <div id="productGrid">
        <?php while ($p = $products->fetch_assoc()):
          $sizes = getSizes($conn, $p['product_id']);
          if (empty($sizes)) $sizes = ['S','M','L'];
          $colors = getColors($conn, $p['product_id']);
          $imagePath = $p['image_url'];
          $total_stock = isset($p['total_stock']) ? intval($p['total_stock']) : 0;
          if (!empty($imagePath)) {
              if (str_starts_with(trim($imagePath), '[')) {
                  $decoded = json_decode($imagePath, true);
                  $img = is_array($decoded) && count($decoded) > 0 ? $decoded[0] : 'uploads/default.png';
              } elseif (str_contains($imagePath, ',')) {
                  $parts = explode(',', $imagePath);
                  $img = trim($parts[0]);
              } else $img = trim($imagePath);
          } else $img = 'uploads/default.png';
        ?>
        <div class="product <?= ($total_stock <= 0 ? 'opacity-50' : '') ?>" tabindex="0" role="group" aria-label="Product <?= htmlspecialchars($p['product_name']) ?>"
             data-category="<?= htmlspecialchars($p['category_name']) ?>"
             data-id="<?= $p['product_id'] ?>"
             data-name="<?= htmlspecialchars($p['product_name']) ?>"
             data-price="<?= $p['price'] ?>"
             data-sizes='<?= htmlspecialchars(json_encode($sizes), ENT_QUOTES) ?>'
             data-colors='<?= htmlspecialchars(json_encode($colors), ENT_QUOTES) ?>'
             data-stock="<?= $total_stock ?>">
          <!-- Product image removed -->
          <div class="meta">
            <div style="font-weight:700;font-size:1.08rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;">
              <?= htmlspecialchars($p['product_name']) ?>
            </div>
            <div class="text-sm text-gray-500" style="margin-bottom:8px;">
              <?= htmlspecialchars($p['category_name']) ?>
            </div>
            <div style="font-weight:700;color:var(--rose);font-size:1.1rem;margin-bottom:10px;">
              ₱<?= number_format($p['price'],2) ?>
            </div>
            <?php if ($total_stock > 0): ?>
              <button class="addToCart" type="button" style="margin-top:0;padding:10px 22px;font-weight:600;font-size:1rem;border-radius:10px;background:var(--rose);color:#fff;box-shadow:0 2px 6px rgba(211,118,137,0.08);border:none;transition:background 0.2s;">
                Add
              </button>
            <?php else: ?>
              <button class="addToCart" type="button" disabled style="margin-top:0;padding:10px 22px;font-weight:600;font-size:1rem;border-radius:10px;background:#ccc;color:#fff;cursor:not-allowed;box-shadow:0 2px 6px rgba(211,118,137,0.08);border:none;">
                Out of Stock
              </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endwhile; ?>
      </div>

      <!-- Product pagination (client) -->
      <div id="productPagination" style="margin-top:14px;display:flex;gap:6px;justify-content:center"></div>

    </div>

    
    <!-- Cart -->
    <aside class="cart-section" aria-label="Cart area">
      <h3 style="color:var(--rose);font-weight:700">Cart</h3>
      <div class="cart-table-wrapper" id="cartWrapper">
        <div id="cartList"></div>
      </div>

      <div class="cart-footer">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div id="cartChange" style="color:var(--rose);font-weight:700"></div>
          <div id="cartTotal" style="font-weight:800">Total: ₱0.00</div>
        </div>

        <form method="POST" onsubmit="prepareCartData()" style="display:flex;flex-direction:column;gap:8px">
          <input type="hidden" name="cart_data" id="cartData">
          <input type="hidden" name="total" id="totalField">

          <label class="text-sm font-medium">Cash Given (₱)</label>
          <input type="number" name="cash_given" step="0.01" required oninput="updateChange()" id="cashGiven" class="border rounded px-3 py-2">

          <label class="text-sm font-medium">Payment Method</label>
          <select name="payment_method_id" id="paymentMethodSelect" class="border rounded px-3 py-2" onchange="toggleGcashRef()">
            <?php $payments->data_seek(0); while ($pay = $payments->fetch_assoc()): ?>
              <option value="<?= $pay['payment_method_id'] ?>"><?= htmlspecialchars($pay['payment_method_name']) ?></option>
            <?php endwhile; ?>
          </select>

          <div id="gcashRefDiv" style="display:none">
            <label class="text-sm font-medium">GCash Reference Number</label>
            <input type="text" name="gcash_ref" id="gcashRefInput" class="border-2 border-[var(--rose)] rounded px-3 py-2" maxlength="50" placeholder="Enter GCash Ref #">
            <button type="button" id="openReturnModal" style="margin-left:8px;background:var(--rose);color:#fff;padding:8px 14px;border-radius:8px;font-weight:600;">Return</button>
          </div>

          <button type="submit" name="checkout" class="checkout-btn">Checkout</button>
        </form>
      </div>
    </aside>

  </div> 
</div> 

<!-- Modal -->
<div id="optionModal" class="modal-backdrop" style="display:none">
  <div class="modal" role="dialog" aria-modal="true">
    <button id="closeModalBg" style="float:right;background:none;border:none;font-size:18px">&times;</button>
    <h4 id="modalProductName" style="font-weight:700;margin-bottom:8px">Select Options</h4>
    <div id="sizeDiv"><label class="text-sm font-medium">Size</label><div id="sizeOptions" style="display:flex;gap:8px;margin-top:8px"></div></div>
    <div id="colorDiv" style="margin-top:12px"><label class="text-sm font-medium">Color</label><div id="colorOptions" style="display:flex;gap:8px;margin-top:8px"></div></div>
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px"><button id="cancelModal" class="" style="padding:8px 12px;border-radius:8px;border:1px solid #e6e6e6">Cancel</button><button id="confirmAdd" style="background:var(--rose);color:#fff;padding:8px 12px;border-radius:8px">Add</button></div>
  </div>
</div>

<!-- Return Modal -->
<!-- Return Drawer Modal -->
<div id="returnModal" class="fixed top-0 right-0 z-[100] h-full w-full max-w-sm bg-white shadow-2xl border-l border-pink-100 transition-transform duration-300 ease-in-out"
     style="transform:translateX(100%);display:block;pointer-events:none;">
  <div class="flex flex-col h-full">
    <div class="flex items-center justify-between px-6 py-4 border-b border-pink-100">
      <div class="flex items-center gap-2">
        <span class="inline-block bg-pink-100 text-pink-600 rounded-full p-2">
          <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12v-2a9 9 0 1 1 9 9h-2"/><path d="M3 12l4-4"/><path d="M3 12l4 4"/></svg>
        </span>
        <h4 class="font-bold text-lg text-pink-700">Return Item</h4>
      </div>
      <button id="closeReturnModal" class="text-pink-400 hover:text-pink-600 text-2xl font-bold focus:outline-none" style="background:none;border:none;">&times;</button>
    </div>
    <form id="returnForm" class="flex-1 px-6 py-5 space-y-4 overflow-y-auto">
      <div>
        <label class="block text-sm font-semibold text-pink-700 mb-1" for="returnOrderId">Order ID</label>
        <input type="text" id="returnOrderId" class="w-full border border-pink-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-300" required autocomplete="off">
      </div>
      <div>
        <label class="block text-sm font-semibold text-pink-700 mb-1" for="returnProductName">Product Name</label>
        <input type="text" id="returnProductName" class="w-full border border-pink-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-300" required autocomplete="off">
      </div>
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <label class="block text-sm font-semibold text-pink-700 mb-1" for="returnQty">Quantity</label>
          <input type="number" id="returnQty" class="w-full border border-pink-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-300" min="1" required>
        </div>
        <div class="flex-1">
          <label class="block text-sm font-semibold text-pink-700 mb-1" for="returnReason">Reason</label>
          <select id="returnReason" class="w-full border border-pink-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-300" required>
            <option value="">Select reason</option>
            <option value="Damaged">Damaged</option>
            <option value="Wrong Item">Wrong Item</option>
            <option value="Customer Request">Customer Request</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-pink-700 mb-1" for="returnNotes">Additional Notes</label>
        <textarea id="returnNotes" class="w-full border border-pink-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-pink-300 resize-none" rows="2"></textarea>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" id="cancelReturnModal" class="px-4 py-2 rounded-lg border border-pink-200 text-pink-600 hover:bg-pink-50 font-semibold transition">Cancel</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-pink-600 text-white font-bold hover:bg-pink-700 transition">Submit Return</button>
      </div>
    </form>
  </div>
</div>

<script>
const productEls = Array.from(document.querySelectorAll('#productGrid .product'));
const productSearch = document.getElementById('productSearch');
const categoryFilter = document.getElementById('categoryFilter');
const productPagination = document.getElementById('productPagination');
let currentProductPage = 1; const productsPerPage = 12;

function applyProductFilters() {
  const q = productSearch.value.trim().toLowerCase();
  const cat = categoryFilter.value.trim().toLowerCase();

  const filtered = productEls.filter(el => {
    const name = (el.dataset.name||'').toLowerCase();
    const id = (el.dataset.id||'').toLowerCase();
    const category = (el.dataset.category||'').toLowerCase();
    const matchCat = !cat || category === cat;
    const matchQ = !q || name.includes(q) || id.includes(q);
    return matchCat && matchQ;
  });

  // pagination
  const pageCount = Math.max(1, Math.ceil(filtered.length / productsPerPage));
  if (currentProductPage > pageCount) currentProductPage = pageCount;
  productEls.forEach(e => e.style.display = 'none');
  const start = (currentProductPage-1)*productsPerPage;
  filtered.slice(start, start+productsPerPage).forEach(e => e.style.display = 'block');

  renderProductPagination(pageCount);
}

function renderProductPagination(pageCount){
  productPagination.innerHTML = '';
  if (pageCount <= 1) return;
  const prev = document.createElement('button'); prev.textContent='Prev'; prev.onclick = ()=>{ if(currentProductPage>1){currentProductPage--; applyProductFilters();}}; productPagination.appendChild(prev);
  for(let i=1;i<=pageCount;i++){ const b=document.createElement('button'); b.textContent=i; if(i===currentProductPage) b.style.background='var(--rose)'; b.onclick=(()=>{ const idx=i; return ()=>{ currentProductPage=idx; applyProductFilters();}; })(); productPagination.appendChild(b);} 
  const next = document.createElement('button'); next.textContent='Next'; next.onclick = ()=>{ currentProductPage++; applyProductFilters(); }; productPagination.appendChild(next);
}

productSearch.addEventListener('input', ()=>{ currentProductPage=1; applyProductFilters(); });
categoryFilter.addEventListener('change', ()=>{ currentProductPage=1; applyProductFilters(); });
// initial render
applyProductFilters();

const modal = document.getElementById('optionModal');
const sizeOptions = document.getElementById('sizeOptions');
const colorOptions = document.getElementById('colorOptions');
const sizeDiv = document.getElementById('sizeDiv');
const colorDiv = document.getElementById('colorDiv');
const modalProductName = document.getElementById('modalProductName');
let currentProduct = null;

const cart = [];
const cartList = document.getElementById('cartList');
const cartTotalEl = document.getElementById('cartTotal');
const cartChangeEl = document.getElementById('cartChange');
const cartDataField = document.getElementById('cartData');
const totalField = document.getElementById('totalField');

productEls.forEach(card=>{
  const btn = card.querySelector('.addToCart');
  btn.addEventListener('click', ()=>{
    if (btn.disabled) return; // do nothing when out of stock
    const sizes = JSON.parse(card.dataset.sizes||'[]');
    const colors = JSON.parse(card.dataset.colors||'[]');
    currentProduct = { id: card.dataset.id, name: card.dataset.name, price: parseFloat(card.dataset.price) };
    modalProductName.textContent = currentProduct.name;
    // sizes
    sizeOptions.innerHTML = '';
    if(sizes && sizes.length){ sizeDiv.style.display='block'; sizes.forEach(s=>{ const b=document.createElement('button'); b.className='size-btn'; b.textContent=s; b.onclick=()=>{ sizeOptions.querySelectorAll('button').forEach(x=>x.classList.remove('selected')); b.classList.add('selected'); }; sizeOptions.appendChild(b); }); } else sizeDiv.style.display='none';
    // colors
    colorOptions.innerHTML = '';
    if(colors && colors.length){ colorDiv.style.display='block'; colors.forEach(c=>{ const b=document.createElement('button'); b.className='color-btn'; b.textContent=c; b.onclick=()=>{ colorOptions.querySelectorAll('button').forEach(x=>x.classList.remove('selected')); b.classList.add('selected'); }; colorOptions.appendChild(b); }); } else colorDiv.style.display='none';

    modal.style.display='flex';
  });
});

document.getElementById('cancelModal').onclick = ()=> modal.style.display='none';
document.getElementById('closeModalBg').onclick = ()=> modal.style.display='none';

document.getElementById('confirmAdd').onclick = ()=>{
  const sizeBtn = sizeOptions.querySelector('.selected');
  const colorBtn = colorOptions.querySelector('.selected');
  const size = sizeBtn ? sizeBtn.textContent : null;
  const color = colorBtn ? colorBtn.textContent : null;
  // if sizeDiv visible but no size chosen
  if(sizeDiv.style.display !== 'none' && !size){ alert('Please select a size'); return; }
  // add to cart
  let found = cart.find(i=>i.product_id===currentProduct.id && (i.size||'')=== (size||'') && (i.color||'')===(color||''));
  if(found) found.quantity++; else cart.push({ product_id: currentProduct.id, name: currentProduct.name, price: currentProduct.price, quantity:1, size, color });
  modal.style.display='none'; renderCart();
};

function renderCart(){
  cartList.innerHTML=''; let total=0;
  cart.forEach((it,idx)=>{
    const row = document.createElement('div'); row.className='cart-item';
    const left = document.createElement('div'); left.innerHTML = `<div style="font-weight:700">${it.name}</div><div style="font-size:12px;color:#6b7280">${it.size||''}${it.size&&it.color?', ':' '}${it.color||''}</div>`;
    const right = document.createElement('div'); right.style.textAlign='right'; right.innerHTML = `<div style="font-weight:700">₱${(it.price).toFixed(2)}</div><div style="margin-top:8px"><span class="qty-control"><button onclick="changeQty(${idx},-1)">-</button> <strong style="margin:0 6px">${it.quantity}</strong> <button onclick="changeQty(${idx},1)">+</button></span></div><div style="margin-top:6px"><button onclick="removeCartItem(${idx})" style="background:#fee2e2;border-radius:6px;padding:6px">Remove</button></div>`;
    row.appendChild(left); row.appendChild(right); cartList.appendChild(row);
    total += it.price * it.quantity;
  });
  cartTotalEl.textContent = 'Total: ₱' + total.toFixed(2);
  totalField.value = total.toFixed(2);
  cartDataField.value = JSON.stringify(cart);
  updateChange();
}

function removeCartItem(i){ cart.splice(i,1); renderCart(); }
function changeQty(i,delta){ cart[i].quantity += delta; if(cart[i].quantity<1) cart[i].quantity=1; renderCart(); }
function prepareCartData(){ document.getElementById('cartData').value = JSON.stringify(cart); }

function updateChange(){ const cash = parseFloat(document.getElementById('cashGiven').value) || 0; const total = parseFloat(totalField.value) || 0; const change = cash - total; cartChangeEl.textContent = cash ? (change>=0? 'Change: ₱'+change.toFixed(2) : 'Insufficient cash') : ''; }

// Payment method toggle
function toggleGcashRef(){ const s = document.getElementById('paymentMethodSelect'); document.getElementById('gcashRefDiv').style.display = (s.value=='1') ? 'block' : 'none'; }
document.addEventListener('DOMContentLoaded', toggleGcashRef);

// Sidebar behavior (simple)
document.getElementById('sidebarToggle').onclick = function() {
  var sidebar = document.getElementById('sidebar');
  sidebar.classList.toggle('collapsed');
  // Optionally, adjust main-content margin if needed
  var mainContent = document.querySelector('.main-content');
  if (sidebar.classList.contains('collapsed')) {
    mainContent.style.marginLeft = '60px';
    mainContent.style.width = 'calc(100vw - 60px)';
  } else {
    mainContent.style.marginLeft = '260px';
    mainContent.style.width = 'calc(100vw - 260px)';
  }
};
document.getElementById('openReturnModal').onclick = function() {
  var modal = document.getElementById('returnModal');
  modal.style.transform = 'translateX(0)';
  modal.style.pointerEvents = 'auto';
  modal.style.boxShadow = '0 8px 32px rgba(211,118,137,0.18)';
};
document.getElementById('closeReturnModal').onclick = function() {
  var modal = document.getElementById('returnModal');
  modal.style.transform = 'translateX(100%)';
  modal.style.pointerEvents = 'none';
};
document.getElementById('cancelReturnModal').onclick = function() {
  var modal = document.getElementById('returnModal');
  modal.style.transform = 'translateX(100%)';
  modal.style.pointerEvents = 'none';
};
document.getElementById('returnForm').onsubmit = function(e) {
  e.preventDefault();
  // Placeholder: handle return logic here
  const orderId = document.getElementById('returnOrderId').value;
  const productName = document.getElementById('returnProductName').value;
  const qty = document.getElementById('returnQty').value;
  const reason = document.getElementById('returnReason').value;
  const notes = document.getElementById('returnNotes').value;
  alert(
    'Return submitted!\n' +
    'Order ID: ' + orderId +
    '\nProduct: ' + productName +
    '\nQty: ' + qty +
    '\nReason: ' + reason +
    (notes ? '\nNotes: ' + notes : '')
  );
  document.getElementById('returnModal').style.display = 'none';
};
</script>
</body>
</html>




