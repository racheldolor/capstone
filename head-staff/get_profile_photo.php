<?php
session_start();

// Authentication check
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role'], ['head', 'staff', 'central', 'admin'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get the file parameter
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    exit('No file specified');
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);

// Build the file path
$file_path = __DIR__ . '/../uploads/' . $filename;

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    exit('File not found');
}

// Get file info
$file_info = pathinfo($file_path);
$extension = strtolower($file_info['extension']);

// Set appropriate content type
$content_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

if (!isset($content_types[$extension])) {
    http_response_code(400);
    exit('Invalid file type');
}

// Set headers
header('Content-Type: ' . $content_types[$extension]);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Output the file
readfile($file_path);
?>