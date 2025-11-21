<?php
session_start();
require 'conn.php';

$preselectedCategoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

// Fetch dropdown data
$category_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$supplier_result = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");

// -----------------------------
// Server-side handling
// -----------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Trim and fetch inputs
    $product_name   = trim($_POST['product_name'] ?? '');
    $price          = isset($_POST['price_id']) ? floatval($_POST['price_id']) : 0;
    $supplier_price = isset($_POST['supplier_price']) ? floatval($_POST['supplier_price']) : 0;
    $category_id    = isset($_POST['category']) ? intval($_POST['category']) : 0;
    $supplier_id    = isset($_POST['supplier_id']) ? intval($_POST['supplier_id']) : 0;

    $errors = [];

    // Basic required checks
    if ($product_name === '') {
        $errors[] = "Product name is required.";
    }
    if ($price <= 0) {
        $errors[] = "Selling price must be greater than 0.";
    }
    if ($supplier_price <= 0) {
        $errors[] = "Supplier price must be greater than 0.";
    }
    if ($category_id <= 0) {
        $errors[] = "Please select a valid category.";
    }
    if ($supplier_id <= 0) {
        $errors[] = "Please select a valid supplier.";
    }
    if ($price < $supplier_price) {
        $errors[] = "Selling price cannot be lower than supplier price.";
    }

    // Check category exists
    $cat_check = $conn->prepare("SELECT category_id FROM categories WHERE category_id = ?");
    $cat_check->bind_param("i", $category_id);
    $cat_check->execute();
    $cat_check_res = $cat_check->get_result();
    if ($cat_check_res->num_rows === 0) {
        $errors[] = "Selected category does not exist.";
    }
    $cat_check->close();

    // Check supplier exists
    $sup_check = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
    $sup_check->bind_param("i", $supplier_id);
    $sup_check->execute();
    $sup_check_res = $sup_check->get_result();
    if ($sup_check_res->num_rows === 0) {
        $errors[] = "Selected supplier does not exist.";
    }
    $sup_check->close();

    // Duplicate product name check (case-insensitive)
    $dup_check = $conn->prepare("SELECT product_id FROM products WHERE LOWER(product_name) = LOWER(?) LIMIT 1");
    $dup_check->bind_param("s", $product_name);
    $dup_check->execute();
    $dup_res = $dup_check->get_result();
    if ($dup_res->num_rows > 0) {
        $errors[] = "A product with that name already exists.";
    }
    $dup_check->close();

    // Image validation - require at least 1 image
    $image_filenames = [];
    $has_images = !empty($_FILES['images']['name'][0]) && $_FILES['images']['name'][0] !== '';
    if (!$has_images) {
        $errors[] = "Please upload at least one product image.";
    } else {
        // Validate extensions & upload errors
        $allowed = ["jpg", "jpeg", "png", "gif"];
        foreach ($_FILES['images']['name'] as $i => $filename) {
            if (empty($filename)) continue;
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errors[] = "Only JPG, PNG, and GIF images are allowed.";
                break;
            }
            if (!isset($_FILES['images']['error'][$i]) || $_FILES['images']['error'][$i] !== 0) {
                $errors[] = "One of the image files failed to upload. Please try again.";
                break;
            }
            // Optional: limit file size (example 5MB)
            if (isset($_FILES['images']['size'][$i]) && $_FILES['images']['size'][$i] > 5 * 1024 * 1024) {
                $errors[] = "Each image must be smaller than 5MB.";
                break;
            }
        }
    }

    // If errors, set flash message and redirect back
    if (!empty($errors)) {
        $_SESSION['message'] = implode("<br>", $errors);
        header("Location: add_product.php" . ($preselectedCategoryId ? "?category_id=".$preselectedCategoryId : ""));
        exit;
    }

    // Proceed with upload (we already validated)
    $target_dir = "uploads/products/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    foreach ($_FILES['images']['name'] as $i => $filename) {
        if (empty($filename)) continue;
        $tmp_name = $_FILES['images']['tmp_name'][$i];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $unique_name = uniqid('prod_') . '.' . $ext;
        $target_file = $target_dir . $unique_name;
        if (move_uploaded_file($tmp_name, $target_file)) {
            // store filename only
            $image_filenames[] = $target_file; // keep path relative so it's easy to render later
        }
    }

    // JSON encode image paths
    $image_url_str = json_encode($image_filenames, JSON_UNESCAPED_SLASHES);

    // Compute revenue
    $revenue = $price - $supplier_price;

    // Insert product using prepared statement
    $sql = "INSERT INTO products (product_name, price_id, supplier_price, revenue, category_id, image_url, supplier_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['message'] = "Database prepare error: " . $conn->error;
        header("Location: add_product.php");
        exit;
    }
    // bind types: s d d d i s i  -> "sdddisi"
    $stmt->bind_param("sdddisi", $product_name, $price, $supplier_price, $revenue, $category_id, $image_url_str, $supplier_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Product added successfully!";
        $stmt->close();
        header("Location: products.php");
        exit;
    } else {
        $_SESSION['message'] = "Database error: " . $stmt->error;
        $stmt->close();
        header("Location: add_product.php");
        exit;
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
.img-preview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
  gap: 0.5rem;
}
.img-preview img {
  width: 100%;
  height: 90px;
  object-fit: cover;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
}
.flash {
  transition: all .2s ease;
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

    <!-- Flash Message -->
    <?php if (isset($_SESSION['message'])): ?>
      <div id="flash" class="mb-4 px-4 py-3 rounded bg-red-100 text-red-700 font-medium text-center flash">
        <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['message']); ?>
      </div>
    <?php elseif (isset($_SESSION['success'])): ?>
      <div id="flash" class="mb-4 px-4 py-3 rounded bg-green-100 text-green-700 font-medium text-center flash">
        <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <!-- Product Form -->
    <form id="productForm" action="add_product.php<?= $preselectedCategoryId ? '?category_id='.$preselectedCategoryId : '' ?>" method="POST" enctype="multipart/form-data" class="space-y-8" novalidate>
      
      <!-- 🧩 Product Info -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="product_name" class="block text-gray-700 font-medium mb-1">Product Name</label>
            <input id="product_name" type="text" name="product_name" required
              class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none" placeholder="e.g. Floral Blouse">
            <p id="product_name_error" class="text-sm text-red-600 mt-1 hidden"></p>
          </div>

          <div>
            <label for="supplier_price" class="block text-gray-700 font-medium mb-1">Supplier Price</label>
            <input id="supplier_price" type="number" step="0.01" name="supplier_price" required
              class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none" placeholder="0.00">
            <p id="supplier_price_error" class="text-sm text-red-600 mt-1 hidden"></p>
          </div>

          <div>
            <label for="price_id" class="block text-gray-700 font-medium mb-1">Selling Price</label>
            <input id="price_id" type="number" step="0.01" name="price_id" required
              class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-[var(--rose)] focus:outline-none" placeholder="0.00">
            <p id="price_id_error" class="text-sm text-red-600 mt-1 hidden"></p>
          </div>
        </div>
      </div>

      <!-- 🏷️ Category & Supplier -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Category & Supplier</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="category" class="block text-gray-700 font-medium mb-1">Category</label>
            <select id="category" name="category" required
              class="w-full border border-gray-300 rounded-lg p-3 bg-white focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
              <option value="">Select a category</option>
              <?php
                // we must rewind the result sets since we fetched earlier
                if ($category_result) {
                    $category_result->data_seek(0);
                    while ($row = $category_result->fetch_assoc()):
              ?>
              <option value="<?= intval($row['category_id']); ?>" <?= ($preselectedCategoryId == $row['category_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['category_name']); ?>
              </option>
              <?php
                    endwhile;
                }
              ?>
            </select>
            <p id="category_error" class="text-sm text-red-600 mt-1 hidden"></p>
          </div>

          <div>
            <label for="supplier_id" class="block text-gray-700 font-medium mb-1">Supplier</label>
            <select id="supplier_id" name="supplier_id" required
              class="w-full border border-gray-300 rounded-lg p-3 bg-white focus:ring-2 focus:ring-[var(--rose)] focus:outline-none">
              <option value="">Select a supplier</option>
              <?php
                if ($supplier_result) {
                    $supplier_result->data_seek(0);
                    while ($row = $supplier_result->fetch_assoc()):
              ?>
              <option value="<?= intval($row['supplier_id']); ?>"><?= htmlspecialchars($row['supplier_name']); ?></option>
              <?php
                    endwhile;
                }
              ?>
            </select>
            <p id="supplier_error" class="text-sm text-red-600 mt-1 hidden"></p>
          </div>
        </div>
      </div>

      <!-- 🖼️ Product Images -->
      <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Images</h3>
        <input id="images" type="file" name="images[]" accept="image/*" multiple required
          class="block w-full text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[var(--rose)] file:text-white hover:file:bg-[var(--rose-hover)] transition cursor-pointer">
        <p id="images_error" class="text-sm text-red-600 mt-2 hidden"></p>
        <p class="text-sm text-gray-500 mt-2">You can select multiple images at once. Max 5MB per image.</p>

        <div class="mt-4 img-preview" id="imgPreview"></div>
      </div>

      <!-- 🧾 Buttons -->
      <div class="flex gap-4 pt-6">
        <button id="submitBtn" type="submit"
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

  <!-- Scripts -->
  <script>
    // Helper: show/hide error text
    function showError(id, message) {
      const el = document.getElementById(id);
      if (!el) return;
      if (message) {
        el.innerText = message;
        el.classList.remove('hidden');
      } else {
        el.innerText = '';
        el.classList.add('hidden');
      }
    }

    // Auto-capitalize product name on blur
    document.getElementById('product_name').addEventListener('blur', function() {
      this.value = this.value.replace(/\s+/g, ' ').trim();
      // Capitalize first letter of each word
      this.value = this.value.split(' ').map(w => w ? w.charAt(0).toUpperCase() + w.slice(1) : '').join(' ');
    });

    // Live image preview
    const imagesInput = document.getElementById('images');
    const imgPreview = document.getElementById('imgPreview');
    imagesInput.addEventListener('change', function() {
      imgPreview.innerHTML = '';
      const files = Array.from(this.files);
      if (!files.length) return;
      files.forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = function(e) {
          const img = document.createElement('img');
          img.src = e.target.result;
          img.alt = file.name;
          imgPreview.appendChild(img);
        };
        reader.readAsDataURL(file);
      });
    });

    // Client-side validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
      // reset errors
      showError('product_name_error', '');
      showError('supplier_price_error', '');
      showError('price_id_error', '');
      showError('category_error', '');
      showError('supplier_error', '');
      showError('images_error', '');

      let valid = true;

      const name = document.getElementById('product_name').value.trim();
      const supplierPrice = parseFloat(document.getElementById('supplier_price').value);
      const price = parseFloat(document.getElementById('price_id').value);
      const category = document.getElementById('category').value;
      const supplier = document.getElementById('supplier_id').value;
      const files = document.getElementById('images').files;

      if (!name) {
        showError('product_name_error', 'Product name is required.');
        valid = false;
      }
      if (isNaN(supplierPrice) || supplierPrice <= 0) {
        showError('supplier_price_error', 'Supplier price must be greater than 0.');
        valid = false;
      }
      if (isNaN(price) || price <= 0) {
        showError('price_id_error', 'Selling price must be greater than 0.');
        valid = false;
      }
      if (!isNaN(price) && !isNaN(supplierPrice) && price < supplierPrice) {
        showError('price_id_error', 'Selling price cannot be lower than supplier price.');
        valid = false;
      }
      if (!category) {
        showError('category_error', 'Please select a category.');
        valid = false;
      }
      if (!supplier) {
        showError('supplier_error', 'Please select a supplier.');
        valid = false;
      }
      if (!files || files.length === 0) {
        showError('images_error', 'Please upload at least one image.');
        valid = false;
      } else {
        // Check file types & sizes client-side
        for (let i = 0; i < files.length; i++) {
          const f = files[i];
          if (!f.type.startsWith('image/')) {
            showError('images_error', 'Only image files are allowed.');
            valid = false;
            break;
          }
          if (f.size > 5 * 1024 * 1024) {
            showError('images_error', 'Each image must be smaller than 5MB.');
            valid = false;
            break;
          }
        }
      }

      if (!valid) {
        e.preventDefault();
        // scroll to top of form to show validation
        window.scrollTo({ top: document.querySelector('.card').offsetTop - 20, behavior: 'smooth' });
        return false;
      }

      // let the form submit
      return true;
    });

    // Auto-hide flash after 4s
    const flash = document.getElementById('flash');
    if (flash) {
      setTimeout(() => {
        flash.style.opacity = '0';
        setTimeout(() => flash.remove(), 400);
      }, 4000);
    }
  </script>

  <!-- FontAwesome (icons) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" defer></script>
</body>
</html>
