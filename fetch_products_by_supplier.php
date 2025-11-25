<?php
// fetch_products_by_supplier.php
require 'conn.php';
header('Content-Type: application/json');

if (!isset($_GET['supplier_id'])) {
    echo json_encode([]);
    exit;
}

$supplier_id = intval($_GET['supplier_id']);

$stmt = $conn->prepare("SELECT product_id, product_name FROM products WHERE supplier_id = ? ORDER BY product_name ASC");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);
?>