<?php
require 'conn.php';
$suppliers = $conn->query("SELECT * FROM suppliers");
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>Suppliers Information</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            poppins: ['Poppins', 'sans-serif'],
          },
          colors: {
            primary: '#ec4899',
          }
        }
      }
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 font-poppins text-sm">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <div class="w-64 bg-white shadow-md" x-data="{ userMenu: false, productMenu: false }">
    <div class="p-4">
      <div class="flex items-center space-x-4">
        <img src="logo2.png" alt="Logo" class="rounded-full w-12 h-12" />
        <h2 class="text-lg font-semibold">SevenDwarfs</h2>
      </div>

      <div class="mt-4 flex items-center space-x-4">
        <img src="newID.jpg" alt="Admin" class="rounded-full w-10 h-10" />
        <div>
          <h3 class="text-sm font-semibold">Admin Name</h3>
          <p class="text-xs text-gray-500">Administrator</p>
        </div>
      </div>
    </div>

    <nav class="mt-6">
      <ul>
        <li class="px-4 py-2 hover:bg-gray-200">
          <a href="dashboard.php" class="flex items-center">
            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
          </a>
        </li>
        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="userMenu = !userMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center">
              <i class="fas fa-users-cog mr-2"></i>User Management
            </span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': userMenu }"></i>
          </div>
        </li>
        <ul x-show="userMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li><a href="users.php" class="hover:text-pink-600 flex items-center"><i class="fas fa-user mr-2"></i>User</a></li>
          <li><a href="user_types.php" class="hover:text-pink-600 flex items-center"><i class="fas fa-id-badge mr-2"></i>Type</a></li>
          <li><a href="user_status.php" class="hover:text-pink-600 flex items-center"><i class="fas fa-toggle-on mr-2"></i>Status</a></li>
          <li><a href="customers.php" class="hover:text-pink-600 flex items-center"><i class="fas fa-users mr-2"></i>Customer</a></li>
        </ul>
        <li class="px-4 py-2 hover:bg-gray-200 cursor-pointer" @click="productMenu = !productMenu">
          <div class="flex items-center justify-between">
            <span class="flex items-center">
              <i class="fas fa-box-open mr-2"></i>Product Management
            </span>
            <i class="fas fa-chevron-down transition-transform duration-200" :class="{ 'rotate-180': productMenu }"></i>
          </div>
        </li>
        <ul x-show="productMenu" x-transition class="pl-8 text-sm text-gray-700 space-y-1">
          <li><a href="categories.php" class="hover:text-pink-600 flex items-center"><i class="fas fa-tags mr-2"></i>Category</a></li>
          <li><a href="products.php" class="hover:text-pink-600 flex items-center"><i class="fas fa-box mr-2"></i>Product</a></li>
          <li><a href="inventory.php" class="hover:text-pink-600 flex items-center"><i class="fas fa-warehouse mr-2"></i>Inventory</a></li>
        </ul>
        <li class="px-4 py-2 hover:bg-gray-200">
          <a href="orders.php" class="flex items-center">
            <i class="fas fa-shopping-cart mr-2"></i>Orders
          </a>
        </li>
        <li class="px-4 py-2 hover:bg-gray-200 bg-pink-100 text-pink-600 rounded-r-lg">
          <a href="suppliers.php" class="flex items-center">
            <i class="fas fa-industry mr-2"></i>Suppliers
          </a>
        </li>
        <li class="px-4 py-2 hover:bg-gray-200">
          <a href="payandtransac.php" class="flex items-center">
            <i class="fas fa-money-check-alt mr-2"></i>Payment & Transactions
          </a>
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
  <div class="flex-1 p-6 overflow-auto">
    <!-- Header -->
    <div class="bg-pink-300 text-white p-4 rounded-t-2xl shadow-sm mb-4">
      <h1 class="text-2xl font-semibold">Suppliers Information</h1>
    </div>

    </head>
 <body class="bg-white text-gray-900 font-sans">
  <div class="max-w-full mx-4 my-6">
   <div class="flex justify-between items-center border-b border-gray-200 pb-3 mb-4">
    <h2 class="text-lg font-extrabold text-gray-900">
    
    </h2>
    <!-- Add Supplier Button -->
