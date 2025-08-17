<?php
require 'conn.php';
session_start();

// Fetch products with stock & price
$query = "
    SELECT p.product_id, p.product_name, p.price_id AS price, 
           COALESCE(s.current_qty,0) AS stock 
    FROM products p
    LEFT JOIN stock s ON p.product_id = s.product_id
";
$products = $conn->query($query);

// Fetch payment methods
$payments = $conn->query("SELECT * FROM payment_methods");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>POS - Seven Dwarfs</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-6xl mx-auto grid grid-cols-3 gap-6">
    
    <!-- Products -->
    <div class="col-span-2 bg-white p-4 rounded shadow">
      <h2 class="text-lg font-bold mb-3">Products</h2>
      <div class="grid grid-cols-3 gap-4">
        <?php while($row = $products->fetch_assoc()): ?>
          <div class="border p-3 rounded text-center">
            <h3 class="font-semibold"><?= htmlspecialchars($row['product_name']) ?></h3>
            <p>₱<?= number_format($row['price'],2) ?></p>
            <p class="text-sm text-gray-500">Stock: <?= $row['stock'] ?></p>
            <button 
              onclick="addToCart(<?= $row['product_id'] ?>, '<?= $row['product_name'] ?>', <?= $row['price'] ?>)" 
              class="bg-pink-500 text-white px-3 py-1 rounded mt-2">
              Add
            </button>
          </div>
        <?php endwhile; ?>
      </div>
    </div>

    <!-- Cart -->
    <div class="bg-white p-4 rounded shadow">
      <h2 class="text-lg font-bold mb-3">Cart</h2>
      <form id="cartForm" method="POST" action="process_sale.php">
        <table class="w-full text-sm mb-3" id="cartTable"></table>
        <input type="hidden" name="cart_data" id="cartData">
        
        <div class="mt-3">
          <label class="block font-semibold">Payment Method</label>
          <select name="payment_method" class="border rounded w-full p-2">
            <?php while($pm = $payments->fetch_assoc()): ?>
              <option value="<?= $pm['payment_method_id'] ?>"><?= $pm['payment_method_name'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="mt-3">
          <label class="block font-semibold">Cash Given</label>
          <input type="number" step="0.01" name="cash_given" class="border rounded w-full p-2" required>
        </div>

        <button type="submit" class="bg-green-500 text-white w-full py-2 mt-4 rounded">Proceed</button>
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
  let table = document.getElementById('cartTable');
  table.innerHTML = '';
  let total = 0;
  cart.forEach((item, i) => {
    total += item.price * item.qty;
    table.innerHTML += `
      <tr>
        <td>${item.name}</td>
        <td>x${item.qty}</td>
        <td>₱${(item.price*item.qty).toFixed(2)}</td>
        <td><button type="button" onclick="removeItem(${i})" class="text-red-500">X</button></td>
      </tr>`;
  });
  table.innerHTML += `<tr><td colspan="2" class="font-bold">Total</td><td colspan="2">₱${total.toFixed(2)}</td></tr>`;
  document.getElementById('cartData').value = JSON.stringify(cart);
}

function removeItem(index) {
  cart.splice(index,1);
  renderCart();
}
</script>
</body>
</html>
