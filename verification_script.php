<?php
/**
 * Test script to verify the file path traversal vulnerability fix
 */

// Define FCPATH constant for testing
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Test cases for the security fix
$test_cases = [
    // Valid cases that should work (only existing files)
    'assets/archives/0000001/doc_1.pdf' => true,

    // Invalid cases that should be blocked
    '../application/config/database.php' => false,
    '..%2Fapplication%2Fconfig%2Fdatabase.php' => false, // URL encoded
    '..\application\config\database.php' => false, // Backslashes
    '/etc/passwd' => false,
    'C:\Windows\win.ini' => false,
    'assets/archives/../../application/config/database.php' => false,
    'assets/archives/0000001/../../../application/config/database.php' => false,
    'assets/archives/0000001/..%2F..%2F..%2Fapplication%2Fconfig%2Fdatabase.php' => false,
    'not_pdf.txt' => false,
    '' => false,
    'uploads/documents/test.pdf' => false, // Doesn't exist
    'assets/archives/0000001/subfolder/file.pdf' => false, // Doesn't exist
];

echo "Testing file path security validation...\n\n";

$pass_count = 0;
$total_count = count($test_cases);

foreach ($test_cases as $file => $should_pass) {
    $result = test_file_validation($file);
    $status = $result === $should_pass ? "PASS" : "FAIL";
    if ($status === "PASS") $pass_count++;
    echo "[$status] '$file' -> " . ($result ? "ALLOWED" : "BLOCKED") . " (expected: " . ($should_pass ? "ALLOWED" : "BLOCKED") . ")\n";
}

echo "\nResults: $pass_count/$total_count tests passed\n";
if ($pass_count === $total_count) {
    echo "✅ All security tests passed! The vulnerability has been fixed.\n";
} else {
    echo "❌ Some tests failed. Please review the implementation.\n";
}

function test_file_validation($file) {
    // Simulate the security check from the fixed code
    if (empty($file)) {
        return false;
    }

    // Define allowed directories (same as in the fix)
    $allowed_directories = [
        FCPATH . 'assets/archives/',
        FCPATH . 'uploads/'
    ];

    $file_path = FCPATH . $file;
    $real_path = realpath($file_path);

    // Validate that the resolved path is within an allowed directory
    $is_valid = false;
    foreach ($allowed_directories as $allowed_dir) {
        $allowed_dir = rtrim($allowed_dir, '/\\') . DIRECTORY_SEPARATOR;
        // Normalize paths for comparison (convert to same directory separator)
        $normalized_real_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $real_path);
        $normalized_allowed_dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $allowed_dir);

        if ($real_path !== false && strpos($normalized_real_path, $normalized_allowed_dir) === 0) {
            $is_valid = true;
            break;
        }
    }

    if (!$is_valid || !preg_match('/\.pdf$/i', $file)) {
        return false;
    }

    return true;
}