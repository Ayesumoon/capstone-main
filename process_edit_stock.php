<?php
// process_edit_stock.php
session_start();
require 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize Inputs
    $stock_id    = intval($_POST['stock_id'] ?? 0);
    $new_qty     = intval($_POST['new_quantity'] ?? 0);
    // Use NULL if empty/zero to properly handle database defaults
    $color_id    = !empty($_POST['color_id']) ? intval($_POST['color_id']) : NULL;
    $size_id     = !empty($_POST['size_id']) ? intval($_POST['size_id']) : NULL;
    $supplier_id = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : NULL;
    $product_id  = !empty($_POST['product_id']) ? intval($_POST['product_id']) : NULL; 
    
    $admin_id = $_SESSION['admin_id'] ?? 0;

    // 2. Validate Required Fields
    if ($stock_id > 0 && $new_qty >= 0 && $product_id) {
        
        // 3. Update Stock Table (Qty, Product, Color, Size)
        $query = "UPDATE stock SET current_qty = ?, product_id = ?, color_id = ?, size_id = ? WHERE stock_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiiii", $new_qty, $product_id, $color_id, $size_id, $stock_id);

        if ($stmt->execute()) {
            
            // 4. Update Supplier (Optional: Updates the most recent stock_in record)
            if ($supplier_id) {
                $supQuery = "UPDATE stock_in SET supplier_id = ? WHERE stock_id = ? ORDER BY date_added DESC LIMIT 1";
                $supStmt = $conn->prepare($supQuery);
                if ($supStmt) {
                    $supStmt->bind_param("ii", $supplier_id, $stock_id);
                    $supStmt->execute();
                    $supStmt->close();
                }
            }

            // 5. System Log (Wrapped in try-catch to prevent fatal errors)
            try {
                $logDesc = "Updated Stock ID $stock_id. ProductID: $product_id, Qty: $new_qty";
                // Ensure 'description' column exists in DB, or change this word to match your DB
                $logSql = "INSERT INTO system_logs (user_id, action, description, created_at) VALUES (?, 'Update Stock Details', ?, NOW())";
                
                $logStmt = $conn->prepare($logSql);
                if ($logStmt) {
                    $logStmt->bind_param("is", $admin_id, $logDesc);
                    $logStmt->execute();
                    $logStmt->close();
                }
            } catch (Exception $e) {
                // Silently fail logging so the user workflow isn't interrupted
                // error_log("Logging failed: " . $e->getMessage()); 
            }

            $_SESSION['message'] = "Stock details updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update stock: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Invalid data provided. Product ID is required.";
    }
}

header("Location: stock_management.php");
exit;
?>