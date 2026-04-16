<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/sheets.php';
require_once __DIR__ . '/../lib/drive.php';

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Allow both customers and admins
portalSessionStart();
$isAdmin    = isLoggedInAsAdmin();
$isCustomer = isLoggedInAsCustomer();

if (!$isAdmin && !$isCustomer) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed.'], 405);
}

$orderId  = trim($_POST['order_id'] ?? '');
$caption  = trim($_POST['caption']  ?? '');
$uploader = $isAdmin ? 'admin' : 'customer';

if (empty($orderId)) {
    jsonResponse(['success' => false, 'error' => 'Order ID required.']);
}

// If customer, verify the order belongs to them
if ($isCustomer && getCurrentOrderId() !== $orderId) {
    jsonResponse(['success' => false, 'error' => 'Access denied.'], 403);
}

if (empty($_FILES['file'])) {
    jsonResponse(['success' => false, 'error' => 'No file uploaded.']);
}

$file     = $_FILES['file'];
$origName = basename($file['name']);
$mimeType = mime_content_type($file['tmp_name']);

// Fallback: if mime is ambiguous, trust the extension for PDFs
if (in_array($mimeType, ['application/octet-stream', 'text/plain', '']) && preg_match('/\.pdf$/i', $origName)) {
    $mimeType = 'application/pdf';
}

// Only allow images, videos, and PDFs
$allowed = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/quicktime','video/x-msvideo','application/pdf'];
if (!in_array($mimeType, $allowed)) {
    jsonResponse(['success' => false, 'error' => 'File type not allowed. Use JPG, PNG, GIF, WEBP, MP4, MOV, or PDF.']);
}

$maxSize = 50 * 1024 * 1024; // 50MB
if ($file['size'] > $maxSize) {
    jsonResponse(['success' => false, 'error' => 'File too large. Max 50MB.']);
}

$mediaId  = uniqid('media_', true);
$filename = $mediaId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);

// Save locally
$driveFileId  = '';
$thumbnailUrl = '';
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
$localPath = $uploadDir . $filename;
if (move_uploaded_file($file['tmp_name'], $localPath)) {
    $thumbnailUrl = '/uploads/' . $filename;
}

$ok = addMediaRecord([
    'media_id'      => $mediaId,
    'order_id'      => $orderId,
    'uploader'      => $uploader,
    'filename'      => $origName,
    'drive_file_id' => $driveFileId,
    'thumbnail_url' => $thumbnailUrl,
    'caption'       => $caption,
]);

if (!$ok) {
    jsonResponse(['success' => false, 'error' => 'Could not save media record. Check Google Sheets configuration.']);
}

jsonResponse([
    'success'       => true,
    'media_id'      => $mediaId,
    'filename'      => $origName,
    'drive_file_id' => $driveFileId,
    'thumbnail_url' => $thumbnailUrl,
    'uploader'      => $uploader,
    'caption'       => $caption,
]);
