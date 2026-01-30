<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$message = '';
$error = '';

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_course') {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $description = trim($_POST['description']);
    $credits = intval($_POST['credits']);
    $instructor_name = trim($_POST['instructor_name']);
    $semester = trim($_POST['semester']);
    $year = intval($_POST['year']);
    
    if (empty($course_code) || empty($course_name)) {
        $error = "Course code and name are required!";
    } else {
        // Check if course code already exists
        $stmt = $conn->prepare("SELECT course_id FROM courses WHERE course_code = ?");
        $stmt->bind_param("s", $course_code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Course code already exists!";
        } else {
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, credits, instructor_name, semester, year, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssissii", $course_code, $course_name, $description, $credits, $instructor_name, $semester, $year, $user_id);
            
            if ($stmt->execute()) {
                $new_course_id = $stmt->insert_id;
                $stmt->close();
                
                // Auto-enroll the creator
                $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')");
                $stmt->bind_param("ii", $user_id, $new_course_id);
                $stmt->execute();
                
                $message = "Course created successfully!";
                logActivity($user_id, 'create_course', "Created course: $course_name ($course_code)");
            } else {
                $error = "Error creating course!";
            }
        }
        $stmt->close();
    }
}

// Handle course update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_course') {
    $course_id = intval($_POST['course_id']);
    $course_name = trim($_POST['course_name']);
    $description = trim($_POST['description']);
    $credits = intval($_POST['credits']);
    $instructor_name = trim($_POST['instructor_name']);
    $semester = trim($_POST['semester']);
    $year = intval($_POST['year']);
    
    $stmt = $conn->prepare("UPDATE courses SET course_name = ?, description = ?, credits = ?, instructor_name = ?, semester = ?, year = ? WHERE course_id = ?");
    $stmt->bind_param("ssissii", $course_name, $description, $credits, $instructor_name, $semester, $year, $course_id);
    
    if ($stmt->execute()) {
        $message = "Course updated successfully!";
    } else {
        $error = "Error updating course!";
    }
    $stmt->close();
}

// Handle course deletion
if (isset($_GET['delete_course']) && is_numeric($_GET['delete_course'])) {
    $course_id = intval($_GET['delete_course']);
    
    $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    
    if ($stmt->execute()) {
        $message = "Course deleted successfully!";
    } else {
        $error = "Error deleting course!";
    }
    $stmt->close();
}

// Handle material upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_material') {
    $course_id = intval($_POST['course_id']);
    $material_type = $_POST['material_type'];
    $title = trim($_POST['material_title']);
    $description = trim($_POST['material_description']);
    
    if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
        // Validate file
        $validation = validateUploadedFile($_FILES['material_file']);
        
        if (!$validation['valid']) {
            $error = $validation['message'] . " (Extension: " . strtolower(pathinfo($_FILES['material_file']['name'], PATHINFO_EXTENSION)) . ")";
        } else {
            $upload_dir = UPLOAD_DIR . 'courses/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_name = $_FILES['material_file']['name'];
            $file_size = $_FILES['material_file']['size'];
            $file_tmp = $_FILES['material_file']['tmp_name'];
            
            // Generate unique filename
            $unique_name = generateUniqueFilename($file_name);
            $file_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                $stmt = $conn->prepare("INSERT INTO course_materials (course_id, material_type, title, description, file_name, file_path, file_size, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssii", $course_id, $material_type, $title, $description, $file_name, $file_path, $file_size, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Material uploaded successfully!";
                    logActivity($user_id, 'upload_material', "Uploaded material: $title for course ID: $course_id");
                } else {
                    $error = "Error saving material to database!";
                }
                $stmt->close();
            } else {
                $error = "Error uploading file!";
            }
        }
    } else {
        // Better error messages for upload failures
        if (isset($_FILES['material_file'])) {
            $upload_error = $_FILES['material_file']['error'];
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum upload size',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum upload size',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $error = $error_messages[$upload_error] ?? "Upload error code: $upload_error";
        } else {
            $error = "No file selected!";
        }
    }
}