<div x-data="{ addModal: false }">
  <button 
    @click="addModal = true"
    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
  >
    <i class="fas fa-plus"></i>
    Add Supplier
  </button>

  <!-- Add Supplier Modal -->
  <div 
    x-show="addModal"
    @click.away="addModal = false"
    x-transition.opacity
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
  >
    <div 
      class="bg-white rounded-lg shadow-lg max-w-md w-full p-6"
      x-transition:enter="transition ease-out duration-300"
      x-transition:enter-start="opacity-0 scale-90"
      x-transition:enter-end="opacity-100 scale-100"
      x-transition:leave="transition ease-in duration-200"
      x-transition:leave-start="opacity-100 scale-100"
      x-transition:leave-end="opacity-0 scale-90"
    >
      <h2 class="text-xl font-bold text-indigo-600 mb-4">Add Supplier</h2>
      
      <form action="add_supplier.php" method="POST">
        <!-- Supplier Name -->
        <div class="mb-4">
          <label class="block text-gray-700 font-semibold">Supplier Name</label>
          <input type="text" name="supplier_name" required class="w-full border p-2 rounded-md focus:ring-indigo-500">
        </div>

        <!-- Category Dropdown -->
        <div class="mb-4">
          <label class="block text-gray-700 font-semibold">Category</label>
          <select name="category_id" required class="w-full border p-2 rounded-md focus:ring-indigo-500">
            <option disabled selected>Select Category</option>
            <?php
              require 'conn.php';
              $catRes = $conn->query("SELECT * FROM categories");
              while ($cat = $catRes->fetch_assoc()):
            ?>
              <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Email -->
        <div class="mb-4">
          <label class="block text-gray-700 font-semibold">Email</label>
          <input type="email" name="supplier_email" class="w-full border p-2 rounded-md focus:ring-indigo-500">
        </div>

        <!-- Phone -->
        <div class="mb-4">
          <label class="block text-gray-700 font-semibold">Phone</label>
          <input type="text" name="supplier_phone" class="w-full border p-2 rounded-md focus:ring-indigo-500">
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-4 mt-6">
          <button 
            type="button" 
            @click="addModal = false" 
            class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded"
          >
            Cancel
          </button>
          <button 
            type="submit" 
            class="px-4 py-2 bg-indigo-600 text-white hover:bg-indigo-700 rounded"
          >
            Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

   </div>
   <div class="bg-white border border-gray-200 rounded-md shadow-sm p-4">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4 gap-4 md:gap-0">
     <div class="flex items-center gap-2 text-sm text-gray-700">
     </div>
     <div class="flex justify-start">
  <div class="flex items-center gap-2 text-sm text-gray-700">
    <label class="whitespace-nowrap" for="search">
      Search:
    </label>
    <input
      class="border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
      id="search"
      name="search"
      type="search"
    />
  </div>
</div>

    </div>
    <div class="overflow-x-auto">
     <table class="min-w-full text-left text-sm text-gray-700 border-separate border-spacing-y-2">
      <thead>
       <tr class="bg-gray-50 border-b border-gray-200">
        <th class="px-3 py-2 font-extrabold cursor-pointer select-none" scope="col">
         ID
         <i class="fas fa-sort-up ml-1 text-gray-400 text-xs">
         </i>
        </th>
        <th class="px-3 py-2 font-extrabold cursor-pointer select-none" scope="col">
         ITEMS
         <i class="fas fa-sort-up ml-1 text-gray-400 text-xs">
         </i>
        </th>
        <th class="px-3 py-2 font-extrabold cursor-pointer select-none" scope="col">
         SUPPLIERS
         <i class="fas fa-sort-up ml-1 text-gray-400 text-xs">
         </i>
        </th>
        <th class="px-3 py-2 font-extrabold cursor-pointer select-none" scope="col">
         SUPPLIERS REGDATE
         <i class="fas fa-sort-up ml-1 text-gray-400 text-xs">
         </i>
        </th>
        <th class="px-3 py-2 font-extrabold cursor-pointer select-none" scope="col">
         MAIL
         <i class="fas fa-sort-up ml-1 text-gray-400 text-xs">
         </i>
        </th>
        <th class="px-3 py-2 font-extrabold cursor-pointer select-none" scope="col">
         PHONE
         <i class="fas fa-sort-up ml-1 text-gray-400 text-xs">
         </i>
        </th>
        <th class="px-3 py-2 font-extrabold cursor-pointer select-none" scope="col">
         ACTIONS
         <i class="fas fa-sort-up ml-1 text-gray-400 text-xs">
         </i>
        </th>
       </tr>
      </thead>
      <tbody>
<?php
require 'conn.php';
$sql = "SELECT s.*, c.category_name FROM suppliers s
LEFT JOIN categories c ON s.category_id = c.category_id";
$result = $conn->query($sql);

if ($result->num_rows > 0):
    while ($row = $result->fetch_assoc()):
?>

