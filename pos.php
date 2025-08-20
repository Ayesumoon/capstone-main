<?php
require 'conn.php';

session_start(); // Ensure session_start() is called

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = "Admin";
$admin_role = "Admin";

if ($admin_id) {
    $query = "
        SELECT
            CONCAT(first_name, ' ', last_name) AS full_name,
            r.role_name
        FROM adminusers a
        LEFT JOIN roles r ON a.role_id = r.role_id
        WHERE a.admin_id = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $admin_name = $row['full_name'];
        $admin_role = $row['role_name'] ?? 'Admin';
    }
    $stmt->close(); // Close the prepared statement
}

// Fetch categories
$categories = [];
$categoryMap = [];

$categoryQuery = "SELECT category_id, category_name FROM categories";
$categoryResult = $conn->query($categoryQuery);
while ($row = $categoryResult->fetch_assoc()) {
    $categories[] = $row['category_name'];
    $categoryMap[$row['category_id']] = $row['category_name'];
}
$categoryResult->free(); // Free the result set

// Fetch products with their price directly from the products table
$productQuery = "
    SELECT
        product_id,
        product_name,
        price_id AS price_value,
        category_id,
        image_url
    FROM products
";

$productResult = $conn->query($productQuery);
$products = [];

while ($row = $productResult->fetch_assoc()) {
    $products[] = [
        'id' => (int)$row['product_id'],
        'name' => $row['product_name'],
        'price' => (float)$row['price_value'],
        'category' => $categoryMap[$row['category_id']] ?? 'Unknown',
        'image' => $row['image_url']   // Add image URL
    ];
}
$productResult->free(); // Free the result set
$conn->close(); // Close the database connection
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Point of Sale</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <style>
    #receipt {
        display: none;
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #receipt, #receipt * {
            visibility: visible;
        }

        #receipt {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            display: block;
        }
    }
</style>


</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <aside class="w-64 bg-white shadow-md">
            <div class="p-4">
                <div class="flex items-center space-x-4">
                    <img src="logo.png" alt="Logo" width="50" height="50" class="rounded-full" />
                    <h2 class="text-lg font-semibold">SevenDwarfs</h2>
                </div>
                <div class="mt-4 flex items-center space-x-4">
                    <img src="newID.jpg" alt="Admin" width="40" height="40" class="rounded-full" />
                    <div>
                        <h3 class="text-sm font-semibold"><?= htmlspecialchars($admin_name); ?></h3>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($admin_role); ?></p>
                    </div>
                </div>
            </div>
            <nav class="mt-6">
                <ul>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-tachometer-alt mr-2"></i><a href="dashboard.php">Dashboard</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-box mr-2"></i><a href="products.php">Products</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-shopping-cart mr-2"></i><a href="orders.php">Orders</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-users mr-2"></i><a href="customers.php">Customers</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-warehouse mr-2"></i><a href="inventory.php">Inventory</a></li>
                    <li class="px-4 py-2 bg-pink-100 text-pink-600"><i class="fas fa-cash-register mr-2"></i><a href="POS.php">Point of Sale</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-user mr-2"></i><a href="users.php">Users</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-money-check-alt mr-2"></i><a href="payandtransac.php">Payment & Transactions</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-cog mr-2"></i><a href="storesettings.php">Store Settings</a></li>
                    <li class="px-4 py-2 hover:bg-gray-200"><i class="fas fa-sign-out-alt mr-2"></i><a href="logout.php">Log out</a></li>
                </ul>
            </nav>
        </aside>

        <main class="flex-1 bg-gradient-to-br from-pink-50 to-pink-100 p-8 font-sans">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-4xl font-bold mb-8 text-center text-pink-700">🛒 Seven Dwarfs Boutique</h1>

        <div class="mb-6 flex items-center gap-3">
            <label class="font-semibold text-lg text-pink-800">Filter by Category:</label>
            <select id="categoryFilter" onchange="filterProducts()" class="p-2 border border-pink-300 rounded-lg shadow-sm bg-pink-50 focus:ring-pink-300">
                <option value="all">All</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlspecialchars($category); ?>"><?= htmlspecialchars($category); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">

            <div class="w-full lg:w-2/3">
                <div class="bg-white p-6 shadow-xl rounded-xl border border-pink-200 mb-10">
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4" id="product-list"></div>
                </div>
            </div>

            <div class="w-full lg:w-1/3">
                <div id="cart" class="bg-white p-6 rounded-xl border border-pink-200 shadow-lg text-base">
                    <h2 class="text-2xl font-semibold mb-4 text-pink-700">🛒 Cart</h2>
                    <div id="cart-items" class="space-y-6"></div>

                    <div class="mt-6 text-gray-800 text-lg">
                        <div class="font-bold text-2xl text-pink-700">Total: ₱<span id="total">0.00</span></div>
                    </div>

                    <div class="mt-6 space-y-4 text-lg">
                        <div>
                            <label for="cashReceived" class="font-medium text-pink-800">Cash Received:</label>
                            <input id="cashReceived" type="number" min="0" step="0.01"
                                    class="border border-pink-300 px-3 py-2 rounded w-full mt-2 text-lg focus:ring-pink-300"
                                    placeholder="Enter cash received" oninput="updateChange()" />
                        </div>
                        <div>
                            <span class="font-bold text-green-700">Change: ₱<span id="change">0.00</span></span>
                        </div>
                    </div>

                    <div class="flex gap-4 mt-6">
                        <button onclick="checkout()" class="bg-pink-600 text-white px-6 py-3 rounded hover:bg-pink-700 transition text-lg">Proceed</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="receipt" class="mt-10 bg-white p-6 shadow-xl rounded-xl border border-pink-200 max-w-md mx-auto font-mono text-sm text-gray-800">
            <h2 class="text-xl font-bold text-center mb-2 text-pink-700">CASH RECEIPT</h2>
            <p class="text-center text-pink-800">Seven Dwarfs Boutique<br>Address: Bayambang, Pangasinan<br>Tel: 123-456-7890</p>
            <hr class="my-2 border-dashed border-pink-300">
            <div class="flex justify-between">
                <span id="receipt-date">Date:</span>
                <span id="receipt-time"></span>
            </div>
            <hr class="my-2 border-dashed border-pink-300">
            <div id="receipt-items" class="space-y-1"></div>
            <hr class="my-2 border-dashed border-pink-300">
            <div class="space-y-1">
                <div>Total: ₱<span id="receipt-total">0.00</span></div>
                <div>Cash: ₱<span id="receipt-cash">0.00</span></div>
                <div>Change: ₱<span id="receipt-change">0.00</span></div>
            </div>
            <h3 class="text-center font-semibold mt-4 text-pink-600">THANK YOU</h3>
            <div class="flex justify-center mt-4">
                <div class="bg-black h-10 w-48"></div> </div>
        </div>
    </div>
