<?php
/**
 * Timetable Email Notification Cron Job
 * 
 * This script should be run daily at 7 PM
 * It sends email notifications to students about their next day's classes
 * 
 * Setup Instructions:
 * 1. Add this to your crontab: 0 19 * * * /usr/bin/php /path/to/send_timetable_notifications.php
 * 2. Or use Windows Task Scheduler to run at 7 PM daily
 */

require_once 'config.php';

// Email configuration
$from_email = "studentlms42@gmail.com";
$from_name = "University LMS";

// Get database connection
$conn = getDBConnection();

// Get tomorrow's day name
$tomorrow = date('l', strtotime('+1 day'));
$tomorrow_date = date('l, F j, Y', strtotime('+1 day'));

// Log file
$log_file = 'logs/email_notifications_' . date('Y-m-d') . '.log';
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Starting timetable notification process for $tomorrow");

// Get all students who have classes tomorrow
$query = "
    SELECT DISTINCT 
        u.user_id,
        u.email,
        u.full_name,
        u.student_id
    FROM users u
    INNER JOIN timetable t ON u.user_id = t.student_id
    WHERE t.day_of_week = ? 
    AND u.user_type = 'student'
    AND u.status = 'active'
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $tomorrow);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}
$stmt->close();

writeLog("Found " . count($students) . " students with classes on $tomorrow");

// Process each student
foreach ($students as $student) {
    // Get student's classes for tomorrow
    $stmt = $conn->prepare("
        SELECT * FROM timetable 
        WHERE student_id = ? AND day_of_week = ?
        ORDER BY start_time
    ");
    $stmt->bind_param("is", $student['user_id'], $tomorrow);
    $stmt->execute();
    $classes_result = $stmt->get_result();
    
    $classes = [];
    while ($class = $classes_result->fetch_assoc()) {
        $classes[] = $class;
    }
    $stmt->close();
    
    if (empty($classes)) {
        continue;
    }
    
    // Build email content
    $subject = "Your Classes for Tomorrow - $tomorrow";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: 'Arial', sans-serif;
                background-color: #f4f4f4;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .header {
                background: linear-gradient(135deg, #6366f1, #8b5cf6);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
            }
            .content {
                padding: 30px;
            }
            .greeting {
                font-size: 16px;
                color: #333;
                margin-bottom: 20px;
            }
            .date-header {
                background: #f0f4ff;
                padding: 15px;
                border-left: 4px solid #6366f1;
                margin-bottom: 20px;
                font-weight: bold;
                color: #4f46e5;
            }
            .class-item {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
            }
            .class-title {
                font-size: 18px;
                font-weight: bold;
                color: #1f2937;
                margin-bottom: 10px;
            }
            .class-info {
                color: #6b7280;
                font-size: 14px;
                line-height: 1.6;
            }
            .class-info span {
                display: inline-block;
                margin-right: 15px;
            }
            .icon {
                margin-right: 5px;
            }
            .footer {
                background: #f9fafb;
                padding: 20px;
                text-align: center;
                color: #6b7280;
                font-size: 12px;
            }
            .reminder {
                background: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 8px;
                padding: 15px;
                margin-top: 20px;
                color: #856404;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎓 Tomorrow's Class Schedule</h1>
            </div>
            <div class='content'>
                <div class='greeting'>
                    Hello " . htmlspecialchars($student['full_name']) . ",
                </div>
                <div class='date-header'>
                    📅 " . htmlspecialchars($tomorrow_date) . "
                </div>
                <p>Here are your scheduled classes for tomorrow:</p>
    ";
    
    foreach ($classes as $class) {
        $start_time = date('g:i A', strtotime($class['start_time']));
        $end_time = date('g:i A', strtotime($class['end_time']));
        
        $message .= "
                <div class='class-item'>
                    <div class='class-title'>" . htmlspecialchars($class['subject_name']) . "</div>
                    <div class='class-info'>
                        <span><span class='icon'>🕐</span>" . $start_time . " - " . $end_time . "</span>";
        
        if (!empty($class['location'])) {
            $message .= "<span><span class='icon'>📍</span>" . htmlspecialchars($class['location']) . "</span>";
        }
        
        if (!empty($class['instructor'])) {
            $message .= "<span><span class='icon'>👨‍🏫</span>" . htmlspecialchars($class['instructor']) . "</span>";
        }
        
        $message .= "</div>";
        
        if (!empty($class['notes'])) {
            $message .= "<div class='class-info' style='margin-top: 10px;'>
                            <span class='icon'>📝</span>" . nl2br(htmlspecialchars($class['notes'])) . "
                        </div>";
        }
        
        $message .= "</div>";
    }
    
    $message .= "
                <div class='reminder'>
                    <strong>💡 Reminder:</strong> Please ensure you have all necessary materials and arrive on time!
                </div>
            </div>
            <div class='footer'>
                <p>This is an automated notification from University LMS</p>
                <p>Student ID: " . htmlspecialchars($student['student_id']) . "</p>
                <p>&copy; " . date('Y') . " University LMS. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from_name <$from_email>" . "\r\n";
    $headers .= "Reply-To: $from_email" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email
    $email_sent = mail($student['email'], $subject, $message, $headers);
    
    if ($email_sent) {
        writeLog("Email sent successfully to " . $student['email'] . " (" . $student['full_name'] . ")");
        
        // Record notification in database
        $notification_date = date('Y-m-d', strtotime('+1 day'));
        foreach ($classes as $class) {
            $stmt = $conn->prepare("
                INSERT INTO timetable_notifications 
                (student_id, timetable_id, notification_date, sent_at, status) 
                VALUES (?, ?, ?, NOW(), 'sent')
            ");
            $stmt->bind_param("iis", $student['user_id'], $class['timetable_id'], $notification_date);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        writeLog("Failed to send email to " . $student['email'] . " (" . $student['full_name'] . ")");
        
        // Record failed notification
        $notification_date = date('Y-m-d', strtotime('+1 day'));
        foreach ($classes as $class) {
            $stmt = $conn->prepare("
                INSERT INTO timetable_notifications 
                (student_id, timetable_id, notification_date, status) 
                VALUES (?, ?, ?, 'failed')
            ");
            $stmt->bind_param("iis", $student['user_id'], $class['timetable_id'], $notification_date);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Small delay to avoid overwhelming mail server
    usleep(500000); // 0.5 second delay
}

$conn->close();

writeLog("Notification process completed. Total emails sent to " . count($students) . " students.");
echo "Process completed. Check log file: $log_file\n";
?>