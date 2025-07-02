<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure PHP handles everything in UTF-8 encoding
//mb_internal_encoding("UTF-8");
//mb_http_input("UTF-8");
//mb_http_output("UTF-8");

//session_start();
require_once 'includes/auth.php';
require_login();  // Ensure admin is logged in

function file_get_contents_utf8($fn) {
     $content = file_get_contents($fn);
      return mb_convert_encoding($content, 'UTF-8',
          mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

function clean_csv($fileTmpPath) {
    // Open the file and clean BOM
    $content = file_get_contents_utf8($fileTmpPath);
    
    // Remove BOM (Byte Order Mark)
//    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
//        $content = substr($content, 3);
//    }
    
    // Return the cleaned content
    return $content;
}

// Ensure UTF-8 encoding for everything
mb_internal_encoding("UTF-8");
mb_http_output("UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    echo "File uploaded successfully.<br>";  // Debugging message
    $fileTmpPath = $_FILES['csv_file']['tmp_name'];
    $fileName = $_FILES['csv_file']['name'];
    $fileSize = $_FILES['csv_file']['size'];
    $fileType = $_FILES['csv_file']['type'];
    
    // Validate the file type (CSV)
    if ($fileType !== 'text/csv') {
        echo 'Invalid file type. Please upload a CSV file.';
        exit;
    }

    echo "File type is valid.<br>";  // Debugging message

    // Parse the CSV
    $csvContent = clean_csv($fileTmpPath);
    $csv = array_map('str_getcsv', explode("\n", $csvContent));
    
    echo "CSV file processed.<br>";  // Debugging message

    // Now process the CSV data
    require_once 'process_csv.php';
    process_csv($csv);

    echo 'CSV file processed successfully!';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload CSV - HOPT</title>
</head>
<body>
    <h1>Upload Store CSV</h1>
    <form action="upload_csv.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="csv_file" accept=".csv" required><br>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
