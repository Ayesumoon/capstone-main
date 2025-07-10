<?php
require 'conn.php'; // Database connection
session_start();

// Check if product_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('Invalid product ID!'); window.location.href='products.php';</script>";
    exit;
}

$product_id = intval($_GET['id']);

// Fetch product details
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

// Fetch product details with supplier price
$sql = "SELECT p.*, p.supplier_price FROM products p
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        WHERE p.product_id = ?";
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


// Fetch categories
$category_query = "SELECT category_id, category_name FROM categories";
$category_result = $conn->query($category_query);

// Fetch suppliers with ID and name
$supplier_query = "SELECT supplier_id, supplier_name FROM suppliers";
$supplier_result = $conn->query($supplier_query);

// Handle form submission
// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_name = trim($_POST['product_name']);
    $sizes = isset($_POST['sizes']) ? implode(", ", $_POST['sizes']) : "";
    $colors = isset($_POST['colors']) ? implode(", ", $_POST['colors']) : "";

    $description = "Sizes: " . $sizes . " | Colors: " . $colors;

    $price_id = floatval($_POST['price']);
    $category_id = intval($_POST['category']);
    $stocks = intval($_POST['stocks']);
    $supplier_id = intval($_POST['supplier']);
    $supplier_price = floatval($_POST['supplier_price']);

    $image_urls = [];

// Handle multiple images
if (isset($_FILES["images"]) && count($_FILES["images"]["name"]) > 0) {
    $target_dir = "uploads/products/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    foreach ($_FILES["images"]["tmp_name"] as $index => $tmp_name) {
        $file_name = $_FILES["images"]["name"][$index];
        $file_tmp = $_FILES["images"]["tmp_name"][$index];
        $file_error = $_FILES["images"]["error"][$index];

        if ($file_error === 0) {
            $unique_name = uniqid() . "_" . basename($file_name);
            $target_file = $target_dir . $unique_name;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ["jpg", "jpeg", "png", "gif"];

            if (in_array($imageFileType, $allowed_types)) {
                move_uploaded_file($file_tmp, $target_file);
                $image_urls[] = $target_file;
            }
        }
    }
}

// If no new images uploaded, retain old
$image_url = !empty($image_urls) ? implode(",", $image_urls) : $product['image_url'];

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
    } else {
        echo "Error preparing statement.";
    }
}

?>

<!-- HTML part -->
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
    font-family: 'Poppins', sans-serif;
  }
</style>

</head>
<body class="bg-gray-100 min-h-screen p-6 transition-all duration-300 ease-in-out">

    <div class="max-w-3xl mx-auto bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-pink-600 mb-4">Edit Product</h2>

        <form action="edit_product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data" class="space-y-8">

    <!-- Product Info -->
    <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Product Information</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Product Name</label>
                <input type="text" name="product_name" required value="<?php echo htmlspecialchars($product['product_name']); ?>"
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:ring-pink-500 focus:border-pink-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Stock Quantity</label>
                <input type="number" name="stocks" required value="<?php echo $product['stocks']; ?>"
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:ring-pink-500 focus:border-pink-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Supplier Price</label>
                <input type="number" step="0.01" name="supplier_price" required value="<?php echo $product['supplier_price']; ?>"
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:ring-pink-500 focus:border-pink-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Selling Price</label>
                <input type="number" step="0.01" name="price" required value="<?php echo htmlspecialchars($product['price_id']); ?>"
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:ring-pink-500 focus:border-pink-500">
            </div>
        </div>
    </div>

    <!-- Category and Supplier -->
    <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Category & Supplier</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Category</label>
                <select name="category" required
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:ring-pink-500 focus:border-pink-500">
                    <option value="">Select Category</option>
                    <?php
                    while ($row = $category_result->fetch_assoc()) {
                        $selected = ($product['category_id'] == $row['category_id']) ? "selected" : "";
                        echo "<option value='{$row['category_id']}' $selected>" . htmlspecialchars($row['category_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Supplier</label>
                <select name="supplier" required
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2 focus:ring-pink-500 focus:border-pink-500">
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
        class="block w-full text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-pink-500 file:text-white hover:file:bg-pink-600">

    <?php if (!empty($product['image_url'])): 
        $imageArray = explode(",", $product['image_url']);
    ?>
        <div class="grid grid-cols-3 gap-4 mt-4">
            <?php foreach ($imageArray as $img): ?>
                <img src="<?php echo htmlspecialchars(trim($img)); ?>" alt="Product Image"
                     class="w-24 h-24 object-cover rounded shadow border">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>


    <!-- Sizes -->
    <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Sizes</h3>
        <div class="flex flex-wrap gap-3">
            <?php
            $sizeOptions = ['XS', 'S', 'M', 'L', 'XL', 'Free Size'];
            foreach ($sizeOptions as $size) {
                $checked = (strpos($product['description'], $size) !== false) ? "checked" : "";
                echo '
                <label class="cursor-pointer">
                    <input type="checkbox" name="sizes[]" value="' . $size . '" class="hidden peer" ' . $checked . '>
                    <div class="px-4 py-2 border rounded-md text-sm font-semibold 
                                peer-checked:bg-pink-500 peer-checked:text-white peer-checked:border-pink-600 transition-all">
                        ' . $size . '
                    </div>
                </label>';
            }
            ?>
        </div>
    </div>

    <!-- Colors -->
    <div>
        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Colors</h3>
        <div class="flex flex-wrap gap-4">
            <?php
            $colorOptions = ['Red', 'Black', 'White', 'Pink', 'Blue', 'Green', 'Yellow', 'Purple'];
            foreach ($colorOptions as $color) {
                $hex = strtolower($color);
                $checked = (strpos($product['description'], $color) !== false) ? "checked" : "";
                echo '
                <label class="relative cursor-pointer">
                    <input type="checkbox" name="colors[]" value="' . $color . '" class="hidden peer" ' . $checked . '>
                    <div class="w-8 h-8 rounded-full border-2 border-gray-300 peer-checked:border-blue-500"
                         style="background-color:' . $hex . ';"></div>
                </label>';
            }
            ?>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="flex gap-4 pt-6">
        <input type="submit" value="Update Product"
            class="bg-pink-500 text-white px-6 py-2 rounded hover:bg-pink-600 transition-all cursor-pointer">
        <a href="products.php"
            class="text-pink-500 hover:underline self-center">Back to Products</a>
    </div>
</form>

    </div>
</body>
</html>