<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

// Check for flash messages
$flash = getFlashMessage();
if ($flash) {
    if ($flash['type'] === 'success') {
        $success = $flash['message'];
    } else {
        $error = $flash['message'];
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT user_id, username, password, full_name, user_type, status, email FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if account is active
            if ($user['status'] === 'inactive') {
                $error = 'Your account is inactive. Please contact the administrator.';
            } elseif (password_verify($password, $user['password'])) {
                // Password is correct - create session
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['last_activity'] = time();
                
                // Update last login timestamp
                $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Log the login activity
                logActivity($user['user_id'], 'login', 'User logged in successfully');
                
                // Set remember me cookie if checked
                if ($remember) {
                    setcookie('remember_user', $user['username'], time() + (86400 * 30), '/'); // 30 days
                }
                
                // Redirect based on user type
                if ($user['user_type'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: student_dashboard.php');
                }
                exit();
            } else {
                $error = 'Invalid username or password';
                
                // Log failed login attempt
                if (isset($user['user_id'])) {
                    logActivity($user['user_id'], 'failed_login', 'Failed login attempt');
                }
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Check for registration success
if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'Registration successful! Please login with your credentials.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - University LMS</title>
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
            --text: #f1f5f9;
            --text-muted: #94a3b8;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.4;
            background: radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.4) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.4) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(236, 72, 153, 0.3) 0%, transparent 50%);
            animation: bgShift 20s ease infinite;
        }
        
        @keyframes bgShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }
        
        .login-container {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 480px;
            padding: 3rem;
            animation: slideUp 0.6s ease;
            position: relative;
            z-index: 10;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 24px 24px 0 0;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            text-align: center;
            font-size: 3.5rem;
            margin-bottom: 0.5rem;
            animation: bounce 1s ease;
            filter: drop-shadow(0 0 20px rgba(99, 102, 241, 0.6));
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        h1 {
            text-align: center;
            color: var(--text);
            margin-bottom: 0.5rem;
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            text-align: center;
            color: var(--text-muted);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            animation: slideIn 0.4s ease;
            border: 1px solid;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
            border-left: 4px solid #ef4444;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.3);
            border-left: 4px solid #22c55e;
        }
        
        .demo-credentials {
            background: rgba(99, 102, 241, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        
        .demo-credentials h4 {
            color: var(--text);
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
        }
        
        .demo-credentials p {
            color: var(--text-muted);
            margin: 0.5rem 0;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .demo-credentials p:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(99, 102, 241, 0.3);
        }
        
        .demo-credentials strong {
            color: var(--primary);
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        input {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            color: var(--text);
        }
        
        input::placeholder {
            color: var(--text-muted);
        }
        
        input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.2);
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .remember-me input[type="checkbox"] {
            width: auto;
            cursor: pointer;
            accent-color: var(--primary);
        }
        
        .remember-me label {
            margin: 0;
            cursor: pointer;
            font-weight: 400;
            color: var(--text-muted);
        }
        
        .btn {
            width: 100%;
            padding: 1.1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s;
            font-family: 'Inter', sans-serif;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(99, 102, 241, 0.6);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .links {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        
        .links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .links a:hover {
            color: var(--accent);
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.85rem;
            position: relative;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .divider::before {
            left: 0;
        }
        
        .divider::after {
            right: 0;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .logo {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    
    <div class="login-container">
        <div class="logo">🎓</div>
        <h1>Welcome Back</h1>
        <p class="subtitle">Sign in to access your account</p>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✓ Success:</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="demo-credentials">
            <h4>🔑 Demo Credentials</h4>
            <p><strong>Admin:</strong> admin / admin123</p>
            <p><strong>Student:</strong> john_doe / student123</p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    autofocus
                    placeholder="Enter your username or email"
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    placeholder="Enter your password"
                >
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me for 30 days</label>
            </div>
            
            <button type="submit" class="btn">Sign In</button>
        </form>
        
        <div class="divider">or</div>
        
        <div class="links">
            Don't have an account? <a href="register.php">Register here</a>
            <br><br>
            <a href="index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>