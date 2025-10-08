<?php
require 'conn.php';

$preselectedCategoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;

// Fetch dropdown data
$category_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$supplier_result = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name ASC");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name   = trim($_POST['product_name'] ?? "");
    $price          = floatval($_POST['price'] ?? 0);
    $supplier_price = floatval($_POST['supplier_price'] ?? 0);
    $category_id    = intval($_POST['category'] ?? 0);
    $supplier_id    = intval($_POST['supplier_id'] ?? 0);

    // Basic validation
    if (empty($product_name) || $price <= 0 || $supplier_price <= 0 || $category_id <= 0 || $supplier_id <= 0) {
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
    <title>Add Product</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-pink-400 mb-6">Add New Product</h2>

        <form action="add_product.php" method="POST" enctype="multipart/form-data" class="space-y-8">
            <!-- Product Information -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Product Name</label>
                        <input type="text" name="product_name" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-pink-500 focus:border-pink-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Supplier Price</label>
                        <input type="number" step="0.01" name="supplier_price" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-pink-500 focus:border-pink-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Selling Price</label>
                        <input type="number" step="0.01" name="price" required
                            class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-pink-500 focus:border-pink-500">
                    </div>
                </div>
            </div>

            <!-- Category and Supplier -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Category & Supplier</h3>

                <div class="mt-4">
                    <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                    <select name="category" id="category" required
                        class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-pink-500 focus:border-pink-500">
                        <option value="">Select a category</option>
                        <?php while ($row = $category_result->fetch_assoc()): ?>
                        <option value="<?= $row['category_id']; ?>" <?= ($preselectedCategoryId == $row['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['category_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="mt-4">
                    <label for="supplier" class="block text-sm font-medium text-gray-700">Supplier</label>
                    <select name="supplier_id" id="supplier" required
                        class="mt-1 block w-full border border-gray-300 rounded-md p-2 focus:ring-pink-500 focus:border-pink-500">
                        <option value="">Select a supplier</option>
                        <?php while ($row = $supplier_result->fetch_assoc()): ?>
                        <option value="<?= $row['supplier_id']; ?>">
                            <?= htmlspecialchars($row['supplier_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Product Images -->
            <div>
                <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Images</h3>
                <input type="file" name="images[]" accept="image/*" multiple
                    class="block w-full text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-pink-400 file:text-white hover:file:bg-pink-500 transition">
            </div>

            <!-- Actions -->
            <div class="pt-6 flex gap-4">
                <input type="submit" value="Add Product"
                    class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-700 transition-all cursor-pointer">
                <a href="products.php" class="text-pink-400 hover:underline self-center">Back to Products</a>
            </div>
        </form>
    </div>
</body>
</html>