<!-- Wrapper that contains both the row and the modal -->
<div x-data="{ openModal: false }">
  <tr class="bg-white border border-gray-100 rounded-md">
    <td class="px-3 py-3 whitespace-nowrap"><?= htmlspecialchars($row['supplier_id']); ?></td>
    <td class="px-3 py-3"><?= htmlspecialchars($row['category_name']) ?></td>
    <td class="px-3 py-3 font-extrabold text-gray-900 whitespace-nowrap"><?= htmlspecialchars($row['supplier_name']); ?></td>
    <td class="px-3 py-3 whitespace-nowrap"><?= date('d/m/Y', strtotime($row['reg_date'] ?? $row['created_at'] ?? '')); ?></td>
    <td class="px-3 py-3 whitespace-nowrap"><?= htmlspecialchars($row['supplier_email']); ?></td>
    <td class="px-3 py-3 whitespace-nowrap"><?= htmlspecialchars($row['supplier_phone']); ?></td>
    <td class="px-3 py-3 whitespace-nowrap flex gap-2">
  <td class="px-3 py-3 whitespace-nowrap flex gap-2">
  <div x-data="{ open: false }">
    <!-- Trigger -->
    <button 
      @click="open = true"
      class="text-green-600 hover:text-green-700 border border-green-300 rounded px-2 py-1" 
      title="Edit"
    >
      <i class="fas fa-edit"></i>
    </button>

    <!-- Modal -->
    <div 
      x-show="open" 
      x-transition 
      @click.away="open = false" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      x-cloak
    >
      <div 
        class="bg-white rounded-lg shadow-xl max-w-md w-full p-6"
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-90"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-90"
      >
        <h2 class="text-xl font-bold text-pink-300 mb-4">Edit Supplier</h2>

        <form action="update_supplier.php" method="POST">
          <input type="hidden" name="supplier_id" value="<?= $row['supplier_id'] ?>">

          <!-- Category Dropdown -->
          <div class="mb-4">
            <label class="block text-gray-700 font-semibold">Category</label>
            <select name="category_id" required class="w-full border p-2 rounded-md focus:ring-pink-500">
              <option disabled>Select Category</option>
              <?php
                $catRes = $conn->query("SELECT * FROM categories");
                while ($cat = $catRes->fetch_assoc()):
                  $selected = ($cat['category_id'] == $row['category_id']) ? 'selected' : '';
              ?>
                <option value="<?= $cat['category_id'] ?>" <?= $selected ?>>
                  <?= htmlspecialchars($cat['category_name']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <!-- Supplier Name -->
          <div class="mb-4">
            <label class="block text-gray-700 font-semibold">Supplier Name</label>
            <input type="text" name="supplier_name" value="<?= htmlspecialchars($row['supplier_name']) ?>" required class="w-full border p-2 rounded-md focus:ring-pink-500">
          </div>

          <!-- Email -->
          <div class="mb-4">
            <label class="block text-gray-700 font-semibold">Email</label>
            <input type="email" name="supplier_email" value="<?= htmlspecialchars($row['supplier_email']) ?>" class="w-full border p-2 rounded-md focus:ring-pink-500">
          </div>

          <!-- Phone -->
          <div class="mb-4">
            <label class="block text-gray-700 font-semibold">Phone</label>
            <input type="text" name="supplier_phone" value="<?= htmlspecialchars($row['supplier_phone']) ?>" class="w-full border p-2 rounded-md focus:ring-pink-500">
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" @click="open = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-pink-300 text-white rounded hover:bg-pink-500">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete button (outside x-data) -->
  <button class="text-red-600 hover:text-red-700 border border-red-300 rounded px-2 py-1" title="Delete">
    <i class="fas fa-trash-alt"></i>
  </button>
</td>


<?php endwhile; else: ?>
<tr>
  <td colspan="7" class="text-center text-gray-500 py-6">No suppliers found.</td>
</tr>
<?php endif; ?>
</tbody>

     </table>
    </div>
    <div class="flex flex-col sm:flex-row justify-between items-center mt-4 text-xs text-gray-700 font-normal">
     <div class="mb-2 sm:mb-0">
      Showing 1 to 6 of 6 entries
     </div>
     <nav aria-label="Pagination" class="inline-flex rounded-md shadow-sm" role="navigation">
      <button aria-label="Previous page" class="rounded-l-md bg-indigo-100 text-indigo-600 px-4 py-2 font-medium hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
       Previous
      </button>
      <button aria-current="page" aria-label="Page 1" class="bg-indigo-700 text-white px-4 py-2 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-500">
       1
      </button>
      <button aria-label="Next page" class="rounded-r-md bg-indigo-100 text-indigo-600 px-4 py-2 font-medium hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
       Next
      </button>
     </nav>
    </div>
   </div>
  </div>
 </body>
</html>
