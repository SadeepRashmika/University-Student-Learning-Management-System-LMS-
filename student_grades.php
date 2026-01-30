<?php
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Grade to GPA mapping
function getGradePoint($grade) {
    $gradePoints = [
        'A+' => 4.00, 'A' => 4.00, 'A-' => 3.70,
        'B+' => 3.30, 'B' => 3.00, 'B-' => 2.70,
        'C+' => 2.30, 'C' => 2.00, 'C-' => 1.70,
        'D+' => 1.30, 'D' => 1.00,
        'F' => 0.00
    ];
    return isset($gradePoints[$grade]) ? $gradePoints[$grade] : 0.00;
}

// Get degree classification
function getDegreeClass($gpa) {
    if ($gpa >= 3.70) return ['class' => 'First Class Honours', 'color' => '#10b981'];
    if ($gpa >= 3.30) return ['class' => 'Second Class Upper', 'color' => '#3b82f6'];
    if ($gpa >= 3.00) return ['class' => 'Second Class Lower', 'color' => '#8b5cf6'];
    if ($gpa >= 2.00) return ['class' => 'Third Class', 'color' => '#f59e0b'];
    if ($gpa >= 1.00) return ['class' => 'Pass', 'color' => '#64748b'];
    return ['class' => 'Fail', 'color' => '#ef4444'];
}

// Function to parse semester for sorting
function parseSemester($semester) {
    // Extract year and semester number from format like "1 Year 1 semester"
    preg_match('/(\d+)\s*Year\s*(\d+)\s*semester/i', $semester, $matches);
    if (count($matches) >= 3) {
        return ($matches[1] * 10) + $matches[2]; // e.g., "2 Year 1 semester" = 21
    }
    return 0;
}

$success_message = '';
$error_message = '';

