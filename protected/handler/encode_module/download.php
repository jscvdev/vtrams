<?php
ob_start();

ini_set('display_errors', 0);
error_reporting(0);

// Base directory (network share or secure path)
$uploadDir = '\\\\192.168.254.122\\drive 1\\uploads\\' . $fileName;

// Allowed MIME types (including legacy and modern Office formats)
$allowedTypes = [
    // PDFs and images
    'application/pdf',
    'image/png',
    'image/jpeg',
    'image/jpg',

    // Microsoft Word
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx

    // Microsoft Excel
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx

    // Microsoft PowerPoint
    'application/vnd.ms-powerpoint', // .ppt
    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
];

// Sanitize filename input
$fileName = isset($_GET['file']) ? basename($_GET['file']) : '';
if (empty($fileName)) {
    header("HTTP/1.1 400 Bad Request");
    echo 'Invalid file name.';
    exit;
}

$filePath = $uploadDir . $fileName;

// Ensure file exists
if (!file_exists($filePath)) {
    header("HTTP/1.1 404 Not Found");
    echo 'File not found.';
    exit;
}

// Get MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Block disallowed MIME types
if (!in_array($mimeType, $allowedTypes)) {
    header("HTTP/1.1 403 Forbidden");
    echo 'File type not allowed.';
    exit;
}

// Common headers
header('Content-Length: ' . filesize($filePath));
header('Pragma: no-cache');
header('Expires: 0');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

// Decide whether to preview or download
if ($mimeType === 'application/pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $fileName . '"');
} else {
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
}

// Output the file
readfile($filePath);
exit;

ob_end_flush();
?>
