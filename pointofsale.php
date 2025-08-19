<?php
require 'conn.php';
session_start();

// Fetch products with stock & price
$query = "
    SELECT p.product_id, p.product_name, p.price_id AS price, 
           COALESCE(s.current_qty,0) AS stock, 
           p.image_url, 
           c.category_name
    FROM products p
    LEFT JOIN stock s ON p.product_id = s.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
";
$products = $conn->query($query);

// Fetch categories
$categories = $conn->query("SELECT category_id, category_name FROM categories");

// Fetch payment methods
$payments = $conn->query("SELECT * FROM payment_methods");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>POS - Seven Dwarfs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @keyframes fadeInUp {
      0% { opacity: 0; transform: translateY(20px); }
      100% { opacity: 1; transform: translateY(0); }
    }
    .animate-fadeInUp { animation: fadeInUp 0.5s ease-in-out; }

    @keyframes slideInRight {
      0% { opacity: 0; transform: translateX(20px); }
      100% { opacity: 1; transform: translateX(0); }
    }
    .animate-slideInRight { animation: slideInRight 0.3s ease-out; }
  </style>
</head>
<body class="bg-gray-100 h-screen flex flex-col">

  <!-- Header -->
  <div class="bg-white shadow p-4 flex justify-between items-center">
    <h1 class="text-xl font-bold">Seven Dwarfs POS</h1>
    <input type="text" id="search" placeholder="Search products..."
      class="border rounded px-3 py-2 w-1/3 focus:ring-2 focus:ring-pink-400 transition">
  </div>

  <div class="flex flex-1 overflow-hidden">
    
    <!-- Sidebar Categories -->
    <div class="w-48 bg-white border-r p-4 overflow-y-auto">
      <h2 class="font-bold mb-3">Categories</h2>
      <ul class="space-y-2">
        <li><button onclick="filterCategory('all')" class="w-full text-left hover:text-pink-500 transition">All</button></li>
        <?php while($cat = $categories->fetch_assoc()): ?>
          <li>
            <button onclick="filterCategory('<?= $cat['category_name'] ?>')" 
              class="w-full text-left hover:text-pink-500 transition"><?= htmlspecialchars($cat['category_name']) ?></button>
          </li>
        <?php endwhile; ?>
      </ul>
    </div>

    <!-- Products -->
    <div class="flex-1 bg-white p-6 overflow-y-auto">
      <div id="productGrid" class="grid grid-cols-4 gap-6">
        <?php while($row = $products->fetch_assoc()): ?>
          <div class="border rounded shadow hover:shadow-lg transform hover:scale-105 transition p-3 text-center product-card animate-fadeInUp"
            data-name="<?= strtolower($row['product_name']) ?>"
            data-category="<?= strtolower($row['category_name'] ?? 'uncategorized') ?>">
            
            <img src="<?= $row['image_url'] ?? 'placeholder.png' ?>" 
                 alt="<?= htmlspecialchars($row['product_name']) ?>" 
                 class="w-full h-32 object-cover rounded mb-2">
            
            <h3 class="font-semibold"><?= htmlspecialchars($row['product_name']) ?></h3>
            <p class="text-pink-600 font-bold">₱<?= number_format($row['price'],2) ?></p>
            <p class="text-xs text-gray-500">Stock: <?= $row['stock'] ?></p>
            <button 
              onclick="addToCart(<?= $row['product_id'] ?>, '<?= $row['product_name'] ?>', <?= $row['price'] ?>)" 
              class="bg-pink-500 hover:bg-pink-600 text-white px-3 py-1 rounded mt-2 w-full transform transition hover:scale-105">
              Add
            </button>
          </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Cart -->
    <div class="w-96 bg-white border-l p-6 flex flex-col">
      <h2 class="text-lg font-bold mb-3">Cart</h2>
      <form id="cartForm" method="POST" action="process_sale.php" class="flex flex-col flex-1">
        <div id="cartTable" class="flex-1 overflow-y-auto mb-3"></div>
        <input type="hidden" name="cart_data" id="cartData">

        <!-- Summary -->
        <div class="border-t pt-3 space-y-2">
          <div class="flex justify-between font-bold text-lg">
            <span>Total Payable:</span><span id="total">₱0.00</span>
          </div>
        </div>

        <div class="mt-3">
          <label class="block font-semibold">Payment Method</label>
          <select name="payment_method" class="border rounded w-full p-2 focus:ring-2 focus:ring-pink-400 transition">
            <?php while($pm = $payments->fetch_assoc()): ?>
              <option value="<?= $pm['payment_method_id'] ?>"><?= $pm['payment_method_name'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="mt-3">
          <label class="block font-semibold">Cash Given</label>
          <input type="number" step="0.01" name="cash_given" class="border rounded w-full p-2 focus:ring-2 focus:ring-pink-400 transition" required>
        </div>

        <div class="mt-4 flex space-x-2">
          <button type="button" class="bg-orange-500 text-white px-4 py-2 rounded w-1/2 transform transition hover:scale-105">Hold Order</button>
          <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded w-1/2 transform transition hover:scale-105">Proceed</button>
        </div>
      </form>
    </div>
  </div>

<script>
let cart = [];

function addToCart(id, name, price) {
  let item = cart.find(p => p.id === id);
  if (item) {
    item.qty++;
  } else {
    cart.push({id, name, price, qty: 1});
  }
  renderCart();
}

function renderCart() {
  let cartDiv = document.getElementById('cartTable');
  cartDiv.innerHTML = '';
  let total = 0;
  cart.forEach((item, i) => {
    total += item.price * item.qty;
    cartDiv.innerHTML += `
      <div class="flex justify-between items-center border-b py-2 animate-slideInRight">
        <div>
          <p class="font-semibold">${item.name}</p>
          <p class="text-xs">x${item.qty}</p>
        </div>
        <div>
          ₱${(item.price*item.qty).toFixed(2)}
          <button type="button" onclick="removeItem(${i})" class="text-red-500 ml-2">X</button>
        </div>
      </div>`;
  });
  document.getElementById('total').innerText = `₱${total.toFixed(2)}`;
  document.getElementById('cartData').value = JSON.stringify(cart);
}

function removeItem(index) {
  cart.splice(index,1);
  renderCart();
}

// Search filter
document.getElementById('search').addEventListener('input', function(){
  let query = this.value.toLowerCase();
  document.querySelectorAll('.product-card').forEach(card => {
    card.style.display = card.dataset.name.includes(query) ? '' : 'none';
  });
});

// Category filter
function filterCategory(cat) {
  document.querySelectorAll('.product-card').forEach(card => {
    if(cat === 'all' || card.dataset.category === cat.toLowerCase()) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}
</script>
</body>
</html>
