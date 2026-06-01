<?php

require 'dbconnection.inc.php';
?>
<?php

try {

    // Check if a file was uploaded
    if (isset($_FILES['fileToUpload'])) {
        $file = $_FILES['fileToUpload'];
        
        // Get file details
        $filename = basename($file['name']);
        $filepath = '../../uploads/' . $filename; // Path where the file will be saved
        $filesize = $file['size'];

        // Validate file upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $file['error']);
        }

        // Create uploads directory if not exists
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        // Move the file to the uploads directory
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Prepare SQL statement to insert file metadata
            $stmt = $pdo->prepare("INSERT INTO uploaded_files (filename, filepath, filesize) VALUES (:filename, :filepath, :filesize)");
            
            // Bind parameters
            $stmt->bindParam(':filename', $filename);
            $stmt->bindParam(':filepath', $filepath);
            $stmt->bindParam(':filesize', $filesize, PDO::PARAM_INT);

            // Execute the statement
            $stmt->execute();

            echo "File uploaded and data stored successfully.";
        } else {
            $temp_dump['upload_err'] = "Upload failed!";
            throw new Exception("Failed to move uploaded file.");
        }
    } else {
        $temp_dump['upload_err'] = "Upload failed!";
        throw new Exception("No file uploaded.");
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    $temp_dump['upload_err'] = "Upload failed!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    $temp_dump['upload_err'] = "Upload failed!";
}
