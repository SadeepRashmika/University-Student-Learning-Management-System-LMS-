<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

// Get enrolled courses count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE student_id = ? AND status = 'enrolled'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$enrolled_count = $result->fetch_assoc()['count'];
$stmt->close();

// Get total materials downloaded
$stmt = $conn->prepare("SELECT COALESCE(SUM(cm.downloads), 0) as total FROM course_materials cm 
    INNER JOIN enrollments e ON cm.course_id = e.course_id 
    WHERE e.student_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$downloads = $result->fetch_assoc()['total'];
$stmt->close();

// Calculate GPA
$stmt = $conn->prepare("SELECT AVG(gpa) as avg_gpa FROM enrollments WHERE student_id = ? AND gpa IS NOT NULL");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$gpa_result = $result->fetch_assoc();
$avg_gpa = $gpa_result['avg_gpa'] ? round($gpa_result['avg_gpa'], 2) : 0;
$stmt->close();

// Get recent announcements
$stmt = $conn->prepare("SELECT a.*, c.course_name FROM announcements a 
    LEFT JOIN courses c ON a.course_id = c.course_id 
    WHERE a.is_global = 1 OR a.course_id IN (SELECT course_id FROM enrollments WHERE student_id = ?) 
    ORDER BY a.post_date DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$announcements = [];
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - University LMS</title>
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
        
        /* Top Bar */
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
        }
        
        .top-bar h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 700;
            color: var(--text);
        }
        
        .user-id {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .btn-logout {
            padding: 0.65rem 1.5rem;
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.25);
            border-color: rgba(239, 68, 68, 0.5);
            transform: translateY(-2px);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 1.75rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--gradient-from), var(--gradient-to));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card.blue { --gradient-from: #3b82f6; --gradient-to: #2563eb; }
        .stat-card.green { --gradient-from: #10b981; --gradient-to: #059669; }
        .stat-card.orange { --gradient-from: #f59e0b; --gradient-to: #d97706; }
        .stat-card.purple { --gradient-from: #8b5cf6; --gradient-to: #7c3aed; }
        
        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .stat-card.blue .icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(37, 99, 235, 0.2));
            color: #60a5fa;
        }
        
        .stat-card.green .icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.2));
            color: #34d399;
        }
        
        .stat-card.orange .icon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(217, 119, 6, 0.2));
            color: #fbbf24;
        }
        
        .stat-card.purple .icon {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.2));
            color: #a78bfa;
        }
        
        .stat-card h3 {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text);
            line-height: 1;
        }
        
        /* Announcements */
        .announcements {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .announcements h2 {
            color: var(--text);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 800;
        }
        
        .announcement-item {
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .announcement-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
        }
        
        .announcement-item:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateX(5px);
        }
        
        .announcement-item:last-child {
            margin-bottom: 0;
        }
        
        .announcement-item .title {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .announcement-item .course {
            font-size: 0.85rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }
        
        .announcement-item .content {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }
        
        .announcement-item .date {
            font-size: 0.8rem;
            color: var(--text-muted);
            opacity: 0.7;
        }
        
        .no-data {
            color: var(--text-muted);
            text-align: center;
            padding: 3rem;
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
            
            .top-bar {
                flex-direction: column;
                gap: 1.5rem;
                align-items: flex-start;
                padding: 1.5rem;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .top-bar h1 {
                font-size: 1.4rem;
            }
            
            .stat-card .value {
                font-size: 2rem;
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
            <a href="student_dashboard.php" class="nav-item active">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="student_courses.php" class="nav-item">
                <span class="icon">📚</span>
                <span>My Courses</span>
            </a>
            <a href="Timetable.php" class="nav-item">
                <span class="icon">📅 </span>
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
        <div class="top-bar">
            <h1>Welcome, <?php echo htmlspecialchars($student['full_name']); ?>!</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($student['profile_image'] && file_exists($student['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($student['profile_image']); ?>" alt="Profile Photo">
                    <?php else: ?>
                        <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                    <div class="user-id"><?php echo htmlspecialchars($student['student_id']); ?></div>
                </div>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon">📚</div>
                <h3>Enrolled Courses</h3>
                <div class="value"><?php echo $enrolled_count; ?></div>
            </div>
            
            <div class="stat-card green">
                <div class="icon">💾</div>
                <h3>Materials Downloaded</h3>
                <div class="value"><?php echo $downloads; ?></div>
            </div>
            
            <div class="stat-card orange">
                <div class="icon">📊</div>
                <h3>Current GPA</h3>
                <div class="value"><?php echo number_format($avg_gpa, 2); ?></div>
            </div>
            
            <div class="stat-card purple">
                <div class="icon">🎯</div>
                <h3>Assignments</h3>
                <div class="value">0</div>
            </div>
        </div>
        
        <div class="announcements">
            <h2>📢 Recent Announcements</h2>
            <?php if (empty($announcements)): ?>
                <div class="no-data">No announcements yet</div>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item">
                        <div class="title"><?php echo htmlspecialchars($announcement['title']); ?></div>
                        <?php if ($announcement['course_name']): ?>
                            <div class="course">📚 <?php echo htmlspecialchars($announcement['course_name']); ?></div>
                        <?php else: ?>
                            <div class="course">🌐 Global Announcement</div>
                        <?php endif; ?>
                        <div class="content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></div>
                        <div class="date">Posted on <?php echo date('M j, Y g:i A', strtotime($announcement['post_date'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Close sidebar when clicking outside on mobile
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