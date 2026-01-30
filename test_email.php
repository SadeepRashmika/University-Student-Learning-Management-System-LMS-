<?php
/**
 * Email Configuration Test Script
 * 
 * Usage:
 * Command line: php test_email.php your-email@example.com
 * Browser: http://localhost/lms/test_email.php?email=your-email@example.com
 */

// Get email from command line or GET parameter
$test_email = '';
if (isset($argv[1])) {
    $test_email = $argv[1];
} elseif (isset($_GET['email'])) {
    $test_email = $_GET['email'];
}

if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    die("Usage: php test_email.php your-email@example.com\nOr visit: http://localhost/lms/test_email.php?email=your-email@example.com\n");
}

$from_email = "studentlms42@gmail.com";
$from_name = "University LMS";

// Test email content
$subject = "LMS Email Configuration Test";
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
        .content {
            padding: 30px;
        }
        .success {
            background: #d1fae5;
            border: 1px solid #10b981;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            color: #065f46;
        }
        .info-box {
            background: #f0f4ff;
            border-left: 4px solid #6366f1;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            background: #f9fafb;
            padding: 20px;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🎓 Email Configuration Test</h1>
        </div>
        <div class='content'>
            <div class='success'>
                <strong>✅ Success!</strong> Your email configuration is working correctly.
            </div>
            <p>This is a test email from the University LMS system.</p>
            <div class='info-box'>
                <strong>Test Details:</strong><br>
                <strong>From:</strong> $from_email<br>
                <strong>To:</strong> $test_email<br>
                <strong>Date:</strong> " . date('l, F j, Y g:i A') . "<br>
                <strong>Server:</strong> " . gethostname() . "
            </div>
            <p>If you received this email, your email notifications are configured correctly and the timetable notification system should work properly.</p>
        </div>
        <div class='footer'>
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

echo "Sending test email to: $test_email\n";
echo "From: $from_email\n";
echo "Please wait...\n\n";

// Send email
$result = mail($test_email, $subject, $message, $headers);

if ($result) {
    echo "✅ SUCCESS: Test email sent successfully!\n";
    echo "Please check your inbox (and spam folder) at: $test_email\n";
    echo "\nIf you received the email, your configuration is working correctly.\n";
    echo "You can now set up the cron job for automated timetable notifications.\n";
} else {
    echo "❌ FAILED: Unable to send test email.\n";
    echo "\nPossible issues:\n";
    echo "1. PHP mail() function not configured on server\n";
    echo "2. Email server blocking outgoing mail\n";
    echo "3. Firewall blocking SMTP ports\n";
    echo "4. Missing mail server configuration in php.ini\n";
    echo "\nSuggested Solutions:\n";
    echo "- Check PHP mail configuration: php -i | grep mail\n";
    echo "- Use PHPMailer with SMTP (see README.md)\n";
    echo "- Contact your hosting provider for mail server details\n";
}

echo "\n" . str_repeat("-", 50) . "\n";
echo "For Gmail SMTP setup, see README.md for instructions\n";
echo str_repeat("-", 50) . "\n";
?>