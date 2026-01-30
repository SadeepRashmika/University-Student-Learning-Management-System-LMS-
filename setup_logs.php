<?php
/**
 * Setup Logs Folder Script
 * This creates the logs folder and sets proper permissions
 * 
 * Usage: Visit http://localhost/lms/setup_logs.php in your browser
 */

echo "<h2>Setting up logs folder...</h2>";

$logs_dir = __DIR__ . '/logs';

// Check if logs directory exists
if (file_exists($logs_dir)) {
    echo "<p style='color: orange;'>⚠️ Logs folder already exists at: " . htmlspecialchars($logs_dir) . "</p>";
} else {
    // Create logs directory
    if (mkdir($logs_dir, 0777, true)) {
        echo "<p style='color: green;'>✅ Successfully created logs folder at: " . htmlspecialchars($logs_dir) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create logs folder. Please create it manually.</p>";
        exit;
    }
}

// Set permissions (for Linux/Mac)
if (chmod($logs_dir, 0777)) {
    echo "<p style='color: green;'>✅ Permissions set successfully (0777)</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Could not set permissions (this is normal on Windows)</p>";
}

// Check if writable
if (is_writable($logs_dir)) {
    echo "<p style='color: green;'>✅ Logs folder is writable</p>";
    
    // Create a test log file
    $test_file = $logs_dir . '/test_' . date('Y-m-d_H-i-s') . '.log';
    $test_content = "Test log entry created at " . date('Y-m-d H:i:s');
    
    if (file_put_contents($test_file, $test_content)) {
        echo "<p style='color: green;'>✅ Test log file created successfully: " . basename($test_file) . "</p>";
        echo "<p style='color: green;'>✅ All checks passed! Your logs folder is ready.</p>";
    } else {
        echo "<p style='color: red;'>❌ Could not write test file</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Logs folder is not writable. Please check permissions.</p>";
}

echo "<hr>";
echo "<h3>Folder Information:</h3>";
echo "<ul>";
echo "<li><strong>Path:</strong> " . htmlspecialchars($logs_dir) . "</li>";
echo "<li><strong>Exists:</strong> " . (file_exists($logs_dir) ? 'Yes' : 'No') . "</li>";
echo "<li><strong>Writable:</strong> " . (is_writable($logs_dir) ? 'Yes' : 'No') . "</li>";
echo "<li><strong>Permissions:</strong> " . substr(sprintf('%o', fileperms($logs_dir)), -4) . "</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>Test the email system: <a href='test_email.php?email=sadeeprashmika252@gmail.com'>test_email.php</a></li>";
echo "<li>Visit your timetable: <a href='Timetable.php'>Timetable.php</a></li>";
echo "<li>Test notifications manually: Run send_timetable_notifications.php</li>";
echo "</ol>";

echo "<p style='color: #666; font-size: 12px; margin-top: 30px;'>You can delete this file (setup_logs.php) after setup is complete.</p>";
?>