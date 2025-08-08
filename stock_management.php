<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock In Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    function updateStockInFields() {
      const sizeSelect = document.getElementById('sizeSelect');
      const colorSelect = document.getElementById('colorSelect');
      const container = document.getElementById('stockInInputs');
      container.innerHTML = '';

      const sizes = Array.from(sizeSelect.selectedOptions).map(opt => opt.value);
      const colors = Array.from(colorSelect.selectedOptions).map(opt => opt.value);

      sizes.forEach(size => {
        colors.forEach(color => {
          const id = `${size}_${color}`;
          container.innerHTML += `
            <div class='border p-4 rounded-md mb-4'>
              <h4 class='text-md font-medium mb-2'>${size} / ${color}</h4>
              <input type='hidden' name='stock[${size}][${color}][size]' value='${size}'>
              <input type='hidden' name='stock[${size}][${color}][color]' value='${color}'>

              <label class='block text-sm text-gray-700 mb-1'>Quantity</label>
              <input type='number' name='stock[${size}][${color}][quantity]' min='0' class='w-full mb-2 p-2 border rounded'>

              <label class='block text-sm text-gray-700 mb-1'>Purchase Price</label>
              <input type='number' step='0.01' name='stock[${size}][${color}][price]' min='0' class='w-full p-2 border rounded'>
            </div>
          `;
        });
      });
    }
  </script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-pink-500 mb-6">Stock In Management</h2>
    <form method="POST" action="stock_in.php">

      <!-- Product Selector -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Product</label>
        <select name="product_id" required class="mt-1 block w-full p-2 border rounded-md">
          <option value="">Select a product</option>
          <!-- Populate this with PHP -->
        </select>
      </div>

      <!-- Supplier Selector -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Supplier</label>
        <select name="supplier_id" required class="mt-1 block w-full p-2 border rounded-md">
          <option value="">Select a supplier</option>
          <!-- Populate this with PHP -->
        </select>
      </div>

      <!-- Date Added -->
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Date</label>
        <input type="date" name="date_added" required class="w-full p-2 border rounded">
      </div>

      <!-- Size and Color Selectors -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">Select Sizes</label>
          <select id="sizeSelect" multiple onchange="updateStockInFields()" class="mt-1 block w-full p-2 border rounded-md">
            <option value="XS">XS</option>
            <option value="S">S</option>
            <option value="M">M</option>
            <option value="L">L</option>
            <option value="XL">XL</option>
            <option value="Free Size">Free Size</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Select Colors</label>
          <select id="colorSelect" multiple onchange="updateStockInFields()" class="mt-1 block w-full p-2 border rounded-md">
            <option value="Red">Red</option>
            <option value="Black">Black</option>
            <option value="White">White</option>
            <option value="Pink">Pink</option>
            <option value="Blue">Blue</option>
            <option value="Green">Green</option>
            <option value="Yellow">Yellow</option>
            <option value="Purple">Purple</option>
          </select>
        </div>
      </div>

      <!-- Dynamic Stock Inputs -->
      <div id="stockInInputs"></div>

      <!-- Submit Button -->
      <div class="mt-6">
        <button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-700 transition">
          Stock In
        </button>
      </div>
    </form>
  </div>
</body>
</html>
