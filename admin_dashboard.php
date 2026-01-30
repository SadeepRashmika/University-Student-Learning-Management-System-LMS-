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

// Get total students count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE user_type = 'student'");
$stmt->execute();
$result = $stmt->get_result();
$total_students = $result->fetch_assoc()['count'];
$stmt->close();

// Get total courses count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
$total_courses = $result->fetch_assoc()['count'];
$stmt->close();

// Get total enrollments count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE status = 'enrolled'");
$stmt->execute();
$result = $stmt->get_result();
$total_enrollments = $result->fetch_assoc()['count'];
$stmt->close();

// Get total announcements count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM announcements");
$stmt->execute();
$result = $stmt->get_result();
$total_announcements = $result->fetch_assoc()['count'];
$stmt->close();

// Get recent students (last 5)
$stmt = $conn->prepare("SELECT * FROM users WHERE user_type = 'student' ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
$recent_students = [];
while ($row = $result->fetch_assoc()) {
    $recent_students[] = $row;
}
$stmt->close();

// Get recent courses (last 5)
$stmt = $conn->prepare("SELECT * FROM courses ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
$recent_courses = [];
while ($row = $result->fetch_assoc()) {
    $recent_courses[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - University LMS</title>
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
        
        .nav-section {
            margin-bottom: 1.5rem;
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
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.4);
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
        
        .user-role {
            font-size: 0.85rem;
            color: var(--accent);
            font-weight: 600;
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
        
        /* Stats Grid */
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
        .stat-card.purple { --gradient-from: #8b5cf6; --gradient-to: #7c3aed; }
        .stat-card.pink { --gradient-from: #ec4899; --gradient-to: #db2777; }
        
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
        
        .stat-card.purple .icon {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(124, 58, 237, 0.2));
            color: #a78bfa;
        }
        
        .stat-card.pink .icon {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(219, 39, 119, 0.2));
            color: #f9a8d4;
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
        
        /* Quick Actions */
        .quick-actions {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }
        
        .quick-actions h2 {
            color: var(--text);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .action-btn {
            padding: 1.25rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            text-align: center;
        }
        
        .action-btn:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.2);
        }
        
        .action-btn .icon {
            font-size: 2.5rem;
        }
        
        .action-btn .label {
            color: var(--text);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .action-btn .desc {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        /* Recent Activity Tables */
        .activity-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .activity-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .activity-card h2 {
            color: var(--text);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .activity-item {
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 10px;
            margin-bottom: 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateX(5px);
        }
        
        .activity-item:last-child {
            margin-bottom: 0;
        }
        
        .activity-item .name {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        
        .activity-item .details {
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .activity-item .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary);
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .no-data {
            color: var(--text-muted);
            text-align: center;
            padding: 2rem;
            font-size: 0.95rem;
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
        @media (max-width: 1200px) {
            .activity-section {
                grid-template-columns: 1fr;
            }
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
            
            .action-grid {
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
            <span class="title">Admin Panel</span>
        </div>
        <nav class="nav-menu">
            <a href="admin_dashboard.php" class="nav-item active">
                <span class="icon">📊</span>
                <span>Dashboard</span>
            </a>
            
            <div class="nav-section-title">Management</div>
            
            <a href="admin_students.php" class="nav-item">
                <span class="icon">👥</span>
                <span>Manage Students</span>
            </a>
            <a href="admin_courses.php" class="nav-item">
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
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($admin['profile_image'] && file_exists($admin['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Profile Photo">
                    <?php else: ?>
                        <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                    <div class="user-role">Administrator</div>
                </div>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon">👥</div>
                <h3>Total Students</h3>
                <div class="value"><?php echo $total_students; ?></div>
            </div>
            
            <div class="stat-card green">
                <div class="icon">📚</div>
                <h3>Active Courses</h3>
                <div class="value"><?php echo $total_courses; ?></div>
            </div>
            
            <div class="stat-card purple">
                <div class="icon">📝</div>
                <h3>Total Enrollments</h3>
                <div class="value"><?php echo $total_enrollments; ?></div>
            </div>
            
            <div class="stat-card pink">
                <div class="icon">📢</div>
                <h3>Announcements</h3>
                <div class="value"><?php echo $total_announcements; ?></div>
            </div>
        </div>
        
        <div class="quick-actions">
            <h2>⚡ Quick Actions</h2>
            <div class="action-grid">
                <a href="admin_students.php?action=add" class="action-btn">
                    <span class="icon">➕</span>
                    <span class="label">Add Student</span>
                    <span class="desc">Register new student</span>
                </a>
                <a href="admin_courses.php?action=add" class="action-btn">
                    <span class="icon">📖</span>
                    <span class="label">Create Course</span>
                    <span class="desc">Add new course</span>
                </a>
                <a href="admin_announcements.php?action=add" class="action-btn">
                    <span class="icon">📣</span>
                    <span class="label">Post Announcement</span>
                    <span class="desc">Broadcast message</span>
                </a>
                <a href="admin_students.php" class="action-btn">
                    <span class="icon">👁️</span>
                    <span class="label">View All Students</span>
                    <span class="desc">Manage students</span>
                </a>
            </div>
        </div>
        
        <div class="activity-section">
            <div class="activity-card">
                <h2>👥 Recent Students</h2>
                <?php if (empty($recent_students)): ?>
                    <div class="no-data">No students registered yet</div>
                <?php else: ?>
                    <?php foreach ($recent_students as $student): ?>
                        <div class="activity-item">
                            <div class="name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                            <div class="details">
                                Student ID: <?php echo htmlspecialchars($student['student_id']); ?><br>
                                Email: <?php echo htmlspecialchars($student['email']); ?>
                            </div>
                            <span class="badge">Joined <?php echo date('M j, Y', strtotime($student['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="activity-card">
                <h2>📚 Recent Courses</h2>
                <?php if (empty($recent_courses)): ?>
                    <div class="no-data">No courses created yet</div>
                <?php else: ?>
                    <?php foreach ($recent_courses as $course): ?>
                        <div class="activity-item">
                            <div class="name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="details">
                                Code: <?php echo htmlspecialchars($course['course_code']); ?><br>
                                <?php if ($course['semester']): ?>
                                    Semester: <?php echo htmlspecialchars($course['semester']); ?>
                                <?php endif; ?>
                            </div>
                            <span class="badge">Created <?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
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