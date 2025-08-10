<?php
session_start();

$isLoggedIn = isset($_SESSION['customer_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cart - Seven Dwarfs Boutique</title>
  <script src="https://unpkg.com/alpinejs" defer></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">

<header class="bg-white shadow">
  <div class="container mx-auto px-4 py-3 flex justify-between items-center">
    <div class="text-2xl font-bold text-pink-600">
      <a href="homepage.php">Seven Dwarfs</a>
    </div>
    <ul class="flex flex-wrap justify-center space-x-6 text-sm md:text-base">
      <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
      <li><a href="shop.php" class="hover:text-pink-500 font-semibold">Shop</a></li>
    </ul>
    <div class="flex items-center gap-4 text-pink-600">
      <a href="cart.php" class="hover:text-pink-500 relative" title="Cart">
        <span class="absolute top-0 right-0 text-white bg-pink-600 rounded-full text-xs px-2 py-1">
          <?php echo isset($_SESSION['carts']) ? count($_SESSION['carts']) : 0; ?>
        </span>
        </a>
      <div class="relative">
        <?php if ($isLoggedIn): ?>
          <a href="profile.php" class="text-pink-600 hover:text-pink-500">Profile</a>
        <?php else: ?>
          <a href="login.php" class="text-pink-600 hover:text-pink-500">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>

<main class="container mx-auto px-4 py-10">
  <h1 class="text-2xl font-bold mb-6 text-pink-600">My Cart</h1>

  <?php if (!empty($_SESSION['carts'])): ?>
    <div class="bg-white shadow rounded-lg p-6">
      <table class="w-full text-left">
        <thead>
          <tr class="text-gray-700 border-b">
            <th class="pb-3">Product</th>
            <th class="pb-3">Price</th>
            <th class="pb-3">Quantity</th>
            <th class="pb-3">Total</th>
            <th class="pb-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $grandTotal = 0;
          foreach ($_SESSION['carts'] as $productId => $item): 
            $total = $item['price_id'] * $item['quantity'];
            $grandTotal += $total;
          ?>
            <tr class="border-b hover:bg-pink-50">
              <td class="py-3"><?php echo htmlspecialchars($item['product_name']); ?></td>
              <td class="py-3">₱<?php echo number_format($item['price_id'], 2); ?></td>
              <td class="py-3"><?php echo $item['quantity']; ?></td>
              <td class="py-3">₱<?php echo number_format($total, 2); ?></td>
              <td class="py-3">
                <form action="remove_from_cart.php" method="POST">
                  <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                  <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600">
                    Remove from Cart
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="text-right mt-6">
        <p class="text-lg font-semibold">Grand Total: <span class="text-pink-600">₱<?php echo number_format($grandTotal, 2); ?></span></p>
        <a href="checkout.php" class="inline-block mt-4 bg-pink-500 text-white px-6 py-2 rounded-lg hover:bg-pink-600 transition">Proceed to Checkout</a>
      </div>
    </div>
  <?php else: ?>
    <div class="text-center text-gray-500">
      <p>Your cart is currently empty.</p>
      <a href="shop.php" class="mt-4 inline-block bg-pink-500 text-white px-6 py-2 rounded-lg hover:bg-pink-600 transition">Go to Shop</a>
    </div>
  <?php endif; ?>
</main>

</body>
</html>