<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            poppins: ['Poppins', 'sans-serif'],
          },
          colors: {
            primary: '#ec4899', // pink-500
          }
        }
      }
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 font-poppins text-sm transition-all duration-300">

 <body class="bg-gray-100 text-sm">
  <div class="flex h-screen">
   <!-- Sidebar -->
<div class="w-64 bg-white shadow-md min-h-screen" x-data="{ userMenu: false, productMenu: true }">
  <div class="p-4">
    <!-- Logo & Brand -->
    <div class="flex items-center space-x-4">
      <img src="logo2.png" alt="Logo" class="rounded-full w-12 h-12" />
      <h2 class="text-lg font-semibold">SevenDwarfs</h2>
    </div>

    <!-- Admin Info -->
    <div class="mt-4 flex items-center space-x-4">
      <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
      <div>
        <h3 class="text-sm font-semibold"><?php echo htmlspecialchars($admin_name); ?></h3>
        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($admin_role); ?></p>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="mt-6">
    <ul>

      <!-- Dashboard -->
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="dashboard.php" class="flex items-center">
          <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
        </a>
      </li>

      <!-- User Management -->
      <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
        <div class="flex items-center justify-between">
          <span class="flex items-center">
            <i class="fas fa-users-cog mr-2"></i>User Management
          </span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
        </div>
      </li>
      <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
        <li class="py-1 hover:text-pink-600"><a href="users.php" class="flex items-center"><i class="fas fa-user mr-2"></i>User</a></li>
        <li class="py-1 hover:text-pink-600"><a href="user_types.php" class="flex items-center"><i class="fas fa-id-badge mr-2"></i>Type</a></li>
        <li class="py-1 hover:text-pink-600"><a href="user_status.php" class="flex items-center"><i class="fas fa-toggle-on mr-2"></i>Status</a></li>
        <li class="py-1">
    <a href="customers.php" class="flex items-center space-x-2 hover:text-pink-600">
      <i class="fas fa-users"></i>
      <span>Customer</span>
    </a>
  </li>
      </ul>

      <!-- Product Management -->
      <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
        <div class="flex items-center justify-between">
          <span class="flex items-center">
            <i class="fas fa-box-open mr-2"></i>Product Management
          </span>
          <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
        </div>
      </li>
      <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
        <li class="py-1 hover:text-pink-600"><a href="categories.php" class="flex items-center"><i class="fas fa-tags mr-2"></i>Category</a></li>
        <li class="py-1 hover:text-pink-600"><a href="products.php" class="flex items-center"><i class="fas fa-box mr-2"></i>Product</a></li>
        <li class="py-1 hover:text-pink-600"><a href="inventory.php" class="flex items-center"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
        <li class="py-1 bg-pink-100 text-pink-600 rounded"><a href="stock_management.php" class="flex items-center"><i class="fas fa-boxes mr-2"></i>Stock Management</a></li>
        </ul>
      <!-- Other Pages -->
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="orders.php" class="flex items-center">
          <i class="fas fa-shopping-cart mr-2"></i>Orders
        </a>
      </li>

      <li class="px-4 py-2 hover:bg-gray-200 ">
          <a href="suppliers.php" class="flex items-center">
            <i class="fas fa-industry mr-2"></i>Suppliers
          </a>
        </li>

      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="payandtransac.php" class="flex items-center">
          <i class="fas fa-money-check-alt mr-2"></i>Payment & Transactions
        </a>
      </li>
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="storesettings.php" class="flex items-center">
          <i class="fas fa-cog mr-2"></i>Store Settings
        </a>
      </li>
      <li class="px-4 py-2 hover:bg-gray-200">
        <a href="logout.php" class="flex items-center">
          <i class="fas fa-sign-out-alt mr-2"></i>Log out
        </a>
      </li>
    </ul>
  </nav>
</div>

<!-- Main Content -->
<div class="flex-1 p-6 space-y-6 transition-all duration-300 font-poppins">
  <!-- Header -->
  <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm">
    <h1 class="text-2xl font-semibold">Stock Management</h1>
  </div>

  <!-- Filters & Controls -->
<div class="bg-white p-6 rounded-b-2xl shadow-md space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
     
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Stock Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <style>
    /* Custom scrollbar for sidebar if needed */
    ::-webkit-scrollbar {
      width: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background-color: #d6336c;
      border-radius: 3px;
    }
  </style>
</head>


   <div class="bg-white p-8 rounded-lg shadow w-full">
    <h2 class="text-lg font-semibold mb-4">Stock In Form</h2>

    <div class="grid grid-cols-2 gap-6">
        <div>
            <label class="block mb-1 font-medium">Select Product</label>
            <select class="border w-full p-2 rounded">
                <option>Select Product</option>
            </select>
        </div>

        <div>
            <label class="block mb-1 font-medium">Select Size</label>
            <select class="border w-full p-2 rounded">
                <option>Select Size</option>
            </select>
        </div>

        <div>
            <label class="block mb-1 font-medium">Select Color</label>
            <select class="border w-full p-2 rounded">
                <option>Red</option>
            </select>
        </div>

        <div>
            <label class="block mb-1 font-medium">Select Supplier</label>
            <select class="border w-full p-2 rounded">
                <option>Supplier 1</option>
            </select>
        </div>

        <div class="col-span-2">
            <button class="bg-pink-500 text-white py-2 px-6 rounded hover:bg-pink-600">
                Add Stock
            </button>
        </div>
    </div>
</div>


      <div class="bg-white p-8 rounded-lg shadow w-full">
        <h2 class="text-lg font-semibold mb-3 text-[#0f172a]">Current Stock</h2>
        <table class="w-full border border-gray-300 rounded-md text-[#0f172a] text-lg">
          <thead>
            <tr class="border-b border-gray-300">
              <th class="text-left px-4 py-3">Product</th>
              <th class="text-left px-4 py-3">Size</th>
              <th class="text-left px-4 py-3">Color</th>
              <th class="text-left px-4 py-3">Quantity</th>
              <th class="text-left px-4 py-3">Supplier</th>
              <th class="text-left px-4 py-3">Date Added</th>
            </tr>
          </thead>
          <tbody>
            <tr class="border-b border-gray-300">
              <td class="px-4 py-3">Product A</td>
              <td class="px-4 py-3">7</td>
              <td class="px-4 py-3">Red</td>
              <td class="px-4 py-3">20</td>
              <td class="px-4 py-3">Supplier 1</td>
              <td class="px-4 py-3">2025-08-10</td>
            </tr>
            <tr>
              <td class="px-4 py-3">Product B</td>
              <td class="px-4 py-3">Red</td>
              <td class="px-4 py-3">Black</td>
              <td class="px-4 py-3">15</td>
              <td class="px-4 py-3">Supplier 2</td>
              <td class="px-4 py-3">2025-08-09</td>
            </tr>
          </tbody>
        </table>
      </section>
    </section>
  </main>
</body>
</html>