// Handle material update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_material') {
    $material_id = intval($_POST['material_id']);
    $material_type = $_POST['material_type'];
    $title = trim($_POST['material_title']);
    $description = trim($_POST['material_description']);
    
    $stmt = $conn->prepare("UPDATE course_materials SET material_type = ?, title = ?, description = ? WHERE material_id = ?");
    $stmt->bind_param("sssi", $material_type, $title, $description, $material_id);
    
    if ($stmt->execute()) {
        $message = "Material updated successfully!";
    } else {
        $error = "Error updating material!";
    }
    $stmt->close();
}

// Handle material deletion
if (isset($_GET['delete_material']) && is_numeric($_GET['delete_material'])) {
    $material_id = intval($_GET['delete_material']);
    
    // Get file path before deletion
    $stmt = $conn->prepare("SELECT file_path FROM course_materials WHERE material_id = ?");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $file_path = $row['file_path'];
        
        // Delete from database
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM course_materials WHERE material_id = ?");
        $stmt->bind_param("i", $material_id);
        
        if ($stmt->execute()) {
            // Delete physical file
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $message = "Material deleted successfully!";
        } else {
            $error = "Error deleting material!";
        }
    }
    $stmt->close();
}

// Handle material viewing (open in browser)
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $material_id = intval($_GET['view']);
    
    $stmt = $conn->prepare("SELECT * FROM course_materials WHERE material_id = ?");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($material = $result->fetch_assoc()) {
        $file_path = $material['file_path'];
        
        if (file_exists($file_path)) {
            $file_ext = strtolower(pathinfo($material['file_name'], PATHINFO_EXTENSION));
            
            // Set appropriate content type for viewing
            $content_types = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'txt' => 'text/plain',
                'html' => 'text/html',
                'htm' => 'text/html',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ];
            
            $content_type = $content_types[$file_ext] ?? 'application/octet-stream';
            
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . basename($material['file_name']) . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: public, max-age=3600');
            
            readfile($file_path);
            exit;
        }
    }
    $stmt->close();
}

// Handle material viewing
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $material_id = intval($_GET['view']);
    
    $stmt = $conn->prepare("SELECT * FROM course_materials WHERE material_id = ?");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($material = $result->fetch_assoc()) {
        $file_path = $material['file_path'];
        if (file_exists($file_path)) {
            $file_ext = strtolower(pathinfo($material['file_name'], PATHINFO_EXTENSION));
            
            // Set appropriate content type for viewing
            $content_types = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'txt' => 'text/plain',
                'html' => 'text/html',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
            ];
            
            $content_type = $content_types[$file_ext] ?? 'application/octet-stream';
            
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . basename($material['file_name']) . '"');
            header('Content-Length: ' . filesize($file_path));
            header('Cache-Control: public, max-age=3600');
            readfile($file_path);
            exit;
        }
    }
    $stmt->close();
}

