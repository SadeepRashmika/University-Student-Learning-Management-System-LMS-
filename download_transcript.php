<?php
// download_transcript.php - Separate file for PDF download
require_once 'config.php';
requireStudent();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Grade to GPA mapping function
function getGradePoint($grade) {
    $gradePoints = [
        'A+' => 4.00, 'A' => 4.00, 'A-' => 3.70,
        'B+' => 3.30, 'B' => 3.00, 'B-' => 2.70,
        'C+' => 2.30, 'C' => 2.00, 'C-' => 1.70,
        'D+' => 1.30, 'D' => 1.00, 'F' => 0.00
    ];
    return $gradePoints[$grade] ?? 0.00;
}

// Get degree classification function
function getDegreeClass($gpa) {
    if ($gpa >= 3.70) return ['class' => 'First Class Honours', 'color' => '#10b981'];
    if ($gpa >= 3.30) return ['class' => 'Second Class Upper', 'color' => '#3b82f6'];
    if ($gpa >= 3.00) return ['class' => 'Second Class Lower', 'color' => '#8b5cf6'];
    if ($gpa >= 2.00) return ['class' => 'Third Class', 'color' => '#f59e0b'];
    if ($gpa >= 1.00) return ['class' => 'Pass', 'color' => '#64748b'];
    return ['class' => 'Fail', 'color' => '#ef4444'];
}

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get all courses
$stmt = $conn->prepare("
    SELECT 
        c.course_code, c.course_name, c.credits, 
        e.grade, e.status, c.semester, c.year
    FROM enrollments e
    INNER JOIN courses c ON e.course_id = c.course_id
    WHERE e.student_id = ?
    ORDER BY c.year DESC, c.semester DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

// Calculate GPA
$totalCredits = 0;
$totalPoints = 0;
$completedCredits = 0;

foreach ($courses as $course) {
    if ($course['status'] == 'completed' && $course['grade']) {
        $credits = $course['credits'];
        $gradePoint = getGradePoint($course['grade']);
        $totalCredits += $credits;
        $totalPoints += ($gradePoint * $credits);
        $completedCredits += $credits;
    }
}

$overallGPA = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0.00;
$degreeInfo = getDegreeClass($overallGPA);

$conn->close();

// Generate HTML for PDF
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Academic Transcript</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            margin: 0 0 5px 0;
            font-size: 22pt;
        }
        .header h2 {
            margin: 0;
            font-size: 16pt;
            font-weight: normal;
        }
        .info-section { margin: 15px 0; }
        .info-row { margin: 4px 0; }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .summary-box {
            background: #f5f5f5;
            border: 2px solid #333;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .summary-box .gpa {
            font-size: 32pt;
            font-weight: bold;
            margin: 10px 0;
        }
        .section-title {
            background: #e0e0e0;
            padding: 8px 10px;
            margin-top: 25px;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 13pt;
            border-left: 4px solid #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table th {
            background: #d0d0d0;
            padding: 8px;
            text-align: left;
            border: 1px solid #999;
            font-weight: bold;
            font-size: 10pt;
        }
        table td {
            padding: 8px;
            border: 1px solid #ccc;
            font-size: 10pt;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9pt;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>UNIVERSITY LMS</h1>
        <h2>OFFICIAL ACADEMIC TRANSCRIPT</h2>
    </div>
    
    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Student Name:</span>
            <span>' . htmlspecialchars($student['full_name']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Student ID:</span>
            <span>' . htmlspecialchars($student['student_id']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span>' . htmlspecialchars($student['email']) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Date Generated:</span>
            <span>' . date('F j, Y') . '</span>
        </div>
    </div>
    
    <div class="summary-box">
        <h3 style="margin-top: 0;">ACADEMIC SUMMARY</h3>
        <div class="gpa">' . number_format($overallGPA, 2) . '</div>
        <div style="font-size: 13pt; font-weight: bold; margin: 10px 0;">' . $degreeInfo['class'] . '</div>
        <div style="margin-top: 10px;">
            <div>Total Credits Completed: <strong>' . $completedCredits . '</strong></div>
            <div>Total Courses: <strong>' . count($courses) . '</strong></div>
        </div>
    </div>
    
    <div class="section-title">COURSE RECORDS</div>
    
    <table>
        <thead>
            <tr>
                <th width="12%">Course Code</th>
                <th width="35%">Course Name</th>
                <th width="18%">Semester</th>
                <th width="10%">Credits</th>
                <th width="10%">Grade</th>
                <th width="10%">Points</th>
            </tr>
        </thead>
        <tbody>';

foreach ($courses as $course) {
    $html .= '<tr>
                <td><strong>' . htmlspecialchars($course['course_code']) . '</strong></td>
                <td>' . htmlspecialchars($course['course_name']) . '</td>
                <td>' . htmlspecialchars($course['semester'] . ' ' . $course['year']) . '</td>
                <td style="text-align: center;">' . $course['credits'] . '</td>
                <td style="text-align: center;"><strong>' . ($course['grade'] ?: 'Pending') . '</strong></td>
                <td style="text-align: center;">' . ($course['grade'] ? number_format(getGradePoint($course['grade']), 2) : '-') . '</td>
            </tr>';
}

$html .= '</tbody>
    </table>
    
    <div class="section-title">GRADING SCALE</div>
    <table style="width: 60%;">
        <tr>
            <td><strong>A+, A:</strong> 4.00</td>
            <td><strong>A-:</strong> 3.70</td>
            <td><strong>B+:</strong> 3.30</td>
        </tr>
        <tr>
            <td><strong>B:</strong> 3.00</td>
            <td><strong>B-:</strong> 2.70</td>
            <td><strong>C+:</strong> 2.30</td>
        </tr>
        <tr>
            <td><strong>C:</strong> 2.00</td>
            <td><strong>C-:</strong> 1.70</td>
            <td><strong>D+:</strong> 1.30</td>
        </tr>
        <tr>
            <td><strong>D:</strong> 1.00</td>
            <td><strong>F:</strong> 0.00</td>
            <td></td>
        </tr>
    </table>
    
    <div class="section-title">DEGREE CLASSIFICATION</div>
    <div style="margin: 15px 0; line-height: 1.8;">
        <div>• <strong>First Class Honours:</strong> GPA ≥ 3.70</div>
        <div>• <strong>Second Class Upper:</strong> GPA 3.30 - 3.69</div>
        <div>• <strong>Second Class Lower:</strong> GPA 3.00 - 3.29</div>
        <div>• <strong>Third Class:</strong> GPA 2.00 - 2.99</div>
        <div>• <strong>Pass:</strong> GPA 1.00 - 1.99</div>
        <div>• <strong>Fail:</strong> GPA < 1.00</div>
    </div>
    
    <div class="footer">
        <p><strong>This is an official academic transcript generated from University LMS</strong></p>
        <p>Generated on ' . date('F j, Y g:i A') . '</p>
        <p style="margin-top: 10px; font-size: 8pt;">This document is computer-generated and requires no signature.</p>
    </div>
</body>
</html>';

// Output as printable HTML with auto-print
header('Content-Type: text/html; charset=UTF-8');
echo $html;
echo '
<script>
    // Auto-print dialog
    window.onload = function() {
        window.print();
    };
    
    // Close window after printing or canceling
    window.onafterprint = function() {
        window.close();
    };
</script>';
exit;
?>