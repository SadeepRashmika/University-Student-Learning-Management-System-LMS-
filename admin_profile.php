<?php
require_once 'config.php';
requireAdmin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$success_message = '';
$error_message = '';

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['profile_photo']['type'];
        $file_size = $_FILES['profile_photo']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error_message = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
        } elseif ($file_size > $max_size) {
            $error_message = "File size must be less than 5MB.";
        } else {
            $upload_dir = 'uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_admin_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                // Delete old profile photo if exists
                $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $old_data = $result->fetch_assoc();
                $stmt->close();
                
                if ($old_data['profile_image'] && file_exists($old_data['profile_image'])) {
                    unlink($old_data['profile_image']);
                }
                
                // Update database
                $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                $stmt->bind_param("si", $upload_path, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Profile photo updated successfully!";
                    logActivity($conn, $user_id, 'update_profile_photo', 'Updated profile photo');
                } else {
                    $error_message = "Error updating profile photo in database.";
                }
                $stmt->close();
            } else {
                $error_message = "Error uploading file.";
            }
        }
    } else {
        $error_message = "Please select a photo to upload.";
    }
}

// Handle profile photo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_photo'])) {
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_data = $result->fetch_assoc();
    $stmt->close();
    
    if ($old_data['profile_image'] && file_exists($old_data['profile_image'])) {
        unlink($old_data['profile_image']);
    }
    
    $stmt = $conn->prepare("UPDATE users SET profile_image = NULL WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Profile photo removed successfully!";
        logActivity($conn, $user_id, 'remove_profile_photo', 'Removed profile photo');
    } else {
        $error_message = "Error removing profile photo.";
    }
    $stmt->close();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    if (empty($full_name) || empty($email)) {
        $error_message = "Full name and email are required.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Email is already taken by another user.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $full_name, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name; // Update session
                $success_message = "Profile updated successfully!";
                logActivity($conn, $user_id, 'update_profile', 'Updated profile information');
            } else {
                $error_message = "Error updating profile.";
            }
        }
        $stmt->close();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!password_verify($current_password, $user['password'])) {
        $error_message = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $success_message = "Password changed successfully!";
            logActivity($conn, $user_id, 'change_password', 'Changed password');
        } else {
            $error_message = "Error changing password.";
        }
        $stmt->close();
    }
}

