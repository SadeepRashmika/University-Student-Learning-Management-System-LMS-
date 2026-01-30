<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $subject_name = trim($_POST['subject_name']);
                $day_of_week = $_POST['day_of_week'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $location = trim($_POST['location']);
                $instructor = trim($_POST['instructor']);
                $notes = trim($_POST['notes']);
                
                $stmt = $conn->prepare("INSERT INTO timetable (student_id, subject_name, day_of_week, start_time, end_time, location, instructor, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssssss", $user_id, $subject_name, $day_of_week, $start_time, $end_time, $location, $instructor, $notes);
                
                if ($stmt->execute()) {
                    $message = "Class added successfully!";
                } else {
                    $error = "Failed to add class.";
                }
                $stmt->close();
                break;
                
            case 'update':
                $timetable_id = intval($_POST['timetable_id']);
                $subject_name = trim($_POST['subject_name']);
                $day_of_week = $_POST['day_of_week'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $location = trim($_POST['location']);
                $instructor = trim($_POST['instructor']);
                $notes = trim($_POST['notes']);
                
                $stmt = $conn->prepare("UPDATE timetable SET subject_name=?, day_of_week=?, start_time=?, end_time=?, location=?, instructor=?, notes=? WHERE timetable_id=? AND student_id=?");
                $stmt->bind_param("sssssssii", $subject_name, $day_of_week, $start_time, $end_time, $location, $instructor, $notes, $timetable_id, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Class updated successfully!";
                } else {
                    $error = "Failed to update class.";
                }
                $stmt->close();
                break;
                
            case 'delete':
                $timetable_id = intval($_POST['timetable_id']);
                
                $stmt = $conn->prepare("DELETE FROM timetable WHERE timetable_id=? AND student_id=?");
                $stmt->bind_param("ii", $timetable_id, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Class deleted successfully!";
                } else {
                    $error = "Failed to delete class.";
                }
                $stmt->close();
                break;
        }
    }
}

