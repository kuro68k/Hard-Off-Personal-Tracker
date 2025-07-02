<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 1;
$store_id = (int) ($_POST['store_id'] ?? 0);
$timestamp = $_POST['timestamp'] ?? null;

if (!$store_id || !$timestamp) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {
    $dt = new DateTime($timestamp);
    $formatted = $dt->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO visits (user_id, store_id, visit_date) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $store_id, $formatted]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
