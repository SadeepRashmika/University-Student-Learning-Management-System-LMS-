<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $course_code = trim($_POST['course_code']);
                $course_name = trim($_POST['course_name']);
                $description = trim($_POST['description']);
                $credits = intval($_POST['credits']);
                $instructor_name = trim($_POST['instructor_name']);
                $semester = trim($_POST['semester']);
                $year = intval($_POST['year']);
                
                if (empty($course_code) || empty($course_name)) {
                    $error = "Course code and name are required";
                } else {
                    $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, description, credits, instructor_name, semester, year, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssissii", $course_code, $course_name, $description, $credits, $instructor_name, $semester, $year, $user_id);
                    
                    if ($stmt->execute()) {
                        $message = "Course added successfully";
                        logActivity($conn, $user_id, 'create_course', "Created course: $course_name ($course_code)");
                    } else {
                        $error = "Error adding course";
                    }
                    $stmt->close();
                }
                break;
                
            case 'edit':
                $course_id = intval($_POST['course_id']);
                $course_code = trim($_POST['course_code']);
                $course_name = trim($_POST['course_name']);
                $description = trim($_POST['description']);
                $credits = intval($_POST['credits']);
                $instructor_name = trim($_POST['instructor_name']);
                $semester = trim($_POST['semester']);
                $year = intval($_POST['year']);
                $status = $_POST['status'];
                
                $stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ?, credits = ?, instructor_name = ?, semester = ?, year = ?, status = ? WHERE course_id = ?");
                $stmt->bind_param("sssississi", $course_code, $course_name, $description, $credits, $instructor_name, $semester, $year, $status, $course_id);
                
                if ($stmt->execute()) {
                    $message = "Course updated successfully";
                    logActivity($conn, $user_id, 'update_course', "Updated course ID: $course_id");
                } else {
                    $error = "Error updating course";
                }
                $stmt->close();
                break;
                
            case 'delete':
                $course_id = intval($_POST['course_id']);
                
                $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                
                if ($stmt->execute()) {
                    $message = "Course deleted successfully";
                    logActivity($conn, $user_id, 'delete_course', "Deleted course ID: $course_id");
                } else {
                    $error = "Error deleting course";
                }
                $stmt->close();
                break;
        }
    }
}

