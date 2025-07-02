<?php
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start session and authenticate
session_start();
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_login(); // Only logged-in users can rate

// Set headers for security and CORS (optional)
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Validate and sanitize input
$storeId = isset($_POST['store_id']) ? (int) $_POST['store_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;

// Get logged-in user ID (fallback to 1 for testing/admin)
$userId = $_SESSION['user_id'] ?? 1;

if ($storeId <= 0 || $rating < 1 || $rating > 3) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid store ID or rating.']);
    exit;
}

try {
    // Ensure there's a unique constraint on (user_id, store_id) in ratings table
    $stmt = $pdo->prepare("
        INSERT INTO ratings (user_id, store_id, rating)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating)
    ");
    $stmt->execute([$userId, $storeId, $rating]);

    echo json_encode(['success' => true, 'message' => 'Rating submitted.']);
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
