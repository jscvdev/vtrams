<?php
// Define the UNC path to the file on the NAS device

// $uncPath = '\\\\192.168.254.122\\drive 1\\uploads\\test.png'; // Replace with your UNC path
// $uncPath = '\\\\192.168.254.122\\drive 1\\uploads\\'.$_GET['file']; // Replace with your UNC path
$file = basename($_GET['file']); // This ensures no directory traversal.
$uncPath = 'C:\\uploads\\' . $file; // Local file path'

// Check if the file exists
if (file_exists($uncPath)) {
    // Set headers for file download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($uncPath) . '"');
    header('Content-Length: ' . filesize($uncPath));

    // Clear output buffer to avoid any unwanted data before sending the file
    ob_clean();
    flush();

    // Read the file and send it to the browser
    readfile($uncPath);

    // Exit to make sure no other content is sent after the file download
    exit;
} else {
    // If file does not exist, show an error message
    echo "File not found!";
}
?>
