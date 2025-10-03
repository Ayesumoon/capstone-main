<?php
require 'conn.php';
session_start();

// 🔹 Get next refund transaction ID (auto-increment preview)
$next_refund_id = null;
$res = $conn->query("SHOW TABLE STATUS LIKE 'refunds'"); // adjust table name if it's not `refunds`
if ($res && $row = $res->fetch_assoc()) {
    $next_refund_id = $row['Auto_increment']; // next ID that will be assigned
}


// ✅ Get logged-in admin info
$admin_id = $_SESSION["admin_id"] ?? null;
$username = $_SESSION["username"] ?? null;
$email = $_SESSION["email"] ?? null;
$role_id = $_SESSION["role_id"] ?? null;
$role_name = null;

if ($admin_id) {
  // Fetch role name for display
  $stmt = $conn->prepare("SELECT r.role_name 
                          FROM adminusers a
                          INNER JOIN roles r ON a.role_id = r.role_id
                          WHERE a.admin_id = ?");
  $stmt->bind_param("i", $admin_id);
  $stmt->execute();
  $stmt->bind_result($role_name);
  $stmt->fetch();
  $stmt->close();
}


// Fetch products with stock, color, size & price
$query = "
    SELECT p.product_id, p.product_name, p.price_id AS price,
           s.stock_id,
           COALESCE(s.current_qty,0) AS stock,
           col.color, 
           sz.size,
           p.image_url,
           c.category_name
    FROM products p
    INNER JOIN stock s ON p.product_id = s.product_id
    LEFT JOIN colors col ON s.color_id = col.color_id
    LEFT JOIN sizes sz ON s.size_id = sz.size_id
    LEFT JOIN categories c ON p.category_id = c.category_id
";
$products = $conn->query($query);

// Fetch categories
$categories = $conn->query("SELECT category_id, category_name FROM categories");

// Fetch payment methods
$payments = $conn->query("SELECT * FROM payment_methods");

// Group products
$productsArr = [];
while ($row = $products->fetch_assoc()) {
  $pid = $row['product_id'];
  if (!isset($productsArr[$pid])) {
    $productsArr[$pid] = [
      'id' => $pid,
      'name' => $row['product_name'],
      'price' => $row['price'],
      'image' => $row['image_url'],
      'category' => $row['category_name'],
      'stocks' => []
    ];
  }
  $productsArr[$pid]['stocks'][] = [
    'stock_id' => $row['stock_id'],
    'color' => $row['color'] ?? '-',
    'size' => $row['size'] ?? '-',
    'qty' => $row['stock']
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seven Dwarfs Boutique - POS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Poppins', sans-serif; }
    .sidebar { scrollbar-width: thin; scrollbar-color: #f9a8d4 #fff1f2; }
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-track { background: #fff1f2; }
    .sidebar::-webkit-scrollbar-thumb { background-color: #f9a8d4; border-radius: 3px; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(233, 213, 255, 0.3); }
    .active-category { background-color: #f472b6; color: white !important; }
  </style>
</head>
<body class="bg-pink-50">
<div class="flex h-screen overflow-hidden">

<!-- Refund Modal -->
<div id="refundModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-50">
  <div class="bg-white w-full max-w-md rounded-lg shadow-lg p-6 relative">
    <button onclick="closeRefundModal()" 
            class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl font-bold">&times;</button>
    
    <h2 class="text-xl font-bold text-pink-600 mb-4">Refund Transaction</h2>

    <form id="refundForm" method="POST" action="process_refund.php" onsubmit="return confirm('Confirm refund?')">
      <div class="mb-3">
        <label class="block font-semibold text-gray-700 mb-1">Order ID</label>
        <input type="number" name="order_id" id="refundOrderId" 
               class="border rounded-lg w-full p-2 bg-white" 
               placeholder="Enter Order ID" required>
        <button type="button" onclick="loadOrderItems()" 
                class="mt-2 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
          Load Items
        </button>
      </div>

      <!-- Items Dropdown -->
      <div id="refundItemsContainer" class="hidden mb-4">
        <label class="block font-semibold text-gray-700 mb-1">Select Item to Refund</label>
        <select name="stock_id" id="refundItemSelect" class="border rounded-lg w-full p-2 bg-white" required></select>

        <label class="block font-semibold text-gray-700 mb-1 mt-3">Quantity to Refund</label>
        <input type="number" name="refund_qty" id="refundQty" 
               class="border rounded-lg w-full p-2 bg-white" min="1" value="1" required>
      </div>

      <div class="flex space-x-3 mt-6">
        <button type="submit" 
                class="bg-pink-500 hover:bg-pink-600 text-white px-4 py-2 rounded-lg w-1/2 shadow-md">
          Submit Refund
        </button>
        <button type="button" onclick="closeRefundModal()" 
                class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg w-1/2 shadow-md">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Open refund modal
function openRefundModal() {
  document.getElementById('refundModal').classList.remove('hidden');
}

// Close modal
function closeRefundModal() {
  document.getElementById('refundModal').classList.add('hidden');
}

function loadOrderItems() {
  const orderId = document.getElementById("refundOrderId").value;
  if (!orderId) {
    alert("Enter Order ID first.");
    return;
  }

  fetch("get_order_items.php?order_id=" + orderId)
    .then(res => res.json())
    .then(items => {
      if (items.length === 0) {
        alert("No items found for this order.");
        return;
      }

      const select = document.getElementById("refundItemSelect");
      select.innerHTML = "";

      items.forEach(it => {
        let opt = document.createElement("option");
        opt.value = it.stock_id;
        opt.textContent = `${it.product_name} - ${it.color ?? '-'} / ${it.size ?? '-'} (Qty: ${it.qty})`;
        opt.dataset.maxQty = it.qty;
        select.appendChild(opt);
      });

      document.getElementById("refundItemsContainer").classList.remove("hidden");

      // enforce max refund qty
      select.addEventListener("change", () => {
        const max = select.options[select.selectedIndex].dataset.maxQty;
        document.getElementById("refundQty").max = max;
      });

      // initialize for first option
      select.dispatchEvent(new Event("change"));
    })
    .catch(err => {
      console.error(err);
      alert("Error loading order items.");
    });
}
</script>


  <!-- Sidebar Categories -->
  <div class="w-64 bg-pink-100 border-r border-pink-200 flex flex-col">
    <div class="p-4 flex items-center justify-center bg-gradient-to-r from-pink-500 to-rose-500">
      <h1 class="text-2xl font-bold text-white">Seven Dwarfs Boutique POS</h1>
    </div>
    <div class="sidebar flex-1 overflow-y-auto py-4 px-2">
      <h3 class="px-4 py-2 text-pink-900 font-semibold">Categories</h3>
      <ul class="space-y-1" id="categoryList">
        <li><a href="#" onclick="filterCategory('all', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition active-category">All</a></li>
        <?php while($cat = $categories->fetch_assoc()): ?>
          <li>
            <a href="#" onclick="filterCategory('<?= strtolower($cat['category_name']) ?>', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition">
              <span class="mr-2 w-3 h-3 rounded-full bg-pink-400"></span> <?= htmlspecialchars($cat['category_name']) ?>
            </a>
          </li>
        <?php endwhile; ?>
        <a href="logout.php" class="ml-3 text-sm text-pink-600 hover:underline">Logout</a>
      </ul>
    </div>
  </div>

  <!-- Main Products -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <div class="bg-white border-b border-pink-200 p-4 flex items-center justify-between">
      <input id="search" type="text" placeholder="Search products..." 
             onkeyup="filterSearch()" 
             class="border-0 focus:ring-2 focus:ring-pink-300 rounded-full bg-pink-50 px-4 py-2 w-64">
<div class="flex items-center space-x-2">
  <div class="text-right hidden sm:block">
    <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($username ?? "Guest") ?></p>
    <p class="text-xs text-gray-500"><?= htmlspecialchars($role_name ?? "Unknown") ?></p>
  </div>
  <div class="w-10 h-10 rounded-full bg-pink-500 flex items-center justify-center text-white font-bold">
    <?= strtoupper(substr($username ?? "U", 0, 2)) ?>
  </div>
</div>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
      <h2 class="text-2xl font-bold text-pink-900 mb-6">Available Products</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6" id="productGrid">
        <?php foreach($productsArr as $product): ?>
          <div class="border rounded shadow hover:shadow-lg transform hover:scale-105 transition p-3 text-center product-card"
            data-name="<?= strtolower($product['name']) ?>"
            data-category="<?= strtolower($product['category'] ?? 'uncategorized') ?>">

            <img src="<?= $product['image'] ?? 'placeholder.png' ?>" 
                 alt="<?= htmlspecialchars($product['name']) ?>" 
                 class="w-full h-32 object-cover rounded mb-2">

            <h3 class="font-semibold"><?= htmlspecialchars($product['name']) ?></h3>
            <p class="text-pink-600 font-bold">₱<?= number_format($product['price'],2) ?></p>
<!-- Size Dropdown -->
<div class="mt-2">
  <label class="block text-xs text-gray-500">Size</label>
  <select class="w-full border rounded p-1 text-sm size-select">
    <option value="">Select size</option>
    <?php 
      $sizes = array_unique(array_column($product['stocks'], 'size'));
      foreach($sizes as $size): ?>
        <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- Color Dropdown -->
<div class="mt-2">
  <label class="block text-xs text-gray-500">Color</label>
  <select class="w-full border rounded p-1 text-sm color-select">
    <option value="">Select color</option>
    <?php foreach($product['stocks'] as $variant): ?>
      <option value="<?= htmlspecialchars($variant['color']) ?>"
              data-size="<?= htmlspecialchars($variant['size']) ?>"
              data-stock-id="<?= $variant['stock_id'] ?>">
        <?= htmlspecialchars($variant['color']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>

            <button 
              onclick="addVariantToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>', <?= $product['price'] ?>, this)" 
              class="bg-pink-500 hover:bg-pink-600 text-white px-3 py-1 rounded mt-3 w-full transform transition hover:scale-105">
              Add
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Shopping Cart -->
<div class="w-96 bg-pink-100 shadow-lg rounded-lg overflow-hidden">
  <div class="bg-pink-500 text-white font-bold text-lg p-3">Shopping Cart</div>
  <div class="p-4 flex flex-col flex-1 text-center">
    <div id="emptyMessage" class="py-6">
      <p class="text-gray-600 font-semibold">Your cart is empty</p>
      <p class="text-gray-400 text-sm">Add some dwarfly items!</p>
    </div>
    <div id="cartTable" class="flex-1 overflow-y-auto mb-4 space-y-2 hidden"></div>

    <!-- Checkout Form -->
    <form method="POST" action="process_sale.php" target="_blank" onsubmit="return confirm('Proceed to checkout?')">
      <input type="hidden" name="cart_data" id="cartData">
      <div class="bg-white rounded-lg shadow p-3 mt-2">
        <div class="flex justify-between text-gray-700 text-sm">
          <span class="font-bold">Subtotal:</span>
          <span id="subtotal">₱0.00</span>
        </div>
        <div class="flex justify-between text-lg font-bold text-black border-t pt-2">
          <span>Total:</span>
          <span id="total">₱0.00</span>
        </div>
      </div>
      <div class="mt-4 text-left">
        <label class="block font-semibold text-gray-700 mb-1">Payment Method</label>
        <select name="payment_method" class="border rounded-lg w-full p-2 bg-white">
          <?php while($pm = $payments->fetch_assoc()): ?>
            <option value="<?= $pm['payment_method_id'] ?>"><?= $pm['payment_method_name'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="mt-4 text-left">
        <label class="block font-semibold text-gray-700 mb-1">Cash Given</label>
        <input type="number" step="0.01" name="cash_given" placeholder="Enter cash amount" class="border rounded-lg w-full p-2 bg-white">
      </div>
      <div class="mt-6 flex space-x-3">
        <button type="submit" class="bg-pink-400 hover:bg-pink-500 text-white px-4 py-2 rounded-lg w-1/2 shadow-md">Checkout</button>
        <button type="button" onclick="clearCart()" class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded-lg w-1/2 shadow-md">Clear</button>
      </div>
    </form>

    <!-- Refund Button OUTSIDE the form -->
    <div class="mt-4">
      <button type="button" onclick="openRefundModal()" 
              class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg w-full shadow-md">
        Refund
      </button>
    </div>
  </div>
</div>

</div>

<script>
let cart = [];

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".product-card").forEach(card => {
    const sizeSelect = card.querySelector(".size-select");
    const colorSelect = card.querySelector(".color-select");

    // When selecting size, filter colors
    sizeSelect.addEventListener("change", () => {
      const chosenSize = sizeSelect.value;
      Array.from(colorSelect.options).forEach(opt => {
        if (!opt.value) return; // keep "Select color"
        opt.hidden = (opt.dataset.size !== chosenSize);
      });
      colorSelect.value = ""; // reset color
    });

    // When selecting color, auto-select the matching size if needed
    colorSelect.addEventListener("change", () => {
      const chosenColor = colorSelect.value;
      const chosenSize = colorSelect.options[colorSelect.selectedIndex]?.dataset.size;
      if (chosenSize) {
        sizeSelect.value = chosenSize;
      }
    });
  });
});

function addVariantToCart(pid, name, price, btn) {
  const card = btn.closest(".product-card");
  const size = card.querySelector(".size-select").value;
  const colorSelect = card.querySelector(".color-select");
  const selectedColor = colorSelect.options[colorSelect.selectedIndex];

  if (!size || !colorSelect.value) {
    alert("Please select both size and color.");
    return;
  }

  const stock_id = parseInt(selectedColor.dataset.stockId);
  if (!stock_id) {
    alert("Invalid size/color combination.");
    return;
  }

  addToCart(stock_id, name, price, colorSelect.value, size);
}


// Add item to cart
function addToCart(stock_id, name, price, color, size) {
  let item = cart.find(p => p.stock_id === stock_id);
  if (item) {
    item.qty++;
  } else {
    cart.push({stock_id, name, price, color, size, qty: 1});
  }
  renderCart();
}

// Render cart
function renderCart() {
  let cartDiv = document.getElementById('cartTable');
  let emptyMsg = document.getElementById('emptyMessage');
  cartDiv.innerHTML = '';
  let subtotal = 0;

  if (cart.length === 0) {
    cartDiv.classList.add("hidden");
    emptyMsg.classList.remove("hidden");
  } else {
    cartDiv.classList.remove("hidden");
    emptyMsg.classList.add("hidden");
    cart.forEach((item,i) => {
      subtotal += item.price * item.qty;
      cartDiv.innerHTML += `
        <div class="flex justify-between items-center border-b py-2 px-2 rounded-md bg-gray-50">
          <div class="text-left">
            <p class="font-semibold text-gray-800">${item.name}</p>
            <p class="text-xs text-gray-500">Color: ${item.color} | Size: ${item.size}</p>
            <p class="text-xs text-gray-500">x${item.qty}</p>
          </div>
          <div class="flex items-center space-x-2">
            <span class="text-gray-700 font-medium">₱${(item.price*item.qty).toFixed(2)}</span>
            <button type="button" onclick="removeItem(${i})" class="text-red-500 hover:text-red-700 font-bold">✕</button>
          </div>
        </div>`;
    });
  }
  document.getElementById('subtotal').innerText = `₱${subtotal.toFixed(2)}`;
  document.getElementById('total').innerText = `₱${subtotal.toFixed(2)}`;
  document.getElementById('cartData').value = JSON.stringify(cart);
}

// Remove item
function removeItem(i) { cart.splice(i,1); renderCart(); }
function clearCart() { cart=[]; renderCart(); }

// Filter by category
function filterCategory(category, e) {
  const products = document.querySelectorAll("#productGrid .product-card");
  category = category.toLowerCase();
  products.forEach(p => {
    const prodCategory = p.dataset.category.toLowerCase();
    p.style.display = (category==="all"||prodCategory===category) ? "block" : "none";
  });
  document.querySelectorAll("#categoryList a").forEach(l => l.classList.remove("active-category"));
  if (e) e.currentTarget.classList.add("active-category");
}

// Search filter
function filterSearch() {
  const val = document.getElementById("search").value.toLowerCase();
  document.querySelectorAll("#productGrid .product-card").forEach(p => {
    p.style.display = p.dataset.name.includes(val) ? "block" : "none";
  });
}

</script>
</body>
</html>
