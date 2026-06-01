<?php
// Start output buffering to prevent any accidental output before headers
ob_start();

ini_set('display_errors', 0);  // Disable error display
error_reporting(0);            // Disable error reporting

// Get the filename from the query string (sanitize to prevent directory traversal)
$fileName = isset($_GET['file']) ? basename($_GET['file']) : '';

// File path on the server (make sure this is correct and accessible)
$filePath = '\\\\192.168.254.122\\drive 1\\uploads\\' . $fileName;

if (file_exists($filePath)) {
    // Get file size
    $fileSize = filesize($filePath);

    // Set headers for the response (don't trigger a download)
    header('Content-Type: application/octet-stream');  // We still use binary MIME type
    header('Content-Length: ' . $fileSize);           // Set the content length for streaming
    header('Pragma: no-cache');
    header('Expires: 0');
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

    // Output the file as binary data (no download trigger)
    readfile($filePath);
    exit; // Ensure the script exits after sending the file

} else {
    // If file doesn't exist, return a 404 error or message
    header("HTTP/1.1 404 Not Found");
    echo 'File not found.';
    exit;
}

ob_end_flush();
?>
