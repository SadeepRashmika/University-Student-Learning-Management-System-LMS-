<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Handle material download tracking
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    $material_id = intval($_GET['download']);
    
    $stmt = $conn->prepare("SELECT cm.*, c.course_name FROM course_materials cm
        INNER JOIN courses c ON cm.course_id = c.course_id
        INNER JOIN enrollments e ON c.course_id = e.course_id
        WHERE cm.material_id = ? AND e.student_id = ? AND e.status = 'enrolled'");
    $stmt->bind_param("ii", $material_id, $user_id);
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

// Handle material viewing
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $material_id = intval($_GET['view']);
    
    $stmt = $conn->prepare("SELECT cm.*, c.course_name FROM course_materials cm
        INNER JOIN courses c ON cm.course_id = c.course_id
        INNER JOIN enrollments e ON c.course_id = e.course_id
        WHERE cm.material_id = ? AND e.student_id = ? AND e.status = 'enrolled'");
    $stmt->bind_param("ii", $material_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($material = $result->fetch_assoc()) {
        $file_path = $material['file_path'];
        if (file_exists($file_path)) {
            $file_ext = strtolower(pathinfo($material['file_name'], PATHINFO_EXTENSION));
            
            $content_types = [
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'txt' => 'text/plain',
                'html' => 'text/html',
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

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

// Get all materials from enrolled courses
$sql = "SELECT cm.*, c.course_name, c.course_code 
        FROM course_materials cm
        INNER JOIN courses c ON cm.course_id = c.course_id
        INNER JOIN enrollments e ON c.course_id = e.course_id
        WHERE e.student_id = ? AND e.status = 'enrolled'";

$params = [$user_id];
$types = "i";

if ($search) {
    $sql .= " AND (cm.title LIKE ? OR cm.description LIKE ? OR c.course_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($filter_course) {
    $sql .= " AND cm.course_id = ?";
    $params[] = $filter_course;
    $types .= "i";
}

if ($filter_type) {
    $sql .= " AND cm.material_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

$sql .= " ORDER BY cm.upload_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$materials = [];
while ($row = $result->fetch_assoc()) {
    $materials[] = $row;
}
$stmt->close();

// Get enrolled courses for filter
$stmt = $conn->prepare("SELECT c.course_id, c.course_name, c.course_code FROM courses c
    INNER JOIN enrollments e ON c.course_id = e.course_id
    WHERE e.student_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_name");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$enrolled_courses = [];
while ($row = $result->fetch_assoc()) {
    $enrolled_courses[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Materials - University LMS</title>
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
            margin-bottom: 2rem;
        }
        
        .top-bar h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
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
        
        .materials-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .material-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .material-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .material-icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            flex-shrink: 0;
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        
        .material-info {
            flex: 1;
        }
        
        .material-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }
        
        .material-type-badge {
            padding: 0.375rem 0.875rem;
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .course-badge {
            padding: 0.375rem 0.875rem;
            background: rgba(139, 92, 246, 0.2);
            color: var(--secondary);
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        
        .material-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .material-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }
        
        .material-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
            flex-wrap: wrap;
        }
        
        .material-meta span {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        
        .material-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex-shrink: 0;
        }
        
        .btn-download, .btn-view-file {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .btn-download {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .btn-download:hover {
            background: rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
        }
        
        .btn-view-file {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .btn-view-file:hover {
            background: rgba(59, 130, 246, 0.3);
            transform: translateY(-2px);
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            font-size: 1rem;
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
            
            .material-card {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .material-actions {
                width: 100%;
                flex-direction: row;
            }
            
            .btn-download, .btn-view-file {
                flex: 1;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .search-box, .filter-select {
                width: 100%;
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
            <a href="student_courses.php" class="nav-item">
                <span class="icon">📚</span>
                <span>My Courses</span>
            </a>
            <a href="Timetable.php" class="nav-item">
                <span class="icon">📅</span>
                <span>Timetable</span>
            </a>
            <a href="student_materials.php" class="nav-item active">
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
        <div class="top-bar">
            <h1>📄 Course Materials</h1>
        </div>
        
        <div class="filter-bar">
            <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; width: 100%;">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search materials..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <select name="course_id" class="filter-select">
                    <option value="0">All Courses</option>
                    <?php foreach ($enrolled_courses as $course): ?>
                        <option value="<?php echo $course['course_id']; ?>" <?php echo $filter_course == $course['course_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['course_code']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="type" class="filter-select">
                    <option value="">All Types</option>
                    <option value="lecture" <?php echo $filter_type === 'lecture' ? 'selected' : ''; ?>>Lecture</option>
                    <option value="assignment" <?php echo $filter_type === 'assignment' ? 'selected' : ''; ?>>Assignment</option>
                    <option value="notes" <?php echo $filter_type === 'notes' ? 'selected' : ''; ?>>Notes</option>
                    <option value="past_paper" <?php echo $filter_type === 'past_paper' ? 'selected' : ''; ?>>Past Paper</option>
                    <option value="other" <?php echo $filter_type === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                
                <button type="submit" class="btn-filter">Apply Filters</button>
                <?php if ($search || $filter_course || $filter_type): ?>
                    <a href="student_materials.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($materials)): ?>
            <div class="no-data">
                <h2>No materials found</h2>
                <p>There are no materials available matching your criteria.</p>
            </div>
        <?php else: ?>
            <div class="materials-list">
                <?php 
                $type_icons = [
                    'lecture' => '📖',
                    'assignment' => '📝',
                    'notes' => '📓',
                    'past_paper' => '📋',
                    'other' => '📦'
                ];
                
                foreach ($materials as $material): 
                    $icon = $type_icons[$material['material_type']] ?? '📄';
                    $file_size = $material['file_size'] ? number_format($material['file_size'] / 1024 / 1024, 2) . ' MB' : 'N/A';
                    $upload_date = date('M j, Y', strtotime($material['upload_date']));
                    $file_ext = strtolower(pathinfo($material['file_name'], PATHINFO_EXTENSION));
                    $viewable_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'html'];
                    $can_view = in_array($file_ext, $viewable_types);
                ?>
                    <div class="material-card">
                        <div class="material-icon"><?php echo $icon; ?></div>
                        
                        <div class="material-info">
                            <div class="material-header">
                                <span class="material-type-badge"><?php echo htmlspecialchars($material['material_type']); ?></span>
                                <span class="course-badge"><?php echo htmlspecialchars($material['course_code']); ?></span>
                            </div>
                            
                            <div class="material-title"><?php echo htmlspecialchars($material['title']); ?></div>
                            
                            <?php if ($material['description']): ?>
                                <div class="material-description">
                                    <?php echo htmlspecialchars($material['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="material-meta">
                                <span>📚 <?php echo htmlspecialchars($material['course_name']); ?></span>
                                <span>📁 <?php echo $file_size; ?></span>
                                <span>📅 <?php echo $upload_date; ?></span>
                                <span>⬇️ <?php echo $material['downloads']; ?> downloads</span>
                                <span>📎 <?php echo strtoupper($file_ext); ?></span>
                            </div>
                        </div>
                        
                        <div class="material-actions">
                            <?php if ($can_view): ?>
                                <a href="?view=<?php echo $material['material_id']; ?>" target="_blank" class="btn-view-file">
                                    👁️ View
                                </a>
                            <?php endif; ?>
                            <a href="?download=<?php echo $material['material_id']; ?>" class="btn-download">
                                ⬇️ Download
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
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
    </script>
</body>
</html>