<?php
$path = "\\\\192.168.254.122\\drive 1\\uploads\\";

echo "<h3>NAS Connectivity & Permissions Test</h3>";
echo "Checking: <b>$path</b><br><br>";

// 1. Check if directory exists
echo "Directory exists: " . (is_dir($path) ? "YES ✅" : "NO ❌") . "<br>";

// 2. Check writable
echo "Writable: " . (is_writable($path) ? "YES ✅" : "NO ❌") . "<br>";

// 3. Try to list directory contents
echo "<br><b>Listing contents:</b><br>";
if ($dir = @scandir($path)) {
    foreach ($dir as $file) {
        echo htmlspecialchars($file) . "<br>";
    }
} else {
    echo "❌ Could not read directory.<br>";
}

// 4. Test write → read → delete
$testFile = $path . "test_php_upload.txt";
$testContent = "Test write at " . date("Y-m-d H:i:s");

echo "<br><b>File operations:</b><br>";
if (@file_put_contents($testFile, $testContent)) {
    echo "✅ Write succeeded: $testFile<br>";

    // Read back
    $readBack = @file_get_contents($testFile);
    if ($readBack === $testContent) {
        echo "✅ Read back succeeded<br>";
    } else {
        echo "❌ Read back failed<br>";
    }

    // Delete test file
    if (@unlink($testFile)) {
        echo "✅ Delete succeeded<br>";
    } else {
        echo "❌ Delete failed<br>";
    }
} else {
    echo "❌ Write failed. (Permissions or path issue)<br>";
}
