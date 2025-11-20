<?php
require 'conn.php';
header('Content-Type: application/json; charset=utf-8');

$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;
if (!$supplier_id) {
    echo json_encode([]);
    exit;
}

/* 
   GET PRODUCTS THAT BELONG TO THIS SUPPLIER  
   (based on products.supplier_id)
*/

$sql = "
    SELECT product_id, product_name
    FROM products
    WHERE supplier_id = ?
    ORDER BY product_name ASC
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode($products);
    $stmt->close();
    exit;
}

echo json_encode([]);