// Handle material download tracking
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $material_id = intval($_GET['download']);
    
    $stmt = $conn->prepare("SELECT * FROM course_materials WHERE material_id = ?");
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($material = $result->fetch_assoc()) {
        // Increment download counter
        $update_stmt = $conn->prepare("UPDATE course_materials SET downloads = downloads + 1 WHERE material_id = ?");
        $update_stmt->bind_param("i", $material_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Serve file for download
        $file_path = $material['file_path'];
        if (file_exists($file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($material['file_name']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        }
    }
    $stmt->close();
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_semester = isset($_GET['semester']) ? $_GET['semester'] : '';

// Get enrolled courses with material count
$sql = "SELECT c.*, 
        COUNT(DISTINCT cm.material_id) as material_count,
        e.enrollment_date, e.grade, e.gpa,
        (SELECT COUNT(*) FROM course_materials WHERE course_id = c.course_id AND material_type = 'lecture') as lecture_count,
        (SELECT COUNT(*) FROM course_materials WHERE course_id = c.course_id AND material_type = 'assignment') as assignment_count
        FROM courses c 
        INNER JOIN enrollments e ON c.course_id = e.course_id 
        LEFT JOIN course_materials cm ON c.course_id = cm.course_id
        WHERE e.student_id = ? AND e.status = 'enrolled'";

$params = [$user_id];
$types = "i";

if ($search) {
    $sql .= " AND (c.course_name LIKE ? OR c.course_code LIKE ? OR c.instructor_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($filter_semester) {
    $sql .= " AND c.semester = ?";
    $params[] = $filter_semester;
    $types .= "s";
}

$sql .= " GROUP BY c.course_id ORDER BY e.enrollment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Get available semesters for filter
$stmt = $conn->prepare("SELECT DISTINCT c.semester FROM courses c 
    INNER JOIN enrollments e ON c.course_id = e.course_id 
    WHERE e.student_id = ? ORDER BY c.semester DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$semesters = [];
while ($row = $result->fetch_assoc()) {
    $semesters[] = $row['semester'];
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - University LMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #8b5cf6;
            --accent: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --dark-lighter: #334155;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            color: var(--text);
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 280px;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            overflow-y: auto;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.5);
            border-radius: 3px;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            background: rgba(15, 23, 42, 0.8);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header .logo {
            font-size: 2rem;
            filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.5));
        }
        
        .sidebar-header .title {
            font-size: 1.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-menu {
            padding: 1.5rem 0;
        }
        
        .nav-item {
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            margin: 0.25rem 0.75rem;
            border-radius: 12px;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 0 3px 3px 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .nav-item:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--text);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: rgba(99, 102, 241, 0.15);
            color: var(--text);
            font-weight: 600;
        }
        
        .nav-item.active::before {
            opacity: 1;
        }
        
        .nav-item .icon {
            font-size: 1.3rem;
            width: 24px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        <!-- Top Bar -->
        .top-bar {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .top-bar h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .btn-create {
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
        
        /* Search and Filter Bar */
        .filter-bar {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.8);
        }
        
        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.2rem;
        }
        
        .filter-select {
            padding: 0.875rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 150px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.8);
        }
        
        .btn-filter {
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
        
        .btn-clear {
            padding: 0.875rem 1.5rem;
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-clear:hover {
            background: rgba(239, 68, 68, 0.25);
            transform: translateY(-2px);
        }
        
        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .course-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .course-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
        }
        
        .course-header {
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.5);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .course-code {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary);
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        
        .course-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }
        
        .course-instructor {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .course-body {
            padding: 1.5rem;
        }
        
        .course-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1.25rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        
        .stat-item {
            background: rgba(15, 23, 42, 0.5);
            padding: 0.75rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .stat-icon {
            font-size: 1.5rem;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        
        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }
        
        .course-footer {
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.3);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 0.75rem;
        }
        
        .btn-view {
            flex: 1;
            padding: 0.75rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
            overflow-y: auto;
            padding: 2rem;
        }
        
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--dark-light);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            padding: 2rem;
            background: rgba(15, 23, 42, 0.8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
        }
        
        .modal-close {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            background: rgba(239, 68, 68, 0.25);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .materials-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .material-item {
            background: rgba(15, 23, 42, 0.5);
            padding: 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        
        .material-item:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateX(5px);
        }
        
        .material-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(99, 102, 241, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .material-info {
            flex: 1;
        }
        
        .material-title {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        
        .material-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .material-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
            flex-wrap: wrap;
        }
        
        .material-actions a,
        .material-actions button {
            white-space: nowrap;
        }
        
        .btn-download {
            padding: 0.625rem 1.25rem;
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-download:hover {
            background: rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-view {
            padding: 0.625rem 1.25rem;
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-view:hover {
            background: rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-view-file {
            padding: 0.625rem 1.25rem;
            background: rgba(99, 102, 241, 0.2);
            color: #818cf8;
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-view-file:hover {
            background: rgba(99, 102, 241, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-edit {
            padding: 0.625rem 1.25rem;
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-edit:hover {
            background: rgba(245, 158, 11, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-delete {
            padding: 0.625rem 1.25rem;
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.3);
            transform: translateY(-2px);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            color: var(--text);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text);
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.8);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-file {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: var(--text);
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-file:hover {
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.8);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }
        
        .btn-secondary {
            width: 100%;
            padding: 1rem;
            background: rgba(100, 116, 139, 0.2);
            color: var(--text-muted);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }
        
        .btn-secondary:hover {
            background: rgba(100, 116, 139, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-upload-material {
            padding: 0.875rem 1.5rem;
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-upload-material:hover {
            background: rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
        }
        
        .course-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-edit-course,
        .btn-delete-course {
            flex: 1;
            padding: 0.625rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-edit-course {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .btn-edit-course:hover {
            background: rgba(245, 158, 11, 0.3);
        }
        
        .btn-delete-course {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .btn-delete-course:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            font-size: 1rem;
        }
        
        .no-materials {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            background: rgba(15, 23, 42, 0.3);
            border-radius: 12px;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }
        
        .menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 1.3rem;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .menu-toggle:hover {
            background: rgba(99, 102, 241, 0.2);
            transform: translateY(-2px);
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .menu-toggle {
                display: block;
            }
            
            .courses-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .search-box, .filter-select {
                width: 100%;
            }
            
            .material-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .material-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .material-actions a,
            .material-actions button {
                flex: 1;
                min-width: auto;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <span class="logo">🎓</span>
            <span class="title">University LMS</span>
        </div>
        <nav class="nav-menu">
            <a href="student_dashboard.php" class="nav-item">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="student_courses.php" class="nav-item active">
                <span class="icon">📚</span>
                <span>My Courses</span>
            </a>
            <a href="Timetable.php" class="nav-item">
                <span class="icon">📅</span>
                <span>Timetable</span>
            </a>
            <a href="student_materials.php" class="nav-item">
                <span class="icon">📄</span>
                <span>Course Materials</span>
            </a>
            <a href="student_grades.php" class="nav-item">
                <span class="icon">📈</span>
                <span>Grades & GPA</span>
            </a>
            <a href="student_profile.php" class="nav-item">
                <span class="icon">👤</span>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="nav-item">
                <span class="icon">🚪</span>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="top-bar">
            <h1>📚 My Courses</h1>
            <button class="btn-create" onclick="openCreateCourseModal()">
                ➕ Create New Course
            </button>
        </div>
        
        <div class="filter-bar">
            <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <select name="semester" class="filter-select">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo htmlspecialchars($sem); ?>" <?php echo $filter_semester === $sem ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($sem); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-filter">Apply Filters</button>
                <?php if ($search || $filter_semester): ?>
                    <a href="student_courses.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($courses)): ?>
            <div class="no-data">
                <h2>No courses found</h2>
                <p>You haven't enrolled in any courses yet.</p>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-instructor">
                                👨‍🏫 <?php echo htmlspecialchars($course['instructor_name'] ?: 'TBA'); ?>
                            </div>
                        </div>
                        
                        <div class="course-body">
                            <?php if ($course['description']): ?>
                                <div class="course-description">
                                    <?php echo htmlspecialchars($course['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="course-stats">
                                <div class="stat-item">
                                    <div class="stat-icon">📄</div>
                                    <div class="stat-content">
                                        <div class="stat-label">Total Materials</div>
                                        <div class="stat-value"><?php echo $course['material_count']; ?></div>
                                    </div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">📖</div>
                                    <div class="stat-content">
                                        <div class="stat-label">Lectures</div>
                                        <div class="stat-value"><?php echo $course['lecture_count']; ?></div>
                                    </div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">💳</div>
                                    <div class="stat-content">
                                        <div class="stat-label">Credits</div>
                                        <div class="stat-value"><?php echo $course['credits']; ?></div>
                                    </div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">📅</div>
                                    <div class="stat-content">
                                        <div class="stat-label">Semester</div>
                                        <div class="stat-value"><?php echo htmlspecialchars($course['semester'] ?: 'N/A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="course-footer">
                            <a href="#" class="btn-view" onclick="viewCourseMaterials(<?php echo $course['course_id']; ?>, '<?php echo addslashes($course['course_name']); ?>'); return false;">
                                View Materials
                            </a>
                        </div>
                        
                        <div class="course-actions" style="padding: 0 1.5rem 1.5rem;">
                            <button class="btn-edit-course" onclick="openEditCourseModal(<?php echo $course['course_id']; ?>, '<?php echo addslashes($course['course_code']); ?>', '<?php echo addslashes($course['course_name']); ?>', '<?php echo addslashes($course['description']); ?>', <?php echo $course['credits']; ?>, '<?php echo addslashes($course['instructor_name']); ?>', '<?php echo addslashes($course['semester']); ?>', <?php echo $course['year']; ?>)">
                                ✏️ Edit Course
                            </button>
                            <a href="?delete_course=<?php echo $course['course_id']; ?>" class="btn-delete-course" onclick="return confirm('Are you sure you want to delete this course? All materials will be deleted too!')">
                                🗑️ Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Materials Modal -->
    <div id="materialsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Course Materials</div>
                <button class="modal-close" onclick="closeModal('materialsModal')">×</button>
            </div>
            <div class="modal-body" id="modalBody">
                <button class="btn-upload-material" onclick="openUploadMaterialModal()">
                    ➕ Upload New Material
                </button>
                <div id="materialsContent">
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        Loading materials...
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Course Modal -->
    <div id="createCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Create New Course</div>
                <button class="modal-close" onclick="closeModal('createCourseModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create_course">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Course Code *</label>
                            <input type="text" name="course_code" class="form-input" required placeholder="e.g., CS101">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Credits</label>
                            <input type="number" name="credits" class="form-input" value="3" min="1" max="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Name *</label>
                        <input type="text" name="course_name" class="form-input" required placeholder="e.g., Introduction to Computer Science">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" placeholder="Course description..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Instructor Name</label>
                        <input type="text" name="instructor_name" class="form-input" placeholder="e.g., Dr. John Smith">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="">Select Semester</option>
                                <option value="1 year 1 semester">1 year 1 semester</option>
                                <option value="1 year 2 semester">1 year 2 semester</option>
                                <option value="2 year 1 semester">2 year 1 semester</option>
                                <option value="2 year 2 semester">2 year 2 semester</option>
                                <option value="3 year 1 semester">3 year 1 semester</option>
                                <option value="3 year 2 semester">3 year 2 semester</option>
                                <option value="4 year 1 semester">4 year 1 semester</option>
                                <option value="4 year 2 semester">4 year 2 semester</option>
                                
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <input type="number" name="year" class="form-input" value="2025" min="2020" max="2030">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">Create Course</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('createCourseModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Course Modal -->
    <div id="editCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Edit Course</div>
                <button class="modal-close" onclick="closeModal('editCourseModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_course">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Course Code (Read Only)</label>
                            <input type="text" id="edit_course_code" class="form-input" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Credits</label>
                            <input type="number" name="credits" id="edit_credits" class="form-input" min="1" max="10">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Name *</label>
                        <input type="text" name="course_name" id="edit_course_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-textarea"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Instructor Name</label>
                        <input type="text" name="instructor_name" id="edit_instructor_name" class="form-input">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Semester</label>
                            <select name="semester" id="edit_semester" class="form-select">
                                <option value="">Select Semester</option>

                                <option value="1 year 1 semester">1 year 1 semester</option>
                                <option value="1 year 2 semester">1 year 2 semester</option>
                                <option value="2 year 1 semester">2 year 1 semester</option>    
                                <option value="2 year 2 semester">2 year 2 semester</option>
                                <option value="3 year 1 semester">3 year 1 semester</option>    
                                <option value="3 year 2 semester">3 year 2 semester</option>
                                <option value="4 year 1 semester">4 year 1 semester</option>    
                                <option value="4 year 2 semester">4 year 2 semester</option>

                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Year</label>
                            <input type="number" name="year" id="edit_year" class="form-input" min="2020" max="2030">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-submit">Update Course</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('editCourseModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Upload Material Modal -->
    <div id="uploadMaterialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Upload Course Material</div>
                <button class="modal-close" onclick="closeModal('uploadMaterialModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_material">
                    <input type="hidden" name="course_id" id="upload_course_id">
                    
                    <div class="form-group">
                        <label class="form-label">Material Type *</label>
                        <select name="material_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="lecture">📖 Lecture</option>
                            <option value="assignment">📝 Assignment</option>
                            <option value="notes">📓 Notes</option>
                            <option value="past_paper">📋 Past Paper</option>
                            <option value="other">📦 Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" name="material_title" class="form-input" required placeholder="e.g., Week 1 Lecture Notes">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="material_description" class="form-textarea" placeholder="Brief description of the material..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Upload File *</label>
                        <input type="file" name="material_file" class="form-file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.txt,.jpg,.jpeg,.png,.gif" required>
                        <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Accepted formats: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, ZIP, TXT, JPG, JPEG, PNG, GIF (Max 10MB)</small>
                    </div>
                    
                    <button type="submit" class="btn-submit">Upload Material</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('uploadMaterialModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Material Modal -->
    <div id="editMaterialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Edit Material</div>
                <button class="modal-close" onclick="closeModal('editMaterialModal')">×</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_material">
                    <input type="hidden" name="material_id" id="edit_material_id">
                    
                    <div class="form-group">
                        <label class="form-label">Material Type *</label>
                        <select name="material_type" id="edit_material_type" class="form-select" required>
                            <option value="lecture">📖 Lecture</option>
                            <option value="assignment">📝 Assignment</option>
                            <option value="notes">📓 Notes</option>
                            <option value="past_paper">📋 Past Paper</option>
                            <option value="other">📦 Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" name="material_title" id="edit_material_title" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="material_description" id="edit_material_description" class="form-textarea"></textarea>
                    </div>
                    
                    <p style="color: var(--text-muted); margin-bottom: 1rem;">
                        <strong>Note:</strong> File cannot be changed. To upload a new file, delete this material and create a new one.
                    </p>
                    
                    <button type="submit" class="btn-submit">Update Material</button>
                    <button type="button" class="btn-secondary" onclick="closeModal('editMaterialModal')">Cancel</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let currentCourseId = null;
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 968) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        function openCreateCourseModal() {
            document.getElementById('createCourseModal').classList.add('active');
        }
        
        function openEditCourseModal(courseId, courseCode, courseName, description, credits, instructorName, semester, year) {
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('edit_course_code').value = courseCode;
            document.getElementById('edit_course_name').value = courseName;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_credits').value = credits;
            document.getElementById('edit_instructor_name').value = instructorName;
            document.getElementById('edit_semester').value = semester;
            document.getElementById('edit_year').value = year;
            document.getElementById('editCourseModal').classList.add('active');
        }
        
        function openUploadMaterialModal() {
            document.getElementById('upload_course_id').value = currentCourseId;
            document.getElementById('uploadMaterialModal').classList.add('active');
        }
        
        function openEditMaterialModal(materialId, materialType, title, description) {
            document.getElementById('edit_material_id').value = materialId;
            document.getElementById('edit_material_type').value = materialType;
            document.getElementById('edit_material_title').value = title;
            document.getElementById('edit_material_description').value = description;
            document.getElementById('editMaterialModal').classList.add('active');
        }
        
        function viewCourseMaterials(courseId, courseName) {
            currentCourseId = courseId;
            const modal = document.getElementById('materialsModal');
            const modalTitle = document.getElementById('modalTitle');
            const materialsContent = document.getElementById('materialsContent');
            
            modalTitle.textContent = courseName + ' - Materials';
            materialsContent.innerHTML = '<div style="text-align: center; padding: 3rem; color: var(--text-muted);">Loading materials...</div>';
            modal.classList.add('active');
            
            // Fetch materials via AJAX
            fetch('fetch_course_materials.php?course_id=' + courseId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data); // Debug log
                    
                    if (data.success) {
                        let html = '';
                        
                        // Organize materials by type
                        const materialTypes = {
                            'lecture': { title: '📖 Lectures', icon: '📖', materials: [] },
                            'assignment': { title: '📝 Assignments', icon: '📝', materials: [] },
                            'past_paper': { title: '📋 Past Papers', icon: '📋', materials: [] },
                            'notes': { title: '📓 Notes', icon: '📓', materials: [] },
                            'other': { title: '📦 Other Materials', icon: '📦', materials: [] }
                        };
                        
                        data.materials.forEach(material => {
                            if (materialTypes[material.material_type]) {
                                materialTypes[material.material_type].materials.push(material);
                            }
                        });
                        
                        // Build HTML for each type
                        Object.keys(materialTypes).forEach(type => {
                            const section = materialTypes[type];
                            if (section.materials.length > 0) {
                                html += `
                                    <div class="materials-section">
                                        <div class="section-title">${section.icon} ${section.title} (${section.materials.length})</div>
                                `;
                                
                                section.materials.forEach(material => {
                                    const fileSize = material.file_size ? (material.file_size / 1024 / 1024).toFixed(2) + ' MB' : 'N/A';
                                    const uploadDate = new Date(material.upload_date).toLocaleDateString();
                                    const fileExt = material.file_name.split('.').pop().toLowerCase();
                                    
                                    // Determine if file can be viewed in browser
                                    const viewableTypes = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'html', 'htm'];
                                    const canView = viewableTypes.includes(fileExt);
                                    
                                    html += `
                                        <div class="material-item">
                                            <div class="material-icon">${section.icon}</div>
                                            <div class="material-info">
                                                <div class="material-title">${escapeHtml(material.title)}</div>
                                                <div class="material-meta">
                                                    <span>📁 ${fileSize}</span>
                                                    <span>📅 ${uploadDate}</span>
                                                    <span>⬇️ ${material.downloads} downloads</span>
                                                    <span>📎 ${fileExt.toUpperCase()}</span>
                                                </div>
                                            </div>
                                            <div class="material-actions">
                                                ${canView ? `
                                                <a href="student_courses.php?view=${material.material_id}" target="_blank" class="btn-view-file">
                                                    👁️ View
                                                </a>
                                                ` : ''}
                                                <a href="student_courses.php?download=${material.material_id}" class="btn-download">
                                                    ⬇️ Download
                                                </a>
                                                <button class="btn-edit" onclick="openEditMaterialModal(${material.material_id}, '${material.material_type}', '${escapeHtml(material.title)}', '${escapeHtml(material.description || '')}')">
                                                    ✏️ Edit
                                                </button>
                                                <a href="student_courses.php?delete_material=${material.material_id}" class="btn-delete" onclick="return confirm('Are you sure you want to delete this material?')">
                                                    🗑️ Delete
                                                </a>
                                            </div>
                                        </div>
                                    `;
                                });
                                
                                html += '</div>';
                            }
                        });
                        
                        if (html === '') {
                            html = '<div class="no-materials">No materials available for this course yet.</div>';
                        }
                        
                        materialsContent.innerHTML = html;
                    } else {
                        const errorMsg = data.message || 'Unknown error';
                        materialsContent.innerHTML = `<div class="no-materials">Error: ${errorMsg}</div>`;
                        console.error('API Error:', data);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    materialsContent.innerHTML = `
                        <div class="no-materials">
                            <p>Error loading materials.</p>
                            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Please check:</p>
                            <ul style="text-align: left; display: inline-block; margin-top: 0.5rem;">
                                <li>fetch_course_materials.php file exists</li>
                                <li>Database connection is working</li>
                                <li>Browser console for detailed errors</li>
                            </ul>
                        </div>
                    `;
                });
        }
        
        function closeModal(modalId) {
            if (modalId) {
                document.getElementById(modalId).classList.remove('active');
            } else {
                // Close all modals
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        }
        
        // Close modal on background click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });
        });
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>