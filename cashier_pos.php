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

// 🧩 Fetch sizes and colors directly from stock
// 🧩 Fetch sizes and colors directly from stock (return names, not IDs)
function getSizes($conn, $pid) {
    $sizes = [];
    $pid = intval($pid);
    $res = $conn->query("
        SELECT DISTINCT s.size 
        FROM stock st
        INNER JOIN sizes s ON st.size_id = s.size_id
        WHERE st.product_id = $pid AND st.size_id IS NOT NULL
    ");
    while ($r = $res->fetch_assoc()) {
        $sizes[] = $r['size'];
    }
    return $sizes;
}

function getColors($conn, $pid) {
    $colors = [];
    $pid = intval($pid);
    $res = $conn->query("
        SELECT DISTINCT c.color 
        FROM stock st
        INNER JOIN colors c ON st.color_id = c.color_id
        WHERE st.product_id = $pid AND st.color_id IS NOT NULL
    ");
    while ($r = $res->fetch_assoc()) {
        $colors[] = $r['color'];
    }
    return $colors;
}


// Fetch products
$products = $conn->query("
    SELECT p.product_id, p.product_name, p.price_id AS price, p.image_url, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id DESC
");

// Fetch payment methods
$payments = $conn->query("SELECT payment_method_id, payment_method_name FROM payment_methods ORDER BY payment_method_id ASC");

// Handle checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'], true);
    $payment_method_id = intval($_POST['payment_method_id']);
    $cash_given = floatval($_POST['cash_given']);
    $total = floatval($_POST['total']);

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
        // Insert order
        $stmt = $conn->prepare("
            INSERT INTO orders (admin_id, total_amount, cash_given, changes, order_status_id, created_at, payment_method_id)
            VALUES (?, ?, ?, ?, 0, NOW(), ?)
        ");
        $stmt->bind_param("idddi", $admin_id, $total, $cash_given, $changes, $payment_method_id);
        $stmt->execute();
        $order_id = $stmt->insert_id;
        $stmt->close();

        // Insert order items + update stock
        $itemStmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, color, size, stock_id, qty, price)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($cart as $item) {
            $product_id = intval($item['product_id']);
            $qty = intval($item['quantity']);
            $price = floatval($item['price']);
            $color = $item['color'] ?? null;
            $size = $item['size'] ?? null;

            // 🟢 Get color_id and size_id by name
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

            // 🟢 Find stock entry by product_id + color_id + size_id
            $stockRes = $conn->prepare("
                SELECT stock_id, current_qty 
                FROM stock
                WHERE product_id = ?
                AND (color_id = ? OR ? IS NULL)
                AND (size_id = ? OR ? IS NULL)
                LIMIT 1
            ");
            $stockRes->bind_param("iiiii", $product_id, $color_id, $color_id, $size_id, $size_id);
            $stockRes->execute();
            $stockData = $stockRes->get_result()->fetch_assoc();
            $stockRes->close();

            if (!$stockData || $stockData['current_qty'] < $qty) {
                throw new Exception("Insufficient stock for product ID: $product_id");
            }

            $stock_id = $stockData['stock_id'];

            // 🟢 Insert order item (save readable names for color/size)
            $itemStmt->bind_param("iissiid", $order_id, $product_id, $color, $size, $stock_id, $qty, $price);
            $itemStmt->execute();

            // 🟢 Update stock
            $newQty = $stockData['current_qty'] - $qty;
            $updateStock = $conn->prepare("UPDATE stock SET current_qty = ? WHERE stock_id = ?");
            $updateStock->bind_param("ii", $newQty, $stock_id);
            $updateStock->execute();
            $updateStock->close();
        }
        $itemStmt->close();

        // Record transaction
        $t = $conn->prepare("
            INSERT INTO transactions (order_id, customer_id, payment_method_id, total, order_status_id, date_time)
            VALUES (?, NULL, ?, ?, 0, NOW())
        ");
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cashier POS | Seven Dwarfs Boutique</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root { --rose:#d37689; --rose-hover:#b75f6f; }
body { background:#fef9fa; font-family:'Poppins',sans-serif; }
.sidebar {
  width: 240px;
  background-color: white;
  border-right: 1px solid #e5e7eb;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 2px 0 6px rgba(0,0,0,0.05);
}
.sidebar a { display:block; padding:0.75rem 1rem; border-radius:8px; font-weight:500; color:#4b5563; margin-bottom:0.25rem; }
.sidebar a:hover { background-color:#fef2f4; color:var(--rose-hover); }
.active-link { background-color:var(--rose); color:white !important; }
.main-content { margin-left:260px; padding:1.5rem; }
</style>
</head>
<body class="text-gray-800">

<!-- Sidebar -->
<aside class="sidebar">
  <div>
    <div class="flex items-center gap-3 mb-6">
      <img src="logo.png" class="w-10 h-10 rounded-full" alt="Logo">
      <h1 class="text-lg font-semibold text-[var(--rose)]">Seven Dwarfs</h1>
    </div>
    <nav>
      <a href="cashier_pos.php" class="active-link">🛍️ POS</a>
      <a href="cashier_transactions.php">💰 Transactions</a>
      <a href="cashier_inventory.php">📦 Inventory</a>
    </nav>
  </div>
  <div class="mt-auto border-t pt-3">
    <p class="text-sm text-gray-600 mb-2">Cashier:
      <span class="font-medium text-[var(--rose)]"><?= htmlspecialchars($cashier_name); ?></span>
    </p>
    <form action="logout.php" method="POST">
      <button class="w-full text-left text-red-500 hover:text-red-600 font-medium">🚪 Logout</button>
    </form>
  </div>
</aside>

<!-- Main Content -->
<div class="main-content">
  <div class="grid grid-cols-3 gap-4">

    <!-- Product List -->
    <div class="col-span-2 bg-white rounded-lg shadow border">
      <div class="p-4 border-b flex justify-between items-center">
        <h2 class="font-semibold text-lg">Products</h2>
        <select id="categoryFilter" class="border rounded px-2 py-1 text-sm">
          <option value="">All Categories</option>
          <?php while ($cat = $categories->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']); ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div id="productGrid" class="grid grid-cols-3 gap-4 p-4 max-h-[70vh] overflow-y-auto">
        <?php while ($p = $products->fetch_assoc()):
          $sizes = getSizes($conn, $p['product_id']);
          $colors = getColors($conn, $p['product_id']);

          $imagePath = $p['image_url'];
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
          } else $img = 'uploads/default.png';
        ?>
        <div class="border rounded-lg p-3 hover:shadow transition cursor-pointer product"
             data-category="<?= htmlspecialchars($p['category_name']) ?>"
             data-id="<?= $p['product_id'] ?>"
             data-name="<?= htmlspecialchars($p['product_name']) ?>"
             data-price="<?= $p['price'] ?>"
             data-sizes='<?= htmlspecialchars(json_encode($sizes), ENT_QUOTES) ?>'
             data-colors='<?= htmlspecialchars(json_encode($colors), ENT_QUOTES) ?>'>
          <img src="<?= htmlspecialchars($img) ?>" class="w-full h-32 object-cover rounded mb-2">
          <p class="font-semibold"><?= htmlspecialchars($p['product_name']) ?></p>
          <p class="text-[var(--rose)] font-medium">₱<?= number_format($p['price'], 2) ?></p>
          <button class="mt-2 w-full bg-[var(--rose)] text-white rounded py-1 text-sm addToCart hover:bg-[var(--rose-hover)]">Add</button>
        </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Cart -->
    <div class="bg-white rounded-lg shadow border p-4 flex flex-col justify-between">
      <div class="mb-5">
        <h2 class="font-semibold text-lg mb-2">Cart</h2>
        <table class="w-full text-sm" id="cartTable">
          <thead><tr class="border-b"><th>Name</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
          <tbody></tbody>
        </table>
        <div class="text-right mt-3 font-semibold text-[var(--rose)]" id="cartTotal">Total: ₱0.00</div>
      </div>

      <form method="POST" class="space-y-3" onsubmit="prepareCartData()">
        <input type="hidden" name="cart_data" id="cartData">
        <input type="hidden" name="total" id="totalField">
        <div>
          <label class="block font-medium">Payment Method</label>
          <select name="payment_method_id" class="border rounded w-full p-2">
            <?php while ($pay = $payments->fetch_assoc()): ?>
              <option value="<?= $pay['payment_method_id']; ?>"><?= htmlspecialchars($pay['payment_method_name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label class="block font-medium">Cash Given (₱)</label>
          <input type="number" name="cash_given" step="0.01" class="border rounded w-full p-2" required>
        </div>
        <button type="submit" name="checkout" class="w-full py-2 bg-[var(--rose)] text-white rounded hover:bg-[var(--rose-hover)]">Checkout</button>
      </form>
    </div>
  </div>
</div>

<!-- Modal -->
<div id="optionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
  <div class="bg-white p-5 rounded-lg w-80 shadow-lg">
    <h3 class="font-semibold mb-3" id="modalProductName">Select Options</h3>
    <div class="mb-3" id="sizeDiv">
      <label class="block font-medium">Size</label>
      <select id="sizeSelect" class="border rounded w-full p-2"></select>
    </div>
    <div class="mb-3" id="colorDiv">
      <label class="block font-medium">Color</label>
      <select id="colorSelect" class="border rounded w-full p-2"></select>
    </div>
    <div class="flex justify-end gap-2">
      <button id="cancelModal" class="px-3 py-1 rounded bg-gray-200">Cancel</button>
      <button id="confirmAdd" class="px-3 py-1 rounded bg-[var(--rose)] text-white">Add</button>
    </div>
  </div>
</div>

<script>
const cart = [];
const tbody = document.querySelector("#cartTable tbody");
const totalDisplay = document.querySelector("#cartTotal");
const cartDataField = document.querySelector("#cartData");
const totalField = document.querySelector("#totalField");

const modal = document.getElementById("optionModal");
const sizeSelect = document.getElementById("sizeSelect");
const colorSelect = document.getElementById("colorSelect");
const sizeDiv = document.getElementById("sizeDiv");
const colorDiv = document.getElementById("colorDiv");
const modalProductName = document.getElementById("modalProductName");
let currentProduct = null;

// Open modal
document.querySelectorAll(".addToCart").forEach(btn => {
  btn.addEventListener("click", () => {
    const p = btn.closest(".product");
    const sizes = JSON.parse(p.dataset.sizes || "[]");
    const colors = JSON.parse(p.dataset.colors || "[]");

    currentProduct = {
      id: parseInt(p.dataset.id),
      name: p.dataset.name,
      price: parseFloat(p.dataset.price),
      sizes, colors
    };

    // Populate dropdowns
    sizeDiv.style.display = sizes.length ? "block" : "none";
    colorDiv.style.display = colors.length ? "block" : "none";

    sizeSelect.innerHTML = sizes.map(s => `<option value="${s}">${s}</option>`).join('');
    colorSelect.innerHTML = colors.map(c => `<option value="${c}">${c}</option>`).join('');

    modalProductName.textContent = currentProduct.name;
    modal.classList.remove("hidden");
  });
});

document.getElementById("cancelModal").onclick = () => modal.classList.add("hidden");

document.getElementById("confirmAdd").onclick = () => {
  const size = sizeSelect.value || null;
  const color = colorSelect.value || null;

  let item = cart.find(i => i.product_id === currentProduct.id && (i.size||'') === (size||'') && (i.color||'') === (color||''));
  if (item) item.quantity++;
  else cart.push({product_id: currentProduct.id, name: currentProduct.name, price: currentProduct.price, quantity: 1, size, color});

  modal.classList.add("hidden");
  renderCart();
};

function renderCart() {
  tbody.innerHTML = "";
  let total = 0;
  cart.forEach(i => {
    const row = document.createElement("tr");
    const itemTotal = i.quantity * i.price;
    total += itemTotal;
    row.innerHTML = `
      <td>${i.name}<br><small class="text-xs">${i.size || ''}${i.size && i.color ? ', ' : ''}${i.color || ''}</small></td>
      <td>${i.quantity}</td>
      <td>₱${i.price.toFixed(2)}</td>
      <td>₱${itemTotal.toFixed(2)}</td>`;
    tbody.appendChild(row);
  });
  totalDisplay.textContent = "Total: ₱" + total.toFixed(2);
  totalField.value = total.toFixed(2);
  cartDataField.value = JSON.stringify(cart);
}

function prepareCartData() {
  cartDataField.value = JSON.stringify(cart);
}

document.getElementById("categoryFilter").addEventListener("change", e => {
  const val = e.target.value.toLowerCase();
  document.querySelectorAll(".product").forEach(p => {
    p.style.display = val === "" || (p.dataset.category || '').toLowerCase() === val ? "" : "none";
  });
});
</script>
</body>
</html>