// Get all courses with enrollment count
$stmt = $conn->prepare("
    SELECT c.*, COUNT(DISTINCT e.enrollment_id) as enrollment_count,
           u.full_name as creator_name
    FROM courses c 
    LEFT JOIN enrollments e ON c.course_id = e.course_id 
    LEFT JOIN users u ON c.created_by = u.user_id
    GROUP BY c.course_id 
    ORDER BY c.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - University LMS</title>
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
            --danger: #ef4444;
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
        
        .nav-section-title {
            padding: 0.5rem 1.5rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 1rem;
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
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
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

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text);
            font-size: 0.9rem;
            width: 250px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.7);
            width: 300px;
        }

        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .content-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }
        
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            color: var(--text);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.7);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: rgba(15, 23, 42, 0.8);
        }
        
        th {
            padding: 1rem 1.25rem;
            text-align: left;
            color: var(--text);
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        td {
            padding: 1.25rem;
            color: var(--text-muted);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        tbody tr {
            transition: all 0.3s ease;
        }
        
        tbody tr:hover {
            background: rgba(99, 102, 241, 0.05);
        }
        
        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-active {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }
        
        .badge-inactive {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            animation: fadeIn 0.3s ease;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--dark-light);
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text);
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            line-height: 1;
            transition: all 0.3s ease;
        }
        
        .modal-close:hover {
            color: var(--text);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
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
        }
        
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
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-box input {
                width: 100%;
            }

            .search-box input:focus {
                width: 100%;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-small {
                width: 100%;
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
            <span class="title">Admin Panel</span>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php" class="nav-item">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section-title">Management</div>
            
            <a href="admin_students.php" class="nav-item">
                <span class="icon">👥</span>
                <span>Manage Students</span>
            </a>
            <a href="admin_courses.php" class="nav-item active">
                <span class="icon">📚</span>
                <span>Manage Courses</span>
            </a>
            <a href="admin_announcements.php" class="nav-item">
                <span class="icon">📢</span>
                <span>Announcements</span>
            </a>
            
            <div class="nav-section-title">Account</div>
            
            <a href="admin_profile.php" class="nav-item">
                <span class="icon">👤</span>
                <span>My Profile</span>
            </a>
            <a href="logout.php" class="nav-item">
                <span class="icon">🚪</span>
                <span>Logout</span>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h1>📚 Manage Courses</h1>
            <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search courses..." onkeyup="filterCourses()">
                </div>
                <button class="btn btn-primary" onclick="openAddModal()">
                    ➕ Add New Course
                </button>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <span>✕</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="content-card">
            <div class="card-header">
                <h2>All Courses (<?php echo count($courses); ?>)</h2>
            </div>
            
            <div class="table-container">
                <table id="coursesTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Course Name</th>
                            <th>Instructor</th>
                            <th>Credits</th>
                            <th>Semester</th>
                            <th>Enrollments</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($courses)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                    No courses found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['instructor_name'] ?? 'TBA'); ?></td>
                                    <td><?php echo $course['credits']; ?></td>
                                    <td><?php echo htmlspecialchars($course['semester'] ?? 'N/A'); ?></td>
                                    <td><?php echo $course['enrollment_count']; ?> students</td>
                                    <td>
                                        <span class="badge badge-<?php echo $course['status']; ?>">
                                            <?php echo ucfirst($course['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-success btn-small" onclick='openEditModal(<?php echo json_encode($course); ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $course['course_id']; ?>, '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                                🗑️ Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Course Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Add New Course</h3>
                <button class="modal-close" onclick="closeAddModal()">×</button>
            </div>
            <form method="POST" id="addCourseForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course Code *</label>
                            <input type="text" name="course_code" required>
                        </div>
                        <div class="form-group">
                            <label>Credits</label>
                            <input type="number" name="credits" value="3" min="1" max="6">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Course Name *</label>
                        <input type="text" name="course_name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Instructor Name</label>
                        <input type="text" name="instructor_name">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Semester</label>
                            <input type="text" name="semester" placeholder="e.g., Spring 2025">
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" name="year" value="2025" min="2020" max="2030">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Course Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Course</h3>
                <button class="modal-close" onclick="closeEditModal()">×</button>
            </div>
            <form method="POST" id="editCourseForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="course_id" id="edit_course_id">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course Code *</label>
                            <input type="text" name="course_code" id="edit_course_code" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Course Name *</label>
                        <input type="text" name="course_name" id="edit_course_name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Instructor Name</label>
                            <input type="text" name="instructor_name" id="edit_instructor_name">
                        </div>
                        <div class="form-group">
                            <label>Credits</label>
                            <input type="number" name="credits" id="edit_credits" min="1" max="6">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Semester</label>
                            <input type="text" name="semester" id="edit_semester" placeholder="e.g., Spring 2025">
                        </div>
                        <div class="form-group">
                            <label>Year</label>
                            <input type="number" name="year" id="edit_year" min="2020" max="2030">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.querySelector('.menu-toggle');
            
            if (window.innerWidth <= 968) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Add Course Modal Functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Edit Course Modal Functions
        function openEditModal(course) {
            document.getElementById('edit_course_id').value = course.course_id;
            document.getElementById('edit_course_code').value = course.course_code;
            document.getElementById('edit_course_name').value = course.course_name;
            document.getElementById('edit_description').value = course.description || '';
            document.getElementById('edit_instructor_name').value = course.instructor_name || '';
            document.getElementById('edit_credits').value = course.credits;
            document.getElementById('edit_semester').value = course.semester || '';
            document.getElementById('edit_year').value = course.year || '';
            document.getElementById('edit_status').value = course.status;
            
            document.getElementById('editModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Delete Course Function
        function confirmDelete(courseId, courseName) {
            const confirmation = confirm(
                '⚠️ DELETE COURSE\n\n' +
                'Course: ' + courseName + '\n' +
                'ID: ' + courseId + '\n\n' +
                'This will permanently delete:\n' +
                '• Course information\n' +
                '• All course materials\n' +
                '• All enrollments\n' +
                '• All assignments and submissions\n\n' +
                'This action CANNOT be undone!\n\n' +
                'Are you absolutely sure?'
            );
            
            if (confirmation) {
                // Create and submit form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const courseIdInput = document.createElement('input');
                courseIdInput.type = 'hidden';
                courseIdInput.name = 'course_id';
                courseIdInput.value = courseId;
                
                form.appendChild(actionInput);
                form.appendChild(courseIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
        }

        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAddModal();
                closeEditModal();
            }
        });

        // Form validation for Add Course
        document.getElementById('addCourseForm').addEventListener('submit', function(e) {
            const courseCode = this.querySelector('input[name="course_code"]').value.trim();
            const courseName = this.querySelector('input[name="course_name"]').value.trim();
            
            if (courseCode === '' || courseName === '') {
                e.preventDefault();
                alert('⚠️ Course code and name are required!');
                return false;
            }
            
            return true;
        });

        // Form validation for Edit Course
        document.getElementById('editCourseForm').addEventListener('submit', function(e) {
            const courseCode = this.querySelector('input[name="course_code"]').value.trim();
            const courseName = this.querySelector('input[name="course_name"]').value.trim();
            
            if (courseCode === '' || courseName === '') {
                e.preventDefault();
                alert('⚠️ Course code and name are required!');
                return false;
            }
            
            return true;
        });

        // Search/Filter functionality for courses
        function filterCourses() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('coursesTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length - 1; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    if (cellText.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }

        // Auto-hide alerts after 5 seconds with smooth fade
        window.addEventListener('load', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'all 0.3s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>