// Get admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Get admin statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE user_type = 'student'");
$stmt->execute();
$result = $stmt->get_result();
$total_students = $result->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM courses WHERE status = 'active'");
$stmt->execute();
$result = $stmt->get_result();
$total_courses = $result->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM announcements");
$stmt->execute();
$result = $stmt->get_result();
$total_announcements = $result->fetch_assoc()['total'];
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - University LMS</title>
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
            --error: #ef4444;
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
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
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
        
        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-10px);
            }
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
        
        .profile-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
        }
        
        .profile-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }
        
        .profile-avatar-container {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto 1.5rem;
        }
        
        .profile-avatar {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 900;
            font-size: 3.5rem;
            box-shadow: 0 8px 30px rgba(236, 72, 153, 0.4);
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .camera-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid var(--dark-light);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.4);
        }
        
        .camera-overlay:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(236, 72, 153, 0.6);
        }
        
        .camera-overlay span {
            font-size: 1.3rem;
        }
        
        .profile-name {
            text-align: center;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--text);
        }
        
        .profile-role {
            text-align: center;
            color: var(--accent);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--accent);
            display: block;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.25rem;
        }
        
        .photo-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .btn-photo {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .btn-upload {
            background: rgba(236, 72, 153, 0.15);
            color: var(--accent);
            border: 1px solid rgba(236, 72, 153, 0.3);
        }
        
        .btn-upload:hover {
            background: rgba(236, 72, 153, 0.25);
            transform: translateY(-2px);
        }
        
        .btn-remove {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        
        .btn-remove:hover {
            background: rgba(239, 68, 68, 0.25);
            transform: translateY(-2px);
        }
        
        .btn-remove:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .form-section {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }
        
        .form-section h2 {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.875rem 1.25rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(15, 23, 42, 0.7);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.1);
        }
        
        .form-control:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--secondary));
            color: white;
            box-shadow: 0 4px 15px rgba(236, 72, 153, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(236, 72, 153, 0.4);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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
        
        #photoUploadInput {
            display: none;
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
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 400px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-content h3 {
            color: var(--text);
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        
        .modal-content p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
        
        .modal-actions {
            display: flex;
            gap: 1rem;
        }
        
        .modal-actions button {
            flex: 1;
        }
        
        @media (max-width: 1200px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .profile-card {
                position: relative;
                top: 0;
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
            
            .form-actions, .modal-actions {
                flex-direction: column;
            }
            
            .btn {
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
            <a href="admin_announcements.php" class="nav-item">
                <span class="icon">📢</span>
                <span>Announcements</span>
            </a>
            
            <div class="nav-section-title">Account</div>
            
            <a href="admin_profile.php" class="nav-item active">
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
            <h1>👤 Admin Profile</h1>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span>⚠</span>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="profile-layout">
            <div class="profile-card">
                <div class="profile-avatar-container">
                    <div class="profile-avatar">
                        <?php if ($admin['profile_image'] && file_exists($admin['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($admin['profile_image']); ?>" alt="Profile Photo">
                        <?php else: ?>
                            <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="camera-overlay" onclick="document.getElementById('photoUploadInput').click()">
                        <span>📷</span>
                    </div>
                </div>
                
                <div class="profile-name"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                <div class="profile-role">👑 Administrator</div>
                
                <div class="photo-actions">
                    <form method="POST" enctype="multipart/form-data" id="uploadPhotoForm" style="flex: 1;">
                        <input type="file" name="profile_photo" id="photoUploadInput" accept="image/*" onchange="handlePhotoSelect(this)">
                        <button type="button" class="btn-photo btn-upload" onclick="document.getElementById('photoUploadInput').click()">
                            📤 Upload Photo
                        </button>
                    </form>
                    <form method="POST" id="removePhotoForm" style="flex: 1;">
                        <button type="button" class="btn-photo btn-remove" onclick="confirmRemovePhoto()" <?php echo (!$admin['profile_image']) ? 'disabled' : ''; ?>>
                            🗑️ Remove
                        </button>
                    </form>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $total_students; ?></span>
                        <span class="stat-label">Students</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $total_courses; ?></span>
                        <span class="stat-label">Courses</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $total_announcements; ?></span>
                        <span class="stat-label">Posts</span>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="form-section">
                    <h2>📝 Profile Information</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn btn-primary">💾 Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <div class="form-section">
                    <h2>🔒 Change Password</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="change_password" class="btn btn-primary">🔑 Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Remove Photo Confirmation Modal -->
    <div class="modal" id="removePhotoModal">
        <div class="modal-content">
            <h3>🗑️ Remove Profile Photo</h3>
            <p>Are you sure you want to remove your profile photo? This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="closeModal()">Cancel</button>
                <button class="btn-photo btn-remove" onclick="removePhoto()">Remove Photo</button>
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
        
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
        
        function handlePhotoSelect(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileSize = file.size / 1024 / 1024;
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                    input.value = '';
                    return;
                }
                
                if (fileSize > 5) {
                    alert('File size must be less than 5MB.');
                    input.value = '';
                    return;
                }
                
                const uploadForm = document.getElementById('uploadPhotoForm');
                const formData = new FormData(uploadForm);
                formData.append('upload_photo', '1');
                
                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    window.location.reload();
                });
            }
        }
        
        function confirmRemovePhoto() {
            document.getElementById('removePhotoModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('removePhotoModal').classList.remove('active');
        }
        
        function removePhoto() {
            const removeForm = document.getElementById('removePhotoForm');
            const formData = new FormData();
            formData.append('remove_photo', '1');
            
            fetch('', {
                method: 'POST',
                body: formData
            }).then(() => {
                window.location.reload();
            });
        }
    </script>
</body>
</html>