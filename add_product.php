<?php
require 'conn.php';

$preselectedCategoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

// Fetch dropdown data
$category_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$supplier_result = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name   = trim($_POST['product_name']);
    $price          = floatval($_POST['price_id']);
    $supplier_price = floatval($_POST['supplier_price']);
    $category_id    = intval($_POST['category']);
    $supplier_id    = intval($_POST['supplier_id']);
    // var_dump($_POST);
    // Basic validation
    if ($product_name=="" || $price == "" || $supplier_price == "" || $category_id == "" || $supplier_id == "") {
        echo "<script>alert('⚠️ All fields are required and must be valid!');</script>";
    } else {
        // ✅ Handle multiple image uploads
        $target_dir = "uploads/products/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $image_filenames = [];
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $i => $filename) {
                $tmp_name = $_FILES['images']['tmp_name'][$i];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $allowed = ["jpg", "jpeg", "png", "gif"];

                if (in_array($ext, $allowed) && $_FILES['images']['error'][$i] === 0) {
                    $unique_name = uniqid('prod_') . '.' . $ext;
                    $target_file = $target_dir . $unique_name;

                    if (move_uploaded_file($tmp_name, $target_file)) {
                        // ✅ Store only the filename (not full path)
                        $image_filenames[] = $unique_name;
                    }
                }
            }
        }

        // ✅ Store filenames as JSON (recommended over comma-separated)
        $image_url_str = json_encode($image_filenames, JSON_UNESCAPED_SLASHES);

        // ✅ Compute revenue
        $revenue = $price - $supplier_price;

        // ✅ Insert product record
        $sql = "INSERT INTO products (product_name, price_id, supplier_price, revenue, category_id, image_url, supplier_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("sdddiis", $product_name, $price, $supplier_price, $revenue, $category_id, $image_url_str, $supplier_id);

            if ($stmt->execute()) {
                echo "<script>alert('✅ Product added successfully!'); window.location.href='products.php';</script>";
                exit;
            } else {
                echo "Error inserting product: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error preparing SQL statement: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Product | Seven Dwarfs Boutique</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --rose: #d37689;
  --rose-hover: #b75f6f;
}
body {
  font-family: 'Poppins', sans-serif;
  background-color: #f9fafb;
}
.card {
  background: #fff;
  border-radius: 1rem;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  padding: 2rem;
}
input, select, button {
  transition: all 0.2s ease;
}
</style>
</head>

<body class="min-h-screen flex items-center justify-center px-4 py-10">

  <div class="card w-full max-w-3xl">
    <!-- Header -->
    <div class="text-center mb-8">
      <h2 class="text-3xl font-bold text-[var(--rose)]">🛍️ Add New Product</h2>
      <p class="text-gray-500 text-sm mt-1">Fill in the product details below</p>
    </div>

    <!-- Flash Message (if applicable) -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="mb-4 px-4 py-3 rounded bg-red-100 text-red-700 font-medium text-center">
        <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?>
      </div>
    <?php elseif (isset($_SESSION['success'])): ?>
      <div class="mb-4 px-4 py-3 rounded bg-green-100 text-green-700 font-medium text-center">
        <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <!-- Product Form -->
    <form action="add_product.php" method="POST" enctype="multipart/form-data" class="space-y-8">
      
      <!-- 🧩 Product Info -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-700 font-medium mb-1">Product Name</label>
            <input type="text" name="product_name" required
              class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-1">Supplier Price</label>
            <input type="number" step="0.01" name="supplier_price" required
              class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-1">Selling Price</label>
            <input type="number" step="0.01" name="price_id" required
              class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
          </div>
        </div>
      </div>

      <!-- 🏷️ Category & Supplier -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Category & Supplier</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-700 font-medium mb-1">Category</label>
            <select name="category" required
              class="w-full border border-gray-300 rounded-lg p-3 bg-white focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
              <option value="">Select a category</option>
              <?php while ($row = $category_result->fetch_assoc()): ?>
              <option value="<?= $row['category_id']; ?>" <?= ($preselectedCategoryId == $row['category_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['category_name']); ?>
              </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div>
            <label class="block text-gray-700 font-medium mb-1">Supplier</label>
            <select name="supplier_id" required
              class="w-full border border-gray-300 rounded-lg p-3 bg-white focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
              <option value="">Select a supplier</option>
              <?php while ($row = $supplier_result->fetch_assoc()): ?>
              <option value="<?= $row['supplier_id']; ?>"><?= htmlspecialchars($row['supplier_name']); ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- 🖼️ Product Images -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Images</h3>
        <input type="file" name="images[]" accept="image/*" multiple
          class="block w-full text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[var(--rose)] file:text-white hover:file:bg-[var(--rose-hover)] transition cursor-pointer">
        <p class="text-sm text-gray-500 mt-2">You can select multiple images at once.</p>
      </div>

      <!-- 🧾 Buttons -->
      <div class="flex gap-4 pt-6">
        <button type="submit"
          class="flex-1 bg-[var(--rose)] text-white px-6 py-3 rounded-lg font-semibold shadow-md hover:bg-[var(--rose-hover)] active:scale-95 transition-all">
          <i class="fas fa-plus-circle mr-2"></i> Add Product
        </button>
        <a href="products.php"
          class="flex-1 bg-gray-100 text-gray-700 text-center px-6 py-3 rounded-lg font-medium hover:bg-gray-200 shadow-sm active:scale-95 transition-all">
          Cancel
        </a>
      </div>
    </form>
  </div>

</body>
</html>

