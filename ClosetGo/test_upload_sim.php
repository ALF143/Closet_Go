cccccccc deleted this file but the code inside put in other file related ,make sure still working<?php
// Simulate an upload_proof request to product_reserve.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'upload_proof';
$_POST['reservation_group_id'] = 'TEST-RES-123';

$dummyImgPath = sys_get_temp_dir() . '/test_proof.jpg';
file_put_contents($dummyImgPath, base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='));

$_FILES['proof_image'] = [
    'name' => 'test_receipt.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $dummyImgPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($dummyImgPath)
];

// Capture output
ob_start();
include __DIR__ . '/product_reserve.php';
$output = ob_get_clean();

echo "Upload Response: " . $output . "\n";
if (file_exists($dummyImgPath)) unlink($dummyImgPath);
unlink(__FILE__);
