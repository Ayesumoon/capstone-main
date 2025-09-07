<?php
require 'conn.php';
session_start();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid product ID!'); window.location.href='products.php';</script>";
    exit;
}

$product_id = intval($_GET['id']);

// Fetch product
$sql = "SELECT * FROM products WHERE product_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "<script>alert('Product not found!'); window.location.href='products.php';</script>";
    exit;
}
$product = $result->fetch_assoc();
$stmt->close();

// Categories & Suppliers
$category_result = $conn->query("SELECT category_id, category_name FROM categories");
$supplier_result = $conn->query("SELECT supplier_id, supplier_name FROM suppliers");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = trim($_POST['product_name']);
    $sizes_input = trim($_POST['sizes_input'] ?? '');
    $colors_input = trim($_POST['colors_input'] ?? '');
    $sizes = array_filter(array_map('trim', explode(',', $sizes_input)));
    $colors = array_filter(array_map('trim', explode(',', $colors_input)));

    $description = "Sizes: " . implode(", ", $sizes) . " | Colors: " . implode(", ", $colors);

    $price_id = floatval($_POST['price']);
    $category_id = intval($_POST['category']);
    $supplier_id = intval($_POST['supplier']);
    $supplier_price = floatval($_POST['supplier_price']);

    // Handle images
    $image_urls = [];
    if (isset($_FILES["images"]) && count($_FILES["images"]["name"]) > 0) {
        $target_dir = "uploads/products/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        foreach ($_FILES["images"]["tmp_name"] as $index => $tmp_name) {
            $file_name = $_FILES["images"]["name"][$index];
            $file_error = $_FILES["images"]["error"][$index];
            if ($file_error === 0) {
                $unique_name = uniqid() . "_" . basename($file_name);
                $target_file = $target_dir . $unique_name;
                $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                $allowed = ["jpg","jpeg","png","gif"];
                if (in_array($ext, $allowed)) {
                    move_uploaded_file($tmp_name, $target_file);
                    $image_urls[] = $target_file;
                }
            }
        }
    }
    $image_url = !empty($image_urls) ? implode(",", $image_urls) : $product['image_url'];

    // Save sizes if not exists
    $size_ids = [];
    foreach ($sizes as $size) {
        $stmt = $conn->prepare("SELECT size_id FROM sizes WHERE size = ?");
        $stmt->bind_param("s", $size);
        $stmt->execute();
        $stmt->bind_result($size_id);
        if ($stmt->fetch()) {
            $size_ids[] = $size_id;
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO sizes (size) VALUES (?)");
            $stmt->bind_param("s", $size);
            $stmt->execute();
            $size_ids[] = $stmt->insert_id;
        }
        $stmt->close();
    }

    // Save colors if not exists
    $color_ids = [];
    foreach ($colors as $color) {
        $stmt = $conn->prepare("SELECT color_id FROM colors WHERE color = ?");
        $stmt->bind_param("s", $color);
        $stmt->execute();
        $stmt->bind_result($color_id);
        if ($stmt->fetch()) {
            $color_ids[] = $color_id;
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO colors (color) VALUES (?)");
            $stmt->bind_param("s", $color);
            $stmt->execute();
            $color_ids[] = $stmt->insert_id;
        }
        $stmt->close();
    }

    // Update product
    $update_sql = "UPDATE products SET product_name=?, description=?, price_id=?, category_id=?, stocks=?, image_url=?, supplier_id=?, supplier_price=? WHERE product_id=?";
    $stmt = $conn->prepare($update_sql);
    if ($stmt) {
        $stmt->bind_param("ssdissdii", $product_name, $description, $price_id, $category_id, $stocks, $image_url, $supplier_id, $supplier_price, $product_id);
        if ($stmt->execute()) {
            echo "<script>alert('Product updated successfully!'); window.location.href='products.php';</script>";
        } else {
            echo "Error updating product: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Product</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-100 to-gray-200 min-h-screen flex items-center justify-center p-6">

  <div class="max-w-3xl w-full bg-white p-8 rounded-2xl shadow-lg transform transition duration-500 hover:shadow-xl">
    <h2 class="text-3xl font-bold text-pink-600 mb-6 tracking-tight">✏️ Edit Product</h2>

    <form action="edit_product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data" class="space-y-10">

      <!-- Product Info -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Product Name -->
          <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-700">Product Name</label>
            <input type="text" name="product_name" required value="<?php echo htmlspecialchars($product['product_name']); ?>"
              class="mt-2 block w-full rounded-lg border border-gray-300 p-3 shadow-sm focus:ring-2 focus:ring-pink-400 focus:border-pink-400 transition duration-200">
          </div>
          <!-- Supplier Price -->
          <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-700">Supplier Price</label>
            <input type="number" step="0.01" name="supplier_price" required value="<?php echo $product['supplier_price']; ?>"
              class="mt-2 block w-full rounded-lg border border-gray-300 p-3 shadow-sm focus:ring-2 focus:ring-pink-400 focus:border-pink-400 transition duration-200">
          </div>
          <!-- Selling Price -->
          <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-700">Selling Price</label>
            <input type="number" step="0.01" name="price" required value="<?php echo htmlspecialchars($product['price_id']); ?>"
              class="mt-2 block w-full rounded-lg border border-gray-300 p-3 shadow-sm focus:ring-2 focus:ring-pink-400 focus:border-pink-400 transition duration-200">
          </div>
        </div>
      </div>

      <!-- Category and Supplier -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Category & Supplier</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- Category -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Category</label>
            <select name="category" required
              class="mt-2 block w-full rounded-lg border border-gray-300 p-3 bg-white shadow-sm focus:ring-2 focus:ring-pink-400 focus:border-pink-400 transition">
              <option value="">Select Category</option>
              <?php
              while ($row = $category_result->fetch_assoc()) {
                  $selected = ($product['category_id'] == $row['category_id']) ? "selected" : "";
                  echo "<option value='{$row['category_id']}' $selected>" . htmlspecialchars($row['category_name']) . "</option>";
              }
              ?>
            </select>
          </div>
          <!-- Supplier -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Supplier</label>
            <select name="supplier" required
              class="mt-2 block w-full rounded-lg border border-gray-300 p-3 bg-white shadow-sm focus:ring-2 focus:ring-pink-400 focus:border-pink-400 transition">
              <option value="">Select Supplier</option>
              <?php
              while ($row = $supplier_result->fetch_assoc()) {
                  $selected = ($product['supplier_id'] == $row['supplier_id']) ? "selected" : "";
                  echo "<option value='{$row['supplier_id']}' $selected>" . htmlspecialchars($row['supplier_name']) . "</option>";
              }
              ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Media Upload -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Images</h3>
        <input type="file" name="images[]" accept="image/*" multiple
          class="block w-full text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-pink-500 file:text-white hover:file:bg-pink-600 cursor-pointer transition">

        <?php if (!empty($product['image_url'])): 
          $imageArray = explode(",", $product['image_url']);
        ?>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-6">
            <?php foreach ($imageArray as $img): ?>
              <div class="relative group">
                <img src="<?php echo htmlspecialchars(trim($img)); ?>" alt="Product Image"
                  class="w-full h-28 object-cover rounded-lg shadow border transform group-hover:scale-105 transition duration-300">
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Action Buttons -->
      <div class="flex gap-6 pt-6">
        <input type="submit" value="Update Product"
          class="bg-pink-500 text-white px-6 py-3 rounded-lg shadow-md hover:bg-pink-600 transform hover:scale-105 transition-all cursor-pointer">
        <a href="products.php"
          class="text-pink-600 font-medium hover:underline self-center transition">← Back to Products</a>
      </div>
    </form>
  </div>

</body>
</html>