</main>


<script>
    const now = new Date();
    const date = now.toLocaleDateString('en-GB'); // Format: DD/MM/YYYY
    const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); // Format: HH:MM

    document.getElementById('receipt-date').textContent = `Date: ${date}`;
    document.getElementById('receipt-time').textContent = time;
</script>


        </div>
    </main>
</div>
        </div>
    </div>
</main>

<script>
    // Check if products are passed correctly by logging the data
    console.log('Products data from PHP:', <?php echo json_encode($products); ?>);
    const products = <?php echo json_encode($products); ?>;

    // Make sure the products data is correctly passed to JavaScript
    if (!Array.isArray(products) || products.length === 0) {
        console.error('No products available in the JavaScript array.');
    }

    let filteredProducts = [...products];
    const cart = [];

    // Function to render the products in the product list
    function renderProducts() {
        console.log("Rendering products...");
        const productList = document.getElementById('product-list');

        if (!productList) {
            console.error('Product list container not found!');
            return;
        }

        productList.innerHTML = '';
        console.log('Number of products to render:', filteredProducts.length);

        filteredProducts.forEach(product => {
            console.log('Rendering product:', product.name);
            const productHTML = `
                <div class="border p-3 rounded-md shadow-sm text-center bg-gray-50 hover:shadow-md transition">
                    <img src="${product.image}" alt="${product.name}" class="w-24 h-24 mx-auto object-cover mb-2 rounded">
                    <p class="text-sm font-medium truncate">${product.name}</p>
                    <p class="text-xs text-gray-500">${product.category}</p>
                    <p class="text-sm font-semibold mt-1">₱${product.price}</p>
                    <button onclick="addToCart(${product.id})" class="mt-2 bg-green-500 text-white text-sm px-3 py-1 rounded hover:bg-green-600">Add</button>
                </div>
            `;
            productList.innerHTML += productHTML;
        });
        console.log("Products rendering complete.");
    }

    // Function to filter products based on the selected category
    function filterProducts() {
        const selected = document.getElementById('categoryFilter').value;
        filteredProducts = selected === 'all' ? [...products] : products.filter(p => p.category === selected);
        console.log('Filtered products:', filteredProducts);
        renderProducts();
    }

    // Function to add products to the cart
    function addToCart(productId) {
        const product = products.find(p => p.id === productId);
        const item = cart.find(c => c.id === productId);
        if (item) {
            item.qty++;
        } else {
            cart.push({ ...product, qty: 1 });
        }
        renderCart();
    }

    // Function to update the quantity of an item in the cart
    function updateQty(productId, change) {
        const item = cart.find(c => c.id === productId);
        if (!item) return;
        item.qty += change;
        if (item.qty <= 0) {
            const index = cart.findIndex(c => c.id === productId);
            cart.splice(index, 1);
        }
        renderCart();
    }

    // Function to remove an item from the cart
    function removeFromCart(productId) {
        const index = cart.findIndex(c => c.id === productId);
        if (index !== -1) cart.splice(index, 1);
        renderCart();
    }

    // Function to render the cart items
    function renderCart() {
        const cartItemsDiv = document.getElementById('cart-items');
        const totalSpan = document.getElementById('total');

        cartItemsDiv.innerHTML = '';
        let subtotal = 0;

        cart.forEach((item, index) => {
            const discountedPrice = item.price * (1 - (item.discount || 0) / 100);
            const itemTotal = discountedPrice * item.qty;
            subtotal += itemTotal;

            const itemDiv = document.createElement('div');
            itemDiv.className = 'border-b pb-2';

            itemDiv.innerHTML = `
                <div class="flex justify-between">
                    <span class="font-medium">${item.name} - ₱${item.price.toFixed(2)}</span>
                </div>
                <div class="flex gap-2 items-center mt-1">
                    <label>Qty:</label>
                    <input type="number" value="${item.qty}" min="1" class="w-12 border rounded px-1 text-sm" onchange="updateQuantity(${index}, this.value)">
                    <label>Discount(%):</label>
                    <input type="number" value="${item.discount || 0}" min="0" max="100" class="w-16 border rounded px-1 text-sm" onchange="updateDiscount(${index}, this.value)">
                    <span class="ml-auto">Total: ₱${itemTotal.toFixed(2)}</span>
                </div>
            `;

            cartItemsDiv.appendChild(itemDiv);
        });

        totalSpan.textContent = subtotal.toFixed(2); // Displaying only the total (no tax)
        updateChange();
    }

    // Function to update the quantity of an item in the cart
    function updateQuantity(index, value) {
        const qty = parseInt(value);
        if (qty > 0) {
            cart[index].qty = qty;
            renderCart();
        }
    }

    // Function to update the discount of an item in the cart
    function updateDiscount(index, value) {
        const discount = parseFloat(value);
        if (discount >= 0 && discount <= 100) {
            cart[index].discount = discount;
            renderCart();
        }
    }

    // Function to update the change when the user provides cash
    function updateChange() {
        const cashReceived = parseFloat(document.getElementById('cashReceived').value);
        let total = 0;

        cart.forEach(item => {
            const discountedPrice = item.price * (1 - (item.discount || 0) / 100);
            total += discountedPrice * item.qty;
        });

        const change = cashReceived - total;

        if (!isNaN(change) && change >= 0) {
            document.getElementById('change').textContent = change.toFixed(2);
        } else {
            document.getElementById('change').textContent = '0.00';
        }
    }

    // Function to handle the checkout process (without recording to server)
    function checkout() {
        if (cart.length === 0) {
            alert('Cart is empty!');
            return;
        }

        const now = new Date();
        const date = now.toLocaleDateString('en-GB');
        const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        document.getElementById('receipt-date').textContent = `Date: ${date}`;
        document.getElementById('receipt-time').textContent = time;

        const receiptItems = document.getElementById('receipt-items');
        receiptItems.innerHTML = '';

        let total = 0;

        cart.forEach(item => {
            const discountedPrice = item.price * (1 - (item.discount || 0) / 100);
            const lineTotal = discountedPrice * item.qty;
            total += lineTotal;

            const itemRow = document.createElement('div');
            itemRow.textContent = `${item.name} x${item.qty} - ₱${lineTotal.toFixed(2)}`;
            receiptItems.appendChild(itemRow);
        });

        const cashReceived = parseFloat(document.getElementById('cashReceived').value);
        const change = cashReceived - total;

        if (isNaN(cashReceived) || cashReceived < total) {
            alert('Invalid or insufficient cash received.');
            return;
        }

        document.getElementById('receipt-total').textContent = total.toFixed(2);
        document.getElementById('receipt-cash').textContent = cashReceived.toFixed(2);
        document.getElementById('receipt-change').textContent = change.toFixed(2);
        document.getElementById('change').textContent = change.toFixed(2);

        window.print();
        cart.length = 0;
        renderCart();
        document.getElementById('cashReceived').value = ''; // Clear cash received input
        document.getElementById('change').textContent = '0.00'; // Reset change display
    }

    // Run renderProducts once the page is loaded
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOMContentLoaded event fired.');
        renderProducts();
    });
</script>

</body>
</html>