<?php
// process_edit_stock.php
session_start();
require 'conn.php';

// 1. Check if Admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// 2. Validate POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get values from the form
    $stock_id       = intval($_POST['stock_id']);
    $product_id     = intval($_POST['product_id']); // Essential for updating prices
    $supplier_id    = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL;
    $color_id       = !empty($_POST['color_id']) ? intval($_POST['color_id']) : NULL;
    $size_id        = !empty($_POST['size_id']) ? intval($_POST['size_id']) : NULL;
    $new_quantity   = intval($_POST['new_quantity']);
    
    // Get the new prices
    $supplier_price = isset($_POST['supplier_price']) ? floatval($_POST['supplier_price']) : 0.00;
    $seller_price   = isset($_POST['price']) ? floatval($_POST['price']) : 0.00;

    // Start Transaction to ensure both tables update, or neither does
    $conn->begin_transaction();

    try {
        // 🔹 STEP 1: Update the STOCK table (Quantity, Color, Size)
        $stock_sql = "UPDATE stock 
                      SET current_qty = ?, 
                          color_id = ?, 
                          size_id = ?, 
                          product_id = ? 
                      WHERE stock_id = ?";
        
        $stmt = $conn->prepare($stock_sql);
        $stmt->bind_param("iiiii", $new_quantity, $color_id, $size_id, $product_id, $stock_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating stock: " . $stmt->error);
        }
        $stmt->close();

        // 🔹 STEP 2: Update the PRODUCTS table (Supplier Price, Seller Price)
        // Note: Based on your database schema, 'price_id' seems to hold the selling price value
        $product_sql = "UPDATE products 
                        SET supplier_price = ?, 
                            price_id = ?, 
                            supplier_id = ? 
                        WHERE product_id = ?";
        
        $stmt2 = $conn->prepare($product_sql);
        $stmt2->bind_param("ddii", $supplier_price, $seller_price, $supplier_id, $product_id);
        
        if (!$stmt2->execute()) {
            throw new Exception("Error updating product prices: " . $stmt2->error);
        }
        $stmt2->close();

        // 🔹 STEP 3: Log the Action (Optional but recommended)
        $admin_id = $_SESSION['admin_id'];
        $log_action = "Updated Stock ID: $stock_id and Prices for Product ID: $product_id";
        $log_sql = "INSERT INTO system_logs (user_id, action) VALUES (?, ?)";
        $stmt_log = $conn->prepare($log_sql);
        $stmt_log->bind_param("is", $admin_id, $log_action);
        $stmt_log->execute();
        $stmt_log->close();

        // Commit changes
        $conn->commit();

        // Redirect back with success message
        header("Location: stock_management.php?status=success&msg=Stock and Prices Updated");
        exit;

    } catch (Exception $e) {
        // Rollback changes if anything failed
        $conn->rollback();
        header("Location: stock_management.php?status=error&msg=" . urlencode($e->getMessage()));
        exit;
    }

} else {
    // If accessed directly without POST
    header("Location: stock_management.php");
    exit;
}
?>