<?php
require_once 'config.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
        exit;
    }

    $course_id = intval($_GET['course_id']);
    $conn = getDBConnection();

    // Verify course exists
    $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();

    // Get all materials for the course
    $stmt = $conn->prepare("SELECT 
        material_id,
        material_type,
        title,
        description,
        file_name,
        file_size,
        upload_date,
        downloads
        FROM course_materials 
        WHERE course_id = ? 
        ORDER BY 
            CASE material_type
                WHEN 'lecture' THEN 1
                WHEN 'assignment' THEN 2
                WHEN 'notes' THEN 3
                WHEN 'past_paper' THEN 4
                WHEN 'other' THEN 5
            END,
            upload_date DESC");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $materials = [];
    while ($row = $result->fetch_assoc()) {
        $materials[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode([
        'success' => true,
        'materials' => $materials,
        'count' => count($materials)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>