// Handle Remove All Courses
if (isset($_GET['remove_all'])) {
    // Get all course_ids for this student
    $stmt = $conn->prepare("SELECT course_id FROM enrollments WHERE student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $course_ids = [];
    while ($row = $result->fetch_assoc()) {
        $course_ids[] = $row['course_id'];
    }
    $stmt->close();
    
    // Delete all enrollments for this student
    $stmt = $conn->prepare("DELETE FROM enrollments WHERE student_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $stmt->close();
        
        // Clean up orphaned courses (courses with no enrollments)
        foreach ($course_ids as $course_id) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            if ($row['count'] == 0) {
                $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $success_message = "All courses removed successfully!";
    } else {
        $error_message = "Error removing courses.";
        $stmt->close();
    }
}

// Handle form submission for adding courses
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_course'])) {
    $course_code = trim($_POST['course_code']);
    $course_name = trim($_POST['course_name']);
    $credits = intval($_POST['credits']);
    $grade = trim($_POST['grade']);
    $semester = trim($_POST['semester']);
    $year = intval($_POST['year']);
    
    if (!empty($course_code) && !empty($course_name) && $credits > 0) {
        // ALWAYS create a new course entry - each enrollment gets its own course record
        // This prevents conflicts and allows multiple students to have the same course code
        $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, credits, semester, year, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssissi", $course_code, $course_name, $credits, $semester, $year, $user_id);
        
        if ($stmt->execute()) {
            $course_id = $stmt->insert_id;
            $stmt->close();
            
            // Create new enrollment for this student (ONLY ONCE)
            $status = empty($grade) ? 'enrolled' : 'completed';
            $gpa_value = empty($grade) ? null : getGradePoint($grade);
            
            $stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, grade, status, gpa) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissd", $user_id, $course_id, $grade, $status, $gpa_value);
            
            if ($stmt->execute()) {
                $success_message = "Course added successfully!";
            } else {
                $error_message = "Error adding enrollment: " . $stmt->error;
            }
            $stmt->close();
        } else {
            // Check if it's a duplicate course_code error (UNIQUE constraint still exists)
            if (strpos($stmt->error, 'Duplicate entry') !== false && strpos($stmt->error, 'course_code') !== false) {
                $error_message = "⚠️ Database Error: Please run this SQL command in phpMyAdmin:<br><code>ALTER TABLE courses DROP INDEX course_code;</code><br>Then try again.";
            } else {
                $error_message = "Error creating course: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// Handle course editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_course'])) {
    $enrollment_id = intval($_POST['enrollment_id']);
    $grade = trim($_POST['edit_grade']);
    
    $status = empty($grade) ? 'enrolled' : 'completed';
    $gpa_value = empty($grade) ? null : getGradePoint($grade);
    
    $stmt = $conn->prepare("UPDATE enrollments SET grade = ?, status = ?, gpa = ? WHERE enrollment_id = ? AND student_id = ?");
    $stmt->bind_param("ssdii", $grade, $status, $gpa_value, $enrollment_id, $user_id);
    
    if ($stmt->execute()) {
        $success_message = "Grade updated successfully!";
    } else {
        $error_message = "Error updating grade.";
    }
    $stmt->close();
}

// Handle course deletion
if (isset($_GET['delete_enrollment'])) {
    $enrollment_id = intval($_GET['delete_enrollment']);
    
    // Get course_id before deleting enrollment
    $stmt = $conn->prepare("SELECT course_id FROM enrollments WHERE enrollment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $enrollment_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $enrollment = $result->fetch_assoc();
        $course_id = $enrollment['course_id'];
        $stmt->close();
        
        // Delete the enrollment
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $enrollment_id, $user_id);
        
        if ($stmt->execute()) {
            $stmt->close();
            
            // Check if this course has any other enrollments
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE course_id = ?");
            $stmt->bind_param("i", $course_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            // If no other enrollments exist, delete the course as well (cleanup)
            if ($row['count'] == 0) {
                $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                $stmt->execute();
                $stmt->close();
            }
            
            $success_message = "Course removed successfully!";
        } else {
            $stmt->close();
            $error_message = "Error removing course.";
        }
    } else {
        $stmt->close();
        $error_message = "Course not found.";
    }
}

// Get all enrolled courses with grades - ORDERED BY SEMESTER
$stmt = $conn->prepare("
    SELECT 
        e.enrollment_id,
        c.course_code,
        c.course_name,
        c.credits,
        e.grade,
        e.status,
        c.semester,
        c.year
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    ORDER BY c.semester ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Sort courses by semester order using custom function
usort($courses, function($a, $b) {
    return parseSemester($a['semester']) - parseSemester($b['semester']);
});

// Calculate overall GPA and group by semester
$totalCredits = 0;
$totalPoints = 0;
$completedCredits = 0;
$semesterGPAs = [];

foreach ($courses as $course) {
    $semester = $course['semester'];
    
    if (!isset($semesterGPAs[$semester])) {
        $semesterGPAs[$semester] = [
            'credits' => 0,
            'points' => 0,
            'courses' => []
        ];
    }
    
    $semesterGPAs[$semester]['courses'][] = $course;
    
    if ($course['status'] == 'completed' && $course['grade']) {
        $credits = $course['credits'];
        $gradePoint = getGradePoint($course['grade']);
        
        // Overall totals
        $totalCredits += $credits;
        $totalPoints += ($gradePoint * $credits);
        $completedCredits += $credits;
        
        // Semester totals
        $semesterGPAs[$semester]['credits'] += $credits;
        $semesterGPAs[$semester]['points'] += ($gradePoint * $credits);
    }
}

// Calculate GPA for each semester
foreach ($semesterGPAs as $semester => &$data) {
    $data['gpa'] = $data['credits'] > 0 ? round($data['points'] / $data['credits'], 2) : 0.00;
}

$overallGPA = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.00;
$degreeInfo = getDegreeClass($overallGPA);

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();

// Handle PDF download
if (isset($_GET['download_pdf'])) {
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Academic Transcript - <?php echo htmlspecialchars($student['full_name']); ?></title>
    <style>
        @media print {
            @page { 
                margin: 1.5cm;
                size: A4;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
        
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            max-width: 210mm;
            margin: 0 auto;
            padding: 20px;
            background: white;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 22pt;
            font-weight: bold;
            color: #1a1a1a;
        }
        
        .header h2 {
            margin: 8px 0 0 0;
            font-size: 16pt;
            font-weight: normal;
            color: #333;
        }
        
        .info-section {
            margin: 20px 0;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .info-row {
            margin: 6px 0;
            display: flex;
        }
        
        .info-label {
            font-weight: bold;
            width: 150px;
            color: #333;
        }
        
        .summary-box {
            background: #e8f4f8;
            border: 2px solid #0066cc;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            border-radius: 8px;
        }
        
        .summary-box h3 {
            margin: 0 0 15px 0;
            color: #0066cc;
        }
        
        .gpa-value {
            font-size: 28pt;
            font-weight: bold;
            color: #0066cc;
            margin: 10px 0;
        }
        
        .degree-class {
            font-size: 14pt;
            font-weight: bold;
            color: #006600;
            margin: 10px 0;
        }
        
        .summary-details {
            margin-top: 15px;
            font-size: 11pt;
        }
        
        .section-title {
            background: #333;
            color: white;
            padding: 8px 12px;
            margin: 25px 0 15px 0;
            font-weight: bold;
            font-size: 12pt;
            border-radius: 3px;
        }
        
        .semester-section {
            margin: 20px 0;
            page-break-inside: avoid;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .semester-header {
            background: #4a5568;
            color: white;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 11pt;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 5px 5px 0 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 10pt;
        }
        
        table th {
            background: #2d3748;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #2d3748;
        }
        
        table td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            background: inherit;
        }
        
        table tbody tr:nth-child(even) {
            background: #f5f5f5;
        }
        
        table tbody tr:nth-child(odd) {
            background: #ffffff;
        }
        
        .grade-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 15px 0;
            font-size: 10pt;
        }
        
        .classification-info {
            margin: 15px 0;
            font-size: 10pt;
        }
        
        .classification-info div {
            margin: 8px 0;
            padding-left: 15px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #0066cc;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #0052a3;
        }
        
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 12px 24px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .back-button:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <a href="student_grades.php" class="back-button no-print">← Back</a>
    <button onclick="window.print()" class="print-button no-print">🖨️ Print / Save as PDF</button>
    
    <div class="header">
        <h1>UNIVERSITY LMS</h1>
        <h2>OFFICIAL ACADEMIC TRANSCRIPT</h2>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Student Name:</span>
            <span><?php echo htmlspecialchars($student['full_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Student ID:</span>
            <span><?php echo htmlspecialchars($student['student_id']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span><?php echo htmlspecialchars($student['email']); ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date Generated:</span>
            <span><?php echo date('F j, Y'); ?></span>
        </div>
    </div>
    
    <div class="summary-box">
        <h3>ACADEMIC SUMMARY</h3>
        <div class="gpa-value"><?php echo number_format($overallGPA, 2); ?></div>
        <div class="degree-class"><?php echo $degreeInfo['class']; ?></div>
        <div class="summary-details">
            <div><strong>Total Credits Completed:</strong> <?php echo $completedCredits; ?></div>
            <div><strong>Total Courses:</strong> <?php echo count($courses); ?></div>
        </div>
    </div>
    
    <div class="section-title">COURSE RECORDS BY SEMESTER</div>
    
    <?php foreach ($semesterGPAs as $semester => $semData): ?>
    <div class="semester-section">
        <div class="semester-header">
            <span><?php echo htmlspecialchars($semester); ?></span>
            <span>Semester GPA: <?php echo number_format($semData['gpa'], 2); ?></span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th style="text-align: center;">Credits</th>
                    <th style="text-align: center;">Grade</th>
                    <th style="text-align: center;">Grade Point</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($semData['courses'] as $course): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                    <td style="text-align: center;"><?php echo $course['credits']; ?></td>
                    <td style="text-align: center;"><strong><?php echo $course['grade'] ?: 'Pending'; ?></strong></td>
                    <td style="text-align: center;"><?php echo $course['grade'] ? number_format(getGradePoint($course['grade']), 2) : '-'; ?></td>
                    <td><?php echo ucfirst($course['status']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
    
    <div class="section-title">GRADING SCALE</div>
    <div class="grade-info">
        <div><strong>A+/A:</strong> 4.00</div>
        <div><strong>A-:</strong> 3.70</div>
        <div><strong>B+:</strong> 3.30</div>
        <div><strong>B:</strong> 3.00</div>
        <div><strong>B-:</strong> 2.70</div>
        <div><strong>C+:</strong> 2.30</div>
        <div><strong>C:</strong> 2.00</div>
        <div><strong>C-:</strong> 1.70</div>
        <div><strong>D+:</strong> 1.30</div>
        <div><strong>D:</strong> 1.00</div>
        <div><strong>F:</strong> 0.00</div>
    </div>
    
    <div class="section-title">DEGREE CLASSIFICATION</div>
    <div class="classification-info">
        <div>• <strong>First Class Honours:</strong> GPA ≥ 3.70</div>
        <div>• <strong>Second Class Upper:</strong> GPA 3.30 - 3.69</div>
        <div>• <strong>Second Class Lower:</strong> GPA 3.00 - 3.29</div>
        <div>• <strong>Third Class:</strong> GPA 2.00 - 2.99</div>
        <div>• <strong>Pass:</strong> GPA 1.00 - 1.99</div>
        <div>• <strong>Fail:</strong> GPA < 1.00</div>
    </div>
    
    <div class="footer">
        <p>This is an official academic transcript generated from University LMS</p>
        <p>Generated on <?php echo date('F j, Y g:i A'); ?></p>
    </div>
</body>
</html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades & GPA - University LMS</title>
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
            --warning: #f59e0b;
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
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .page-header h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
        }
        
        .btn-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .btn-warning:hover {
            background: rgba(245, 158, 11, 0.25);
        }
        
        .btn-info {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-info:hover {
            background: rgba(59, 130, 246, 0.25);
        }
        
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
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
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: rgba(30, 41, 59, 0.5);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .summary-card .label {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }
        
        .summary-card .value {
            font-size: 3rem;
            font-weight: 900;
            color: var(--text);
            line-height: 1;
        }
        
        .summary-card.gpa .value {
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .degree-class {
            margin-top: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.95rem;
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
            justify-content: space-between;
            align-items: center;
        }
        
        .semester-header {
            background: rgba(99, 102, 241, 0.15);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        
        .semester-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .semester-gpa {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--success);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
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
        .form-group select {
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(15, 23, 42, 0.7);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .courses-table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
        }
        
        .courses-table thead {
            background: rgba(15, 23, 42, 0.8);
        }
        
        .courses-table th {
            padding: 1rem;
            text-align: left;
            color: var(--text);
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .courses-table td {
            padding: 1rem;
            color: var(--text-muted);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .courses-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .courses-table tbody tr:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        
        .grade-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 700;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .grade-a { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .grade-b { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        .grade-c { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
        .grade-d { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }
        .grade-f { background: rgba(127, 29, 29, 0.3); color: #ef4444; }
        .grade-pending { background: rgba(100, 116, 139, 0.2); color: #94a3b8; }
        
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-weight: 600;
            display: inline-block;
            font-size: 0.85rem;
        }
        
        .status-completed { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .status-enrolled { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: var(--dark-light);
            margin: 10% auto;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-header h2 {
            color: var(--text);
            font-size: 1.5rem;
        }
        
        .close {
            color: var(--text-muted);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: var(--text);
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
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
            <a href="browse_courses.php" class="nav-item">
                <span class="icon">🔍</span>
                <span>Browse Courses</span>
            </a>                
            <a href="student_materials.php" class="nav-item">
                <span class="icon">📄</span>
                <span>Course Materials</span>
            </a>
            <a href="student_grades.php" class="nav-item active">
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
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <span>✓</span>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <span>✗</span>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1>📈 Grades & GPA Management</h1>
            <div class="header-actions">
                <a href="?download_pdf=1" class="btn btn-primary">
                    <span>📥</span>
                    Download PDF
                </a>
                <?php if (count($courses) > 0): ?>
                <a href="?remove_all=1" class="btn btn-warning" onclick="return confirm('⚠️ Are you sure you want to remove ALL courses? This action cannot be undone!')">
                    <span>🗑️</span>
                    Remove All Courses
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="summary-grid">
            <div class="summary-card gpa">
                <div class="label">Overall GPA</div>
                <div class="value"><?php echo number_format($overallGPA, 2); ?></div>
                <div class="degree-class" style="background: <?php echo $degreeInfo['color']; ?>20; color: <?php echo $degreeInfo['color']; ?>; border: 1px solid <?php echo $degreeInfo['color']; ?>40;">
                    <?php echo $degreeInfo['class']; ?>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="label">Credits Completed</div>
                <div class="value"><?php echo $completedCredits; ?></div>
            </div>
            
            <div class="summary-card">
                <div class="label">Total Courses</div>
                <div class="value"><?php echo count($courses); ?></div>
            </div>
            
            <div class="summary-card">
                <div class="label">Total Semesters</div>
                <div class="value"><?php echo count($semesterGPAs); ?></div>
            </div>
        </div>
        
        <div class="content-section">
            <h2 class="section-title">➕ Add New Course & Grade</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="course_code">Course Code *</label>
                        <input type="text" id="course_code" name="course_code" required placeholder="e.g., CS101">
                    </div>
                    
                    <div class="form-group">
                        <label for="course_name">Course Name *</label>
                        <input type="text" id="course_name" name="course_name" required placeholder="e.g., Introduction to Programming">
                    </div>
                    
                    <div class="form-group">
                        <label for="credits">Credits *</label>
                        <input type="number" id="credits" name="credits" min="1" max="10" value="3" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="grade">Grade</label>
                        <select id="grade" name="grade">
                            <option value="">Not Graded Yet</option>
                            <option value="A+">A+ (4.00)</option>
                            <option value="A">A (4.00)</option>
                            <option value="A-">A- (3.70)</option>
                            <option value="B+">B+ (3.30)</option>
                            <option value="B">B (3.00)</option>
                            <option value="B-">B- (2.70)</option>
                            <option value="C+">C+ (2.30)</option>
                            <option value="C">C (2.00)</option>
                            <option value="C-">C- (1.70)</option>
                            <option value="D+">D+ (1.30)</option>
                            <option value="D">D (1.00)</option>
                            <option value="F">F (0.00)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="semester">Semester *</label>
                        <select id="semester" name="semester" required>
                            <option value="1 Year 1 semester">1 Year 1 semester</option>
                            <option value="1 Year 2 semester">1 Year 2 semester</option>
                            <option value="2 Year 1 semester">2 Year 1 semester</option>
                            <option value="2 Year 2 semester">2 Year 2 semester</option>
                            <option value="3 Year 1 semester">3 Year 1 semester</option>
                            <option value="3 Year 2 semester">3 Year 2 semester</option>
                            <option value="4 Year 1 semester">4 Year 1 semester</option>
                            <option value="4 Year 2 semester">4 Year 2 semester</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_course" class="btn btn-primary">
                        <span>➕</span>
                        Add Course
                    </button>
                </div>
            </form>
        </div>
        
        <div class="content-section">
            <h2 class="section-title">📚 My Courses & Grades by Semester</h2>
            <?php if (empty($courses)): ?>
                <div class="no-data">No courses added yet. Add your first course above!</div>
            <?php else: ?>
                <?php foreach ($semesterGPAs as $semester => $semData): ?>
                <div class="semester-header">
                    <span class="semester-title"><?php echo htmlspecialchars($semester); ?></span>
                    <span class="semester-gpa">Semester GPA: <?php echo number_format($semData['gpa'], 2); ?></span>
                </div>
                <div style="overflow-x: auto; margin-bottom: 2rem;">
                    <table class="courses-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Credits</th>
                                <th>Grade</th>
                                <th>Grade Point</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($semData['courses'] as $course): ?>
                                <tr>
                                    <td style="font-weight: 600; color: var(--primary);"><?php echo htmlspecialchars($course['course_code']); ?></td>
                                    <td style="color: var(--text);"><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td style="font-weight: 600;"><?php echo $course['credits']; ?></td>
                                    <td>
                                        <?php if ($course['grade']): ?>
                                            <?php
                                                $gradeClass = 'grade-pending';
                                                if (in_array($course['grade'], ['A+', 'A', 'A-'])) $gradeClass = 'grade-a';
                                                elseif (in_array($course['grade'], ['B+', 'B', 'B-'])) $gradeClass = 'grade-b';
                                                elseif (in_array($course['grade'], ['C+', 'C', 'C-'])) $gradeClass = 'grade-c';
                                                elseif (in_array($course['grade'], ['D+', 'D'])) $gradeClass = 'grade-d';
                                                elseif ($course['grade'] == 'F') $gradeClass = 'grade-f';
                                            ?>
                                            <span class="grade-badge <?php echo $gradeClass; ?>">
                                                <?php echo htmlspecialchars($course['grade']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="grade-badge grade-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 600; color: var(--text);">
                                        <?php echo $course['grade'] ? number_format(getGradePoint($course['grade']), 2) : '-'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $course['status']; ?>">
                                            <?php echo ucfirst($course['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="showGPAInfo('<?php echo htmlspecialchars($course['course_code']); ?>', '<?php echo $course['grade'] ?: 'N/A'; ?>', '<?php echo $course['grade'] ? number_format(getGradePoint($course['grade']), 2) : '0.00'; ?>', <?php echo $course['credits']; ?>)" class="btn-success">
                                                📊 GPA
                                            </button>
                                            <button onclick="openEditModal(<?php echo $course['enrollment_id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>', '<?php echo $course['grade']; ?>')" class="btn-info">
                                                ✏️ Edit
                                            </button>
                                            <a href="?delete_enrollment=<?php echo $course['enrollment_id']; ?>" 
                                               class="btn-danger" 
                                               onclick="return confirm('Are you sure you want to remove this course?')">
                                                🗑️ Remove
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="content-section">
            <h2 class="section-title">📊 Grading Information</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div>
                    <h3 style="color: var(--text); margin-bottom: 1rem; font-size: 1.1rem;">Grading Scale</h3>
                    <div style="background: rgba(15, 23, 42, 0.5); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1);">
                        <div style="display: grid; gap: 0.5rem;">
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                <span style="font-weight: 600;">A+/A</span>
                                <span style="color: var(--primary);">4.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                <span style="font-weight: 600;">A-</span>
                                <span style="color: var(--primary);">3.70</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                <span style="font-weight: 600;">B+</span>
                                <span style="color: var(--primary);">3.30</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                <span style="font-weight: 600;">B</span>
                                <span style="color: var(--primary);">3.00</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255, 255, 255, 0.05);">
                                <span style="font-weight: 600;">B-</span>
                                <span style="color: var(--primary);">2.70</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                                <span style="font-weight: 600;">C+ to F</span>
                                <span style="color: var(--primary);">2.30 - 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 style="color: var(--text); margin-bottom: 1rem; font-size: 1.1rem;">Degree Classification</h3>
                    <div style="background: rgba(15, 23, 42, 0.5); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1);">
                        <div style="display: grid; gap: 0.75rem;">
                            <div style="padding: 0.75rem; background: rgba(16, 185, 129, 0.1); border-left: 3px solid #10b981; border-radius: 6px;">
                                <div style="font-weight: 700; color: #34d399;">First Class Honours</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">GPA ≥ 3.70</div>
                            </div>
                            <div style="padding: 0.75rem; background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; border-radius: 6px;">
                                <div style="font-weight: 700; color: #60a5fa;">Second Class Upper</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">GPA 3.30 - 3.69</div>
                            </div>
                            <div style="padding: 0.75rem; background: rgba(139, 92, 246, 0.1); border-left: 3px solid #8b5cf6; border-radius: 6px;">
                                <div style="font-weight: 700; color: #a78bfa;">Second Class Lower</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">GPA 3.00 - 3.29</div>
                            </div>
                            <div style="padding: 0.75rem; background: rgba(245, 158, 11, 0.1); border-left: 3px solid #f59e0b; border-radius: 6px;">
                                <div style="font-weight: 700; color: #fbbf24;">Third Class</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">GPA 2.00 - 2.99</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- GPA Info Modal -->
    <div id="gpaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📊 GPA Breakdown</h2>
                <span class="close" onclick="closeGPAModal()">&times;</span>
            </div>
            <div id="gpaModalBody" style="color: var(--text); line-height: 1.8;">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Edit Grade Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✏️ Edit Grade</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" id="edit_enrollment_id" name="enrollment_id">
                <div class="form-group">
                    <label for="edit_course_display">Course</label>
                    <input type="text" id="edit_course_display" disabled style="background: rgba(15, 23, 42, 0.8);">
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="edit_grade">New Grade</label>
                    <select id="edit_grade" name="edit_grade" required>
                        <option value="">Not Graded Yet</option>
                        <option value="A+">A+ (4.00)</option>
                        <option value="A">A (4.00)</option>
                        <option value="A-">A- (3.70)</option>
                        <option value="B+">B+ (3.30)</option>
                        <option value="B">B (3.00)</option>
                        <option value="B-">B- (2.70)</option>
                        <option value="C+">C+ (2.30)</option>
                        <option value="C">C (2.00)</option>
                        <option value="C-">C- (1.70)</option>
                        <option value="D+">D+ (1.30)</option>
                        <option value="D">D (1.00)</option>
                        <option value="F">F (0.00)</option>
                    </select>
                </div>
                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button type="submit" name="edit_course" class="btn btn-primary">
                        <span>💾</span>
                        Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-danger">
                        <span>✖</span>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showGPAInfo(courseCode, grade, gradePoint, credits) {
            const modal = document.getElementById('gpaModal');
            const modalBody = document.getElementById('gpaModalBody');
            
            const contribution = grade !== 'N/A' ? (parseFloat(gradePoint) * credits).toFixed(2) : '0.00';
            
            modalBody.innerHTML = `
                <div style="background: rgba(15, 23, 42, 0.5); padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem;">
                    <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem;">
                        ${courseCode}
                    </div>
                    <div style="display: grid; gap: 1rem;">
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: rgba(99, 102, 241, 0.1); border-radius: 8px;">
                            <span style="color: var(--text-muted);">Grade:</span>
                            <span style="font-weight: 700; color: var(--text);">${grade}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: rgba(99, 102, 241, 0.1); border-radius: 8px;">
                            <span style="color: var(--text-muted);">Grade Point:</span>
                            <span style="font-weight: 700; color: var(--text);">${gradePoint}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: rgba(99, 102, 241, 0.1); border-radius: 8px;">
                            <span style="color: var(--text-muted);">Credits:</span>
                            <span style="font-weight: 700; color: var(--text);">${credits}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem; background: rgba(16, 185, 129, 0.15); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.3);">
                            <span style="color: var(--text-muted);">GPA Contribution:</span>
                            <span style="font-weight: 700; color: #34d399;">${contribution}</span>
                        </div>
                    </div>
                </div>
                <div style="background: rgba(99, 102, 241, 0.1); padding: 1rem; border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.3);">
                    <div style="font-size: 0.9rem; color: var(--text-muted);">
                        <strong>Formula:</strong> GPA Contribution = Grade Point × Credits<br>
                        ${contribution} = ${gradePoint} × ${credits}
                    </div>
                </div>
            `;
            
            modal.style.display = 'block';
        }
        
        function closeGPAModal() {
            document.getElementById('gpaModal').style.display = 'none';
        }
        
        function openEditModal(enrollmentId, courseCode, currentGrade) {
            const modal = document.getElementById('editModal');
            document.getElementById('edit_enrollment_id').value = enrollmentId;
            document.getElementById('edit_course_display').value = courseCode;
            document.getElementById('edit_grade').value = currentGrade || '';
            modal.style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const gpaModal = document.getElementById('gpaModal');
            const editModal = document.getElementById('editModal');
            if (event.target == gpaModal) {
                closeGPAModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>