<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_announcement'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
        $is_global = isset($_POST['is_global']) ? 1 : 0;
        
        if (!empty($title) && !empty($content)) {
            $stmt = $conn->prepare("INSERT INTO announcements (course_id, title, content, posted_by, is_global) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issii", $course_id, $title, $content, $user_id, $is_global);
            
            if ($stmt->execute()) {
                $success_message = "Announcement posted successfully!";
                
                // Log activity
                logActivity($conn, $user_id, 'create_announcement', "Created announcement: $title");
            } else {
                $error_message = "Error posting announcement: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
    
    if (isset($_POST['edit_announcement'])) {
        $announcement_id = intval($_POST['announcement_id']);
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $course_id = !empty($_POST['course_id']) ? intval($_POST['course_id']) : null;
        $is_global = isset($_POST['is_global']) ? 1 : 0;
        
        if (!empty($title) && !empty($content)) {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, course_id = ?, is_global = ? WHERE announcement_id = ?");
            $stmt->bind_param("ssiii", $title, $content, $course_id, $is_global, $announcement_id);
            
            if ($stmt->execute()) {
                $success_message = "Announcement updated successfully!";
                logActivity($conn, $user_id, 'update_announcement', "Updated announcement ID: $announcement_id");
            } else {
                $error_message = "Error updating announcement.";
            }
            $stmt->close();
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $announcement_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $announcement_id);
    
    if ($stmt->execute()) {
        $success_message = "Announcement deleted successfully!";
        logActivity($conn, $user_id, 'delete_announcement', "Deleted announcement ID: $announcement_id");
    } else {
        $error_message = "Error deleting announcement.";
    }
    $stmt->close();
    header("Location: admin_announcements.php");
    exit();
}

// Get all courses for dropdown
$courses_query = "SELECT course_id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_name";
$courses_result = $conn->query($courses_query);
$courses = [];
while ($row = $courses_result->fetch_assoc()) {
    $courses[] = $row;
}

// Get announcement to edit if edit mode
$edit_announcement = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_announcement = $result->fetch_assoc();
    $stmt->close();
}

// Get all announcements
$announcements_query = "
    SELECT 
        a.*,
        c.course_name,
        c.course_code,
        u.full_name as posted_by_name
    FROM announcements a
    LEFT JOIN courses c ON a.course_id = c.course_id
    LEFT JOIN users u ON a.posted_by = u.user_id
    ORDER BY a.post_date DESC
";
$announcements_result = $conn->query($announcements_query);
$announcements = [];
while ($row = $announcements_result->fetch_assoc()) {
    $announcements[] = $row;
}

// Get admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements - University LMS</title>
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
            z-index: 1000;
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
        }
        
        .sidebar-header .title {
            font-size: 1.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
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
            margin: 0.25rem 0.75rem;
            border-radius: 12px;
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
        
        .nav-item .icon {
            font-size: 1.3rem;
            width: 24px;
            text-align: center;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-secondary {
            background: rgba(100, 116, 139, 0.15);
            color: #cbd5e1;
            border: 1px solid rgba(100, 116, 139, 0.3);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .content-section {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .form-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            color: var(--text);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.7);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: rgba(15, 23, 42, 0.5);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            cursor: pointer;
            font-weight: 500;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .announcements-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .announcement-card {
            background: rgba(15, 23, 42, 0.5);
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .announcement-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateY(-2px);
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .announcement-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .announcement-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .announcement-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .announcement-content {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        
        .announcement-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .badge-global {
            background: rgba(236, 72, 153, 0.2);
            color: #f9a8d4;
        }
        
        .badge-course {
            background: rgba(99, 102, 241, 0.2);
            color: #a5b4fc;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        @media (max-width: 968px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
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
            <a href="admin_courses.php" class="nav-item">
                <span class="icon">📚</span>
                <span>Manage Courses</span>
            </a>
            <a href="admin_announcements.php" class="nav-item active">
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
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span>✗</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1>📢 Manage Announcements</h1>
            <?php if (!isset($_GET['action']) && !isset($_GET['edit'])): ?>
                <a href="?action=add" class="btn btn-primary">
                    <span>➕</span>
                    Post New Announcement
                </a>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_GET['action']) && $_GET['action'] == 'add' || isset($_GET['edit'])): ?>
        <div class="content-section">
            <h2 class="section-title">
                <span><?php echo isset($_GET['edit']) ? '✏️ Edit' : '➕ Create'; ?> Announcement</span>
            </h2>
            <form method="POST" action="">
                <?php if (isset($_GET['edit'])): ?>
                    <input type="hidden" name="announcement_id" value="<?php echo $edit_announcement['announcement_id']; ?>">
                <?php endif; ?>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="title">📝 Announcement Title *</label>
                        <input type="text" id="title" name="title" required 
                               value="<?php echo isset($edit_announcement) ? htmlspecialchars($edit_announcement['title']) : ''; ?>"
                               placeholder="Enter announcement title">
                    </div>
                    
                    <div class="form-group">
                        <label for="content">📄 Content *</label>
                        <textarea id="content" name="content" required 
                                  placeholder="Enter announcement content..."><?php echo isset($edit_announcement) ? htmlspecialchars($edit_announcement['content']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="course_id">📚 Course (Optional - leave empty for all courses)</label>
                        <select id="course_id" name="course_id">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>"
                                        <?php echo (isset($edit_announcement) && $edit_announcement['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_global" name="is_global" value="1"
                               <?php echo (isset($edit_announcement) && $edit_announcement['is_global']) ? 'checked' : ''; ?>>
                        <label for="is_global">🌍 Make this a global announcement (visible to all students)</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="<?php echo isset($_GET['edit']) ? 'edit_announcement' : 'add_announcement'; ?>" class="btn btn-success">
                        <span><?php echo isset($_GET['edit']) ? '✓' : '➕'; ?></span>
                        <?php echo isset($_GET['edit']) ? 'Update' : 'Post'; ?> Announcement
                    </button>
                    <a href="admin_announcements.php" class="btn btn-secondary">
                        <span>✗</span>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
        <?php else: ?>
        <div class="content-section">
            <h2 class="section-title">
                <span>📋 All Announcements</span>
            </h2>
            
            <?php if (empty($announcements)): ?>
                <div class="no-data">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📢</div>
                    No announcements posted yet. Create your first announcement!
                </div>
            <?php else: ?>
                <div class="announcements-grid">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-card">
                            <div class="announcement-header">
                                <div style="flex: 1;">
                                    <div class="announcement-title">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </div>
                                    <div class="announcement-meta">
                                        <span>
                                            <span>👤</span>
                                            <?php echo htmlspecialchars($announcement['posted_by_name']); ?>
                                        </span>
                                        <span>
                                            <span>📅</span>
                                            <?php echo date('M j, Y g:i A', strtotime($announcement['post_date'])); ?>
                                        </span>
                                        <?php if ($announcement['is_global']): ?>
                                            <span class="badge badge-global">🌍 Global</span>
                                        <?php elseif ($announcement['course_id']): ?>
                                            <span class="badge badge-course">
                                                📚 <?php echo htmlspecialchars($announcement['course_code']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="announcement-content">
                                <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                            </div>
                            
                            <div class="announcement-actions">
                                <a href="?edit=<?php echo $announcement['announcement_id']; ?>" class="btn btn-secondary">
                                    <span>✏️</span>
                                    Edit
                                </a>
                                <a href="?delete=<?php echo $announcement['announcement_id']; ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Are you sure you want to delete this announcement?')">
                                    <span>🗑️</span>
                                    Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>