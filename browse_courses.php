<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$message = '';
$error = '';

// Handle course enrollment
if (isset($_GET['enroll']) && is_numeric($_GET['enroll'])) {
    $course_id = intval($_GET['enroll']);
    
    // Check if already enrolled
    $stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "You are already enrolled in this course!";
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, status) VALUES (?, ?, 'enrolled')");
        $stmt->bind_param("ii", $user_id, $course_id);
        
        if ($stmt->execute()) {
            $message = "Successfully enrolled in the course!";
            logActivity($user_id, 'enroll_course', "Enrolled in course ID: $course_id");
        } else {
            $error = "Error enrolling in course!";
        }
    }
    $stmt->close();
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_semester = isset($_GET['semester']) ? $_GET['semester'] : '';

// Get available courses (not enrolled)
$sql = "SELECT c.*, 
        COUNT(DISTINCT e.student_id) as enrolled_students,
        COUNT(DISTINCT cm.material_id) as material_count,
        CASE WHEN e2.enrollment_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
        FROM courses c 
        LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.status = 'enrolled'
        LEFT JOIN course_materials cm ON c.course_id = cm.course_id
        LEFT JOIN enrollments e2 ON c.course_id = e2.course_id AND e2.student_id = ?
        WHERE c.status = 'active'";

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

$sql .= " GROUP BY c.course_id ORDER BY c.created_at DESC";

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
$stmt = $conn->prepare("SELECT DISTINCT semester FROM courses WHERE status = 'active' ORDER BY semester DESC");
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
    <title>Browse Courses - University LMS</title>
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
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
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
        
        .course-card.enrolled {
            opacity: 0.7;
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .course-card.enrolled::before {
            background: linear-gradient(90deg, var(--success), #059669);
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
        
        .enrolled-badge {
            display: inline-block;
            padding: 0.375rem 0.875rem;
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
            margin-left: 0.5rem;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }
        
        .stat-item {
            background: rgba(15, 23, 42, 0.5);
            padding: 0.75rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-icon {
            font-size: 1.3rem;
            margin-bottom: 0.25rem;
        }
        
        .stat-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        
        .course-footer {
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.3);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn-enroll {
            width: 100%;
            padding: 0.875rem;
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
        
        .btn-enroll:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
        
        .btn-enrolled {
            width: 100%;
            padding: 0.875rem;
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            display: block;
            cursor: default;
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
            
            .courses-grid {
                grid-template-columns: 1fr;
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
            <a href="browse_courses.php" class="nav-item active">
                <span class="icon">🔍</span>
                <span>Browse Courses</span>
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
            <h1>🔍 Browse Available Courses</h1>
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
                    <a href="browse_courses.php" class="btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if (empty($courses)): ?>
            <div class="no-data">
                <h2>No courses available</h2>
                <p>There are no courses matching your criteria.</p>
            </div>
        <?php else: ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card <?php echo $course['is_enrolled'] ? 'enrolled' : ''; ?>">
                        <div class="course-header">
                            <div>
                                <span class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></span>
                                <?php if ($course['is_enrolled']): ?>
                                    <span class="enrolled-badge">✓ Enrolled</span>
                                <?php endif; ?>
                            </div>
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
                                    <div class="stat-icon">💳</div>
                                    <div class="stat-value"><?php echo $course['credits']; ?></div>
                                    <div class="stat-label">Credits</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">👥</div>
                                    <div class="stat-value"><?php echo $course['enrolled_students']; ?></div>
                                    <div class="stat-label">Students</div>
                                </div>
                                
                                <div class="stat-item">
                                    <div class="stat-icon">📄</div>
                                    <div class="stat-value"><?php echo $course['material_count']; ?></div>
                                    <div class="stat-label">Materials</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="course-footer">
                            <?php if ($course['is_enrolled']): ?>
                                <div class="btn-enrolled">✓ Already Enrolled</div>
                            <?php else: ?>
                                <a href="?enroll=<?php echo $course['course_id']; ?>" class="btn-enroll" onclick="return confirm('Are you sure you want to enroll in this course?')">
                                    Enroll Now
                                </a>
                            <?php endif; ?>
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