<?php
session_start();
require 'conn.php'; // Database connection

// ✅ Get product ID safely
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    echo "Invalid product ID.";
    exit();
}

// ✅ Fetch categories for dropdown
$categories = [];
$catQuery = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
while ($row = $catQuery->fetch_assoc()) {
    $categories[] = $row;
}

// ✅ Fetch suppliers for dropdown
$suppliers = [];
$supplierQuery = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
while ($row = $supplierQuery->fetch_assoc()) {
    $suppliers[] = $row;
}

// ✅ Fetch product details
$stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo "Product not found!";
    exit();
}

// ✅ Handle multiple image formats (JSON or comma-separated)
$existingImages = [];
if (!empty($product['image_url'])) {
    $trimmed = trim($product['image_url']);
    if (str_starts_with($trimmed, '[')) {
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $existingImages = $decoded;
        }
    } else {
        $existingImages = array_filter(array_map('trim', explode(',', $product['image_url'])));
    }
}

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_name   = trim($_POST['product_name']);
    $description    = trim($_POST['description']);
    $price          = (float)$_POST['price'];
    $supplier_price = (float)$_POST['supplier_price'];
    $category_id    = (int)$_POST['category_id'];
    $supplier_id    = (int)$_POST['supplier_id'];

    // 🖼 Handle image uploads
    $uploadedImages = [];
    $uploadDir = 'uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = basename($_FILES['images']['name'][$key]);
                $targetPath = $uploadDir . uniqid() . '_' . $fileName;

                if (move_uploaded_file($tmp_name, $targetPath)) {
                    $uploadedImages[] = $targetPath;
                }
            }
        }

        // Replace old images with new ones
        $finalImages = $uploadedImages;
    } else {
        $finalImages = $existingImages; // keep existing if no new upload
    }

    $imageString = json_encode($finalImages, JSON_UNESCAPED_SLASHES);

    // ✅ Update database
    $updateStmt = $conn->prepare("
        UPDATE products 
        SET product_name = ?, description = ?, price_id = ?, supplier_price = ?, 
            category_id = ?, supplier_id = ?, image_url = ?
        WHERE product_id = ?
    ");
    $updateStmt->bind_param(
        "ssddiisi",
        $product_name,
        $description,
        $price,
        $supplier_price,
        $category_id,
        $supplier_id,
        $imageString,
        $product_id
    );
    $updateStmt->execute();
    $updateStmt->close();

    header("Location: products.php?updated=1");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Product | Seven Dwarfs Boutique</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --rose: #e5a5b2;
      --rose-hover: #d48b98;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafb;
      color: #374151;
    }
  </style>
</head>

<body class="min-h-screen flex items-center justify-center bg-gray-100 px-4">
  <div class="max-w-3xl w-full bg-white shadow-md rounded-2xl p-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-2xl font-semibold text-[var(--rose)] flex items-center gap-2">
        <i class="fas fa-pen-nib"></i> Edit Product
      </h2>
      <a href="products.php" 
         class="flex items-center gap-2 bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition text-sm">
        <i class="fas fa-arrow-left"></i> Back
      </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
      <!-- Product Name -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Product Name</label>
        <input 
          type="text" 
          name="product_name" 
          value="<?= htmlspecialchars($product['product_name']); ?>" 
          required
          class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] outline-none"
        >
      </div>

      <!-- Price Section -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-gray-700 font-medium mb-1">Selling Price (₱)</label>
          <input 
            type="number" 
            name="price" 
            step="0.01" 
            value="<?= htmlspecialchars($product['price_id']); ?>" 
            required
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] outline-none"
          >
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Supplier Price (₱)</label>
          <input 
            type="number" 
            name="supplier_price" 
            step="0.01" 
            value="<?= htmlspecialchars($product['supplier_price'] ?? 0); ?>"
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] outline-none"
          >
        </div>
      </div>

      <!-- Category & Supplier -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-gray-700 font-medium mb-1">Category</label>
          <select 
            name="category_id" 
            required
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] outline-none"
          >
            <?php foreach ($categories as $cat): ?>
              <option 
                value="<?= $cat['category_id']; ?>" 
                <?= ($product['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($cat['category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-gray-700 font-medium mb-1">Supplier</label>
          <select 
            name="supplier_id" 
            required
            class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] outline-none"
          >
            <option value="">Select Supplier</option>
            <?php foreach ($suppliers as $sup): ?>
              <option 
                value="<?= $sup['supplier_id']; ?>" 
                <?= ($product['supplier_id'] == $sup['supplier_id']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars($sup['supplier_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Image Upload -->
      <div>
        <label class="block text-gray-700 font-medium mb-1">Upload New Images</label>
        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-[var(--rose)] transition">
          <input 
            type="file" 
            name="images[]" 
            multiple 
            accept="image/*"
            class="w-full text-sm text-gray-500"
          >
          <p class="text-xs text-gray-500 mt-2">Uploading new images will replace the old ones.</p>
        </div>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end gap-3 pt-4">
        <button 
          type="reset" 
          class="bg-gray-200 text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-300 transition"
        >
          Reset
        </button>
        <button 
          type="submit" 
          class="bg-[var(--rose)] hover:bg-[var(--rose-hover)] text-white px-6 py-2 rounded-lg shadow"
        >
          <i class="fas fa-save mr-1"></i> Save Changes
        </button>
      </div>
    </form>
  </div>
</body>
</html>
