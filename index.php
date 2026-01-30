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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>University LMS - Learning Management System</title>
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
            color: var(--text);
            overflow-x: hidden;
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
            background: radial-gradient(circle at 20% 50%, rgba(99, 102, 241, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(236, 72, 153, 0.2) 0%, transparent 50%);
            animation: bgShift 20s ease infinite;
        }
        
        @keyframes bgShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }
        
        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        
        .header.scrolled {
            background: rgba(15, 23, 42, 0.95);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo-icon {
            font-size: 2rem;
            filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.5));
        }
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .nav-buttons a {
            padding: 0.75rem 1.75rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .nav-buttons a:first-child {
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .nav-buttons a:first-child:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .nav-buttons a.btn-register {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }
        
        .nav-buttons a.btn-register:hover {
            box-shadow: 0 6px 30px rgba(99, 102, 241, 0.6);
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8rem 2rem 4rem;
            overflow: hidden;
        }
        
        .hero-content {
            max-width: 1200px;
            text-align: center;
            position: relative;
            z-index: 10;
            animation: fadeInUp 1s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero h1 {
            font-size: 5rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            line-height: 1.1;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 50%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInUp 1s ease 0.2s backwards;
        }
        
        .hero p {
            font-size: 1.4rem;
            color: var(--text-muted);
            max-width: 700px;
            margin: 0 auto 3rem;
            line-height: 1.7;
            animation: fadeInUp 1s ease 0.4s backwards;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1.25rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease 0.6s backwards;
        }
        
        .cta-buttons a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1.25rem 2.5rem;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.4s ease;
            position: relative;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4);
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 14px;
            padding: 2px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 50px rgba(99, 102, 241, 0.6);
        }
        
        .btn-primary:hover::before {
            opacity: 1;
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-4px);
        }
        
        /* Stats Section */
        .stats {
            position: relative;
            padding: 4rem 2rem;
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stats-content {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            text-align: center;
        }
        
        .stat-item {
            transition: transform 0.3s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-10px);
        }
        
        .stat-item h3 {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-item p {
            font-size: 1.1rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        /* Features Section */
        .features {
            position: relative;
            max-width: 1400px;
            margin: 6rem auto;
            padding: 0 2rem;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 5rem;
        }
        
        .section-header h2 {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-header p {
            font-size: 1.2rem;
            color: var(--text-muted);
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            border-color: rgba(99, 102, 241, 0.5);
            box-shadow: 0 20px 60px rgba(99, 102, 241, 0.2);
        }
        
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
            transition: transform 0.4s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .feature-card p {
            color: var(--text-muted);
            line-height: 1.7;
        }
        
        /* How to Use Section */
        .how-to-use {
            position: relative;
            background: rgba(30, 41, 59, 0.3);
            padding: 6rem 2rem;
            margin-top: 6rem;
        }
        
        .how-to-use-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .steps {
            display: grid;
            gap: 2rem;
            margin-top: 4rem;
        }
        
        .step {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            padding: 2.5rem;
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.4s ease;
        }
        
        .step:hover {
            border-color: rgba(99, 102, 241, 0.5);
            transform: translateX(15px);
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.2);
        }
        
        .step-number {
            min-width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 900;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
            flex-shrink: 0;
        }
        
        .step-content h3 {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            font-weight: 700;
        }
        
        .step-content p {
            color: var(--text-muted);
            line-height: 1.7;
        }
        
        /* CTA Section */
        .cta-section {
            padding: 6rem 2rem;
            text-align: center;
        }
        
        .cta-section h2 {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .cta-section p {
            font-size: 1.2rem;
            color: var(--text-muted);
            margin-bottom: 2.5rem;
        }
        
        /* Footer */
        .footer {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem 2rem;
            text-align: center;
        }
        
        .footer h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .footer p {
            color: var(--text-muted);
            line-height: 1.8;
        }
        
        .footer-links {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 3rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-buttons {
                width: 100%;
                justify-content: center;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
            
            .step {
                flex-direction: column;
            }
            
            .step:hover {
                transform: translateY(-10px);
            }
            
            .section-header h2 {
                font-size: 2.5rem;
            }
            
            .cta-section h2 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .cta-buttons {
                flex-direction: column;
            }
            
            .cta-buttons a {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    
    <header class="header" id="header">
        <div class="header-content">
            <div class="logo">
                <span class="logo-icon">🎓</span>
                <span>University LMS</span>
            </div>
            <div class="nav-buttons">
                <a href="login.php">Login</a>
                <a href="register.php" class="btn-register">Register</a>
            </div>
        </div>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to the Future of Learning</h1>
            <p>Experience next-generation education with our comprehensive learning management system. Access courses, materials, and track your academic progress seamlessly.</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn-primary">Get Started Free →</a>
                <a href="login.php" class="btn-secondary">Sign In</a>
            </div>
        </div>
    </section>

    <section class="stats">
        <div class="stats-content">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>1000+</h3>
                    <p>Active Students</p>
                </div>
                <div class="stat-item">
                    <h3>50+</h3>
                    <p>Courses Available</p>
                </div>
                <div class="stat-item">
                    <h3>500+</h3>
                    <p>Study Materials</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Access Anytime</p>
                </div>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="section-header">
            <h2>Powerful Features</h2>
            <p>Everything you need for academic excellence</p>
        </div>
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Course Management</h3>
                <p>Browse and enroll in courses effortlessly. Access all your course materials, lectures, and resources in one organized space.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📄</div>
                <h3>Study Materials</h3>
                <p>Download lecture notes, past papers, assignments, and other study materials uploaded by instructors anytime, anywhere.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Progress Tracking</h3>
                <p>Monitor your academic progress, view grades, calculate GPA, and track your performance across all enrolled courses.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💾</div>
                <h3>Easy Downloads</h3>
                <p>Download PDFs and course materials with a single click. Access your content offline and study at your own pace.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔔</div>
                <h3>Real-time Notifications</h3>
                <p>Stay updated with course announcements, assignment deadlines, and important notifications from your instructors.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Mobile Optimized</h3>
                <p>Access the platform from any device. Fully responsive design ensures a great experience on mobile, tablet, and desktop.</p>
            </div>
        </div>
    </section>

    <section class="how-to-use">
        <div class="how-to-use-content">
            <div class="section-header">
                <h2>How It Works</h2>
                <p>Get started in just a few simple steps</p>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Create Your Account</h3>
                        <p>Register as a student using your university email address. Fill in your details and create a secure password. Admins are registered by system administrators for security purposes.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Login to Your Dashboard</h3>
                        <p>Use your credentials to access your personalized dashboard. Students and admins have different interfaces tailored to their needs and responsibilities.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Browse & Enroll in Courses</h3>
                        <p>Explore the catalog of available courses and enroll in the ones you need. View comprehensive course details including instructors, credits, and descriptions.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Access Course Materials</h3>
                        <p>Once enrolled, access all course materials including lecture notes, assignments, past papers, and additional resources uploaded by your instructors.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h3>Download & Study</h3>
                        <p>Download PDFs and materials to study offline. All downloads are tracked and you can access your download history anytime.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">6</div>
                    <div class="step-content">
                        <h3>Track Your Progress</h3>
                        <p>View your grades, calculate your GPA automatically, and monitor your academic performance throughout the semester. Stay on top of your academic goals.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <h2>Ready to Start Learning?</h2>
        <p>Join thousands of students already using our platform</p>
        <div class="cta-buttons">
            <a href="register.php" class="btn-primary">Create Account →</a>
            <a href="login.php" class="btn-secondary">Sign In</a>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-content">
            <h3>🎓 University LMS</h3>
            <p>&copy; 2024 University Learning Management System. All rights reserved.</p>
            <p>Empowering education through innovative technology</p>
            <div class="footer-links">
                <p>Built with ❤️ for students and educators</p>
            </div>
        </div>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', () => {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>