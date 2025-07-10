<?php
session_start();
require 'conn.php'; // your DB connection file

$isLoggedIn = isset($_SESSION['customer_id']); // Assuming 'customer_id' is stored in session upon login

// Check if product_id is passed in the URL
if (!isset($_GET['product_id'])) {
  header("Location: shop.php"); // Redirect if no product_id is passed
  exit();
}

$product_id = $_GET['product_id'];

// Fetch product details
$stmt = $conn->prepare("SELECT p.product_name, p.price_id, p.description, p.image_url, c.category_name FROM products p JOIN categories c ON p.category_id = c.category_id WHERE p.product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
  echo "Product not found!";
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Product Details</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2/dist/alpine.min.js" defer></script>
  <style>
    /* Add extra padding to prevent content from being hidden under the fixed navbar */
    body {
      padding-top: 4rem; /* Adjust this value depending on the navbar height */
    }

    .cart-animation {
      transition: transform 0.5s ease-in-out;
    }
  </style>
</head>

<body class="bg-pink-50 text-gray-800 font-sans" x-data="cart()" @keydown.escape.window="showLogin = false; showSignup = false">
  
<nav class="bg-pink-100 shadow-md fixed top-0 left-0 w-full z-50">
    <div class="max-w-7xl mx-auto px-4 py-4 flex flex-wrap items-center justify-between gap-4">
      
    <div class="flex flex-1 items-center gap-4">
    
        <h1 class="text-2xl font-bold text-pink-600 whitespace-nowrap">Seven Dwarfs Boutique</h1>
        <form action="shop.php" method="get" class="flex flex-1 max-w-sm">
          <input type="text" name="search" placeholder="Search products..." class="w-full px-3 py-2 border border-pink-300 rounded-md focus:outline-none focus:ring-2 focus:ring-pink-400 text-sm">
        </form>
      </div>

      
      <ul class="flex flex-wrap justify-center space-x-4 text-sm md:text-base">
        <li><a href="homepage.php" class="hover:text-pink-500">Home</a></li>
        <li><a href="shop.php" class="hover:text-pink-500 font-semibold">Shop</a></li>
      </ul>


      <div class="flex items-center gap-4 text-pink-600">
        
      <a href="cart.php" class="hover:text-pink-500 relative" title="Cart">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h11.1a1 1 0 001-.8l1.4-5.2H7zm0 0l-1-4H4" />
          </svg>
          
          <div x-show="cartCount > 0" class="absolute top-0 right-0 bg-pink-600 text-white text-xs rounded-full px-2 py-1">
            <span x-text="cartCount"></span>
          </div>
        </a>

        
        <div class="relative">
          <?php if ($isLoggedIn): ?>
          <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" class="hover:text-pink-500" title="Profile">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-pink-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </button>
            <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
              <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Profile</a>
              <a href="purchases.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-pink-100">My Purchases</a>
              <form action="logout.php" method="POST">
                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-500 hover:bg-pink-100">Logout</button>
              </form>
            </div>
          </div>
          <?php else: ?>
          <button @click="showLogin = true" class="hover:text-pink-500" title="Profile">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A4 4 0 0112 14a4 4 0 016.879 3.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>


  <div class="max-w-4xl mx-auto mt-10 bg-white shadow-lg rounded-lg p-8">
    <div class="flex">

      <div class="flex-none w-1/3">
      <img src="<?= htmlspecialchars($product['image_url']) ?>"
      alt="<?= htmlspecialchars($product['product_name']) ?>" class="w-full h-full object-cover rounded-lg">
      </div>

    
      <div class="ml-8 flex-1">
        <h2 class="text-3xl font-semibold text-pink-600"><?= htmlspecialchars($product['product_name']) ?></h2>
        <p class="text-lg font-semibold text-pink-600 mt-2">₱<?= number_format($product['price_id'], 2) ?></p>
        <p class="text-gray-600 mt-4"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
        <p class="text-gray-500 mt-2">Category: <?= htmlspecialchars($product['category_name']) ?></p>

        <div class="mt-6">
        <form method="POST" action="add_to_cart.php">
  <input type="hidden" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>">
  <input type="hidden" name="price_id" value="<?= $product['price_id'] ?>">
  <input type="hidden" name="product_id" value="<?= $product_id ?>">
  <input type="number" name="quantity" value="1" min="1" class="border rounded px-2 w-16">
    <br>
    <br>
    <button type="submit" class="bg-pink-500 text-white px-6 py-3 rounded-full text-xl shadow-md hover:bg-pink-600 transition">
      Add to Cart
    </button>
  </form>
</div>


        <div class="mt-6">
          <a href="shop.php" class="bg-pink-500 text-white px-6 py-3 rounded-full text-xl shadow-md hover:bg-pink-600 transition">
            Back to Shop
          </a>
        </div>
      </div>
    </div>
  </div>

  <script>
    function addToCart(productId) {
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Product added to cart!');
                window.location.href = data.redirectUrl; // Redirect to cart page
            } else {
                alert(data.message || 'Error adding product to cart');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });
    }
  </script>

  <script>
 function removeFromCart(productId) {
    // Send the POST request to remove the item
    fetch('remove_from_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json()) // Parse JSON response
    .then(data => {
        if (data.status === 'success') {
            // Redirect to the cart page on success
            window.location.href = data.redirectUrl;
        } else {
            // Display error message if the removal failed
            alert(data.message || 'Error removing item');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while processing your request.');
    });
}

</script>

</body>
</html>