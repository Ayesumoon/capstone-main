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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seven Dwarf Boutique - POS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Poppins', sans-serif; }
    .sidebar { scrollbar-width: thin; scrollbar-color: #f9a8d4 #fff1f2; }
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-track { background: #fff1f2; }
    .sidebar::-webkit-scrollbar-thumb { background-color: #f9a8d4; border-radius: 3px; }
    .product-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(233, 213, 255, 0.3); }
    .dwarf-red { background-color: #ff6b6b; }
    .dwarf-orange { background-color: #ffbe76; }
    .dwarf-yellow { background-color: #f6e58d; }
    .dwarf-green { background-color: #7bed9f; }
    .dwarf-blue { background-color: #70a1ff; }
    .dwarf-indigo { background-color: #5f27cd; }
    .dwarf-violet { background-color: #a55eea; }
    .active-category { background-color: #f472b6; color: white !important; }
  </style>
</head>
<body class="bg-pink-50">
<div class="flex h-screen overflow-hidden">

  <!-- Sidebar Categories -->
  <div class="w-64 bg-pink-100 border-r border-pink-200 flex flex-col">
    <div class="p-4 flex items-center justify-center bg-gradient-to-r from-pink-500 to-rose-500">
      <h1 class="text-2xl font-bold text-white">Seven Dwarfs Boutique POS</h1>
    </div>
    <div class="sidebar flex-1 overflow-y-auto py-4 px-2">
      <h3 class="px-4 py-2 text-pink-900 font-semibold">Categories</h3>
      <ul class="space-y-1" id="categoryList">
        <li><a href="#" onclick="filterCategory('all', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition active-category"><span class="mr-2 w-3 h-3 rounded-full bg-pink-500"></span> All</a></li>
        <li><a href="#" onclick="filterCategory('Blouse', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-red"></span> Blouse</a></li>
        <li><a href="#" onclick="filterCategory('Dress', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-orange"></span> Dress</a></li>
        <li><a href="#" onclick="filterCategory('Shorts', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-yellow"></span> Shorts</a></li>
        <li><a href="#" onclick="filterCategory('Skirt', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-green"></span> Skirt</a></li>
        <li><a href="#" onclick="filterCategory('Trouser', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-blue"></span> Trouser</a></li>
        <li><a href="#" onclick="filterCategory('Pants', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-indigo"></span> Pants</a></li>
        <li><a href="#" onclick="filterCategory('Coordinates', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-violet"></span> Coordinates</a></li>
        <li><a href="#" onclick="filterCategory('Shoes', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full bg-pink-500"></span> Shoes</a></li>
        <li><a href="#" onclick="filterCategory('Perfume', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-red"></span> Perfume</a></li>
        <li><a href="#" onclick="filterCategory('Bags', event)" class="flex items-center px-4 py-2 text-pink-900 hover:bg-pink-200 rounded-lg transition"><span class="mr-2 w-3 h-3 rounded-full dwarf-yellow"></span> Bags</a></li>
      </ul>
    </div>
  </div>

  <!-- Main Products -->
  <div class="flex-1 flex flex-col overflow-hidden">
    <div class="bg-white border-b border-pink-200 p-4 flex items-center justify-between">
      <input id="search" type="text" placeholder="Search products..." 
             onkeyup="filterSearch()" 
             class="border-0 focus:ring-2 focus:ring-pink-300 rounded-full bg-pink-50 px-4 py-2 w-64">
      <div class="w-8 h-8 rounded-full bg-pink-500 flex items-center justify-center text-white font-semibold">JD</div>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
      <h2 class="text-2xl font-bold text-pink-900 mb-6">Featured Products</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6" id="productGrid">
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
    </div>
  </div>
</div>

<script>
let cart = [];

// Add item
function addToCart(id, name, price) {
  let item = cart.find(p => p.id === id);
  if (item) {
    item.qty++;
  } else {
    cart.push({id, name, price, qty: 1});
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
          <div>
            <p class="font-semibold text-gray-800">${item.name}</p>
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
  if (e) e.target.classList.add("active-category");
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