// Get all timetable entries
$stmt = $conn->prepare("SELECT * FROM timetable WHERE student_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$timetable_entries = [];
while ($row = $result->fetch_assoc()) {
    $timetable_entries[] = $row;
}
$stmt->close();

// Organize by day
$timetable_by_day = [
    'Monday' => [],
    'Tuesday' => [],
    'Wednesday' => [],
    'Thursday' => [],
    'Friday' => [],
    'Saturday' => [],
    'Sunday' => []
];

foreach ($timetable_entries as $entry) {
    $timetable_by_day[$entry['day_of_week']][] = $entry;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable - University LMS</title>
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
            --success: #10b981;
            --error: #ef4444;
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
        
        .top-bar {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-bar h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .btn-primary {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
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
        
        .timetable-container {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }
        
        .day-section {
            margin-bottom: 2rem;
        }
        
        .day-header {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(99, 102, 241, 0.3);
        }
        
        .class-card {
            background: rgba(15, 23, 42, 0.6);
            padding: 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .class-card:hover {
            border-color: rgba(99, 102, 241, 0.4);
            transform: translateX(5px);
        }
        
        .class-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 12px 0 0 12px;
        }
        
        .class-info {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 1rem;
        }
        
        .class-details h3 {
            font-size: 1.2rem;
            color: var(--text);
            margin-bottom: 0.5rem;
        }
        
        .class-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
        }
        
        .class-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            padding: 0.5rem;
            background: rgba(99, 102, 241, 0.15);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .btn-icon:hover {
            background: rgba(99, 102, 241, 0.25);
            transform: translateY(-2px);
        }
        
        .btn-icon.delete {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .btn-icon.delete:hover {
            background: rgba(239, 68, 68, 0.25);
        }
        
        .no-classes {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: rgba(30, 41, 59, 0.98);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            font-size: 1.5rem;
            font-weight: 800;
        }
        
        .btn-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text);
            font-family: inherit;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.8);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn-secondary {
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: rgba(30, 41, 59, 0.95);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 1.3rem;
            cursor: pointer;
            z-index: 1001;
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
            
            .class-info {
                flex-direction: column;
            }
            
            .class-meta {
                flex-direction: column;
                gap: 0.5rem;
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
            <a href="Timetable.php" class="nav-item active">
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
        <div class="top-bar">
            <h1>📅 My Timetable</h1>
            <button class="btn-primary" onclick="openAddModal()">➕ Add Class</button>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="timetable-container">
            <?php foreach ($timetable_by_day as $day => $classes): ?>
                <div class="day-section">
                    <div class="day-header"><?php echo $day; ?></div>
                    
                    <?php if (empty($classes)): ?>
                        <div class="no-classes">No classes scheduled</div>
                    <?php else: ?>
                        <?php foreach ($classes as $class): ?>
                            <div class="class-card">
                                <div class="class-info">
                                    <div class="class-details">
                                        <h3><?php echo htmlspecialchars($class['subject_name']); ?></h3>
                                        <div class="class-meta">
                                            <div class="meta-item">
                                                <span>🕐</span>
                                                <span><?php echo date('g:i A', strtotime($class['start_time'])); ?> - <?php echo date('g:i A', strtotime($class['end_time'])); ?></span>
                                            </div>
                                            <?php if ($class['location']): ?>
                                                <div class="meta-item">
                                                    <span>📍</span>
                                                    <span><?php echo htmlspecialchars($class['location']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($class['instructor']): ?>
                                                <div class="meta-item">
                                                    <span>👨‍🏫</span>
                                                    <span><?php echo htmlspecialchars($class['instructor']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($class['notes']): ?>
                                            <div class="meta-item" style="margin-top: 0.5rem;">
                                                <span>📝</span>
                                                <span><?php echo htmlspecialchars($class['notes']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="class-actions">
                                        <button class="btn-icon" onclick='openEditModal(<?php echo json_encode($class); ?>)'>✏️</button>
                                        <button class="btn-icon delete" onclick="deleteClass(<?php echo $class['timetable_id']; ?>)">🗑️</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="modal" id="classModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Class</h2>
                <button class="btn-close" onclick="closeModal()">×</button>
            </div>
            
            <form method="POST" id="classForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="timetable_id" id="timetableId">
                
                <div class="form-group">
                    <label for="subject_name">Subject Name *</label>
                    <input type="text" id="subject_name" name="subject_name" required>
                </div>
                
                <div class="form-group">
                    <label for="day_of_week">Day *</label>
                    <select id="day_of_week" name="day_of_week" required>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="start_time">Start Time *</label>
                    <input type="time" id="start_time" name="start_time" required>
                </div>
                
                <div class="form-group">
                    <label for="end_time">End Time *</label>
                    <input type="time" id="end_time" name="end_time" required>
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" placeholder="e.g., Room 101, Building A">
                </div>
                
                <div class="form-group">
                    <label for="instructor">Instructor</label>
                    <input type="text" id="instructor" name="instructor" placeholder="Instructor name">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Any additional notes..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="timetable_id" id="deleteId">
    </form>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Class';
            document.getElementById('formAction').value = 'add';
            document.getElementById('classForm').reset();
            document.getElementById('timetableId').value = '';
            document.getElementById('classModal').classList.add('active');
        }
        
        function openEditModal(classData) {
            document.getElementById('modalTitle').textContent = 'Edit Class';
            document.getElementById('formAction').value = 'update';
            document.getElementById('timetableId').value = classData.timetable_id;
            document.getElementById('subject_name').value = classData.subject_name;
            document.getElementById('day_of_week').value = classData.day_of_week;
            document.getElementById('start_time').value = classData.start_time;
            document.getElementById('end_time').value = classData.end_time;
            document.getElementById('location').value = classData.location || '';
            document.getElementById('instructor').value = classData.instructor || '';
            document.getElementById('notes').value = classData.notes || '';
            document.getElementById('classModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('classModal').classList.remove('active');
        }
        
        function deleteClass(id) {
            if (confirm('Are you sure you want to delete this class?')) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
        
        document.getElementById('classModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
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