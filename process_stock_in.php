<?php
require 'conn.php';
session_start();

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get Data
    $product_id  = intval($_POST['product_id']);
    $color_id    = intval($_POST['color_id']);
    $size_id     = intval($_POST['size_id']);
    $quantity    = intval($_POST['quantity']);
    $supplier_id = intval($_POST['supplier_id']);
    
    // 🟢Get the prices from the form
    // We use floatval because prices have decimals
    $supplier_price = isset($_POST['supplier_price']) ? floatval($_POST['supplier_price']) : 0.00;
    $selling_price  = isset($_POST['price']) ? floatval($_POST['price']) : 0.00;

    // Validate inputs
    if ($product_id <= 0 || $color_id <= 0 || $size_id <= 0 || $quantity <= 0 || $supplier_id <= 0) {
        die("Invalid input.");
    }

   
    if ($product_id > 0) {
        $updatePrices = $conn->prepare("
            UPDATE products 
            SET supplier_price = ?, price_id = ?, supplier_id = ? 
            WHERE product_id = ?
        ");
        // "d" stands for decimal/double, "i" for integer
        $updatePrices->bind_param("ddii", $supplier_price, $selling_price, $supplier_id, $product_id);
        $updatePrices->execute();
        $updatePrices->close();
    }

    // 2️⃣ Check if stock entry exists for this product + color + size
    $checkStock = $conn->prepare("
        SELECT stock_id, current_qty 
        FROM stock 
        WHERE product_id = ? AND color_id = ? AND size_id = ?
        LIMIT 1
    ");
    $checkStock->bind_param("iii", $product_id, $color_id, $size_id);
    $checkStock->execute();
    $result = $checkStock->get_result();

    if ($row = $result->fetch_assoc()) {
        // 3️⃣ Update existing stock quantity
        $stock_id = $row['stock_id'];
        $new_qty = $row['current_qty'] + $quantity;

        $updateStock = $conn->prepare("
            UPDATE stock 
            SET current_qty = ? 
            WHERE stock_id = ?
        ");
        $updateStock->bind_param("ii", $new_qty, $stock_id);
        $updateStock->execute();
        $updateStock->close();
    } else {
        // 4️⃣ Insert a new stock record for this variant
        $insertStock = $conn->prepare("
            INSERT INTO stock (product_id, color_id, size_id, current_qty) 
            VALUES (?, ?, ?, ?)
        ");
        $insertStock->bind_param("iiii", $product_id, $color_id, $size_id, $quantity);
        $insertStock->execute();
        $stock_id = $insertStock->insert_id;
        $insertStock->close();
    }
    $checkStock->close();

    // 5️⃣ Record the stock-in transaction
    $insertStockIn = $conn->prepare("
        INSERT INTO stock_in (stock_id, supplier_id, quantity, date_added) 
        VALUES (?, ?, ?, NOW())
    ");
    $insertStockIn->bind_param("iii", $stock_id, $supplier_id, $quantity);
    $insertStockIn->execute();
    $insertStockIn->close();

    // 6️⃣ Optional: Update total product-level stock tracking
    $updateProductStock = $conn->prepare("
        UPDATE products 
        SET stocks = COALESCE(stocks, 0) + ? 
        WHERE product_id = ?
    ");
    $updateProductStock->bind_param("ii", $quantity, $product_id);
    $updateProductStock->execute();
    $updateProductStock->close();

    // 7️⃣ Redirect back with success
    header("Location: stock_management.php?success=1");
    exit;
} else {
    die("Invalid request method.");
}
?>