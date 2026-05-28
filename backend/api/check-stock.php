<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/db.php';
require_once '../includes/catalog_functions.php';

$catalog = new Catalog();
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id) {
    $availability = $catalog->checkAvailability($product_id);
    echo json_encode([
        'success' => true,
        'product_id' => $product_id,
        'available' => $availability['available'],
        'can_order' => $availability['can_order'],
        'max_order' => $availability['max_order']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Product ID required'
    ]);
}
?>