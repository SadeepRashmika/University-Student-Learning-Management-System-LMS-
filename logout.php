<?php
require_once 'config.php';

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'logout', 'User logged out')");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $conn->close();
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();
?>