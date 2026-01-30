<?php
/**
 * Image Upload Diagnostic Tool
 * Upload this file to your server and access it to diagnose upload issues
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        h2 {
            color: #666;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .success {
            color: #4CAF50;
            font-weight: bold;
        }
        .error {
            color: #f44336;
            font-weight: bold;
        }
        .warning {
            color: #ff9800;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        td:first-child {
            font-weight: bold;
            width: 250px;
        }
        .test-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            border: 2px dashed #ccc;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #45a049;
        }
        input[type="file"] {
            padding: 10px;
            margin: 10px 0;
        }
        .result-box {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 10px 0;
        }
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 10px 0;
        }
        pre {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🔍 Image Upload Diagnostic Tool</h1>
    
    <div class="section">
        <h2>1. PHP Configuration</h2>
        <table>
            <tr>
                <td>PHP Version</td>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td>file_uploads</td>
                <td class="<?php echo ini_get('file_uploads') ? 'success' : 'error'; ?>">
                    <?php echo ini_get('file_uploads') ? '✅ Enabled' : '❌ Disabled'; ?>
                </td>
            </tr>
            <tr>
                <td>upload_max_filesize</td>
                <td class="<?php echo (int)ini_get('upload_max_filesize') >= 10 ? 'success' : 'warning'; ?>">
                    <?php echo ini_get('upload_max_filesize'); ?>
                    <?php if ((int)ini_get('upload_max_filesize') < 10) echo ' (⚠️ Less than 10M)'; ?>
                </td>
            </tr>
            <tr>
                <td>post_max_size</td>
                <td class="<?php echo (int)ini_get('post_max_size') >= 10 ? 'success' : 'warning'; ?>">
                    <?php echo ini_get('post_max_size'); ?>
                    <?php if ((int)ini_get('post_max_size') < 10) echo ' (⚠️ Less than 10M)'; ?>
                </td>
            </tr>
            <tr>
                <td>max_file_uploads</td>
                <td><?php echo ini_get('max_file_uploads'); ?></td>
            </tr>
            <tr>
                <td>max_execution_time</td>
                <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
            </tr>
            <tr>
                <td>memory_limit</td>
                <td><?php echo ini_get('memory_limit'); ?></td>
            </tr>
            <tr>
                <td>upload_tmp_dir</td>
                <td>
                    <?php 
                    $tmp_dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
                    echo $tmp_dir;
                    echo is_writable($tmp_dir) ? ' <span class="success">✅ Writable</span>' : ' <span class="error">❌ Not writable</span>';
                    ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>2. Image Processing Support</h2>
        <table>
            <?php if (extension_loaded('gd')): ?>
                <tr>
                    <td>GD Library</td>
                    <td class="success">✅ Installed</td>
                </tr>
                <?php 
                $gd_info = gd_info();
                ?>
                <tr>
                    <td>GIF Support</td>
                    <td class="<?php echo $gd_info['GIF Read Support'] ? 'success' : 'error'; ?>">
                        <?php echo $gd_info['GIF Read Support'] ? '✅ Yes' : '❌ No'; ?>
                    </td>
                </tr>
                <tr>
                    <td>JPEG Support</td>
                    <td class="<?php echo $gd_info['JPEG Support'] ? 'success' : 'error'; ?>">
                        <?php echo $gd_info['JPEG Support'] ? '✅ Yes' : '❌ No'; ?>
                    </td>
                </tr>
                <tr>
                    <td>PNG Support</td>
                    <td class="<?php echo $gd_info['PNG Support'] ? 'success' : 'error'; ?>">
                        <?php echo $gd_info['PNG Support'] ? '✅ Yes' : '❌ No'; ?>
                    </td>
                </tr>
            <?php else: ?>
                <tr>
                    <td>GD Library</td>
                    <td class="error">❌ Not Installed (Images may not work properly)</td>
                </tr>
            <?php endif; ?>
            
            <tr>
                <td>Fileinfo Extension</td>
                <td class="<?php echo extension_loaded('fileinfo') ? 'success' : 'warning'; ?>">
                    <?php echo extension_loaded('fileinfo') ? '✅ Installed' : '⚠️ Not installed'; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>3. Directory Permissions</h2>
        <table>
            <?php
            $upload_dir = 'uploads/';
            $courses_dir = 'uploads/courses/';
            ?>
            <tr>
                <td>uploads/ directory</td>
                <td>
                    <?php if (is_dir($upload_dir)): ?>
                        <span class="success">✅ Exists</span>
                        <?php if (is_writable($upload_dir)): ?>
                            <span class="success">✅ Writable</span>
                        <?php else: ?>
                            <span class="error">❌ Not writable (chmod 755 or 777 needed)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="error">❌ Does not exist</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td>uploads/courses/ directory</td>
                <td>
                    <?php if (is_dir($courses_dir)): ?>
                        <span class="success">✅ Exists</span>
                        <?php if (is_writable($courses_dir)): ?>
                            <span class="success">✅ Writable</span>
                        <?php else: ?>
                            <span class="error">❌ Not writable (chmod 755 or 777 needed)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="error">❌ Does not exist (will be created on first upload)</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="section">
        <h2>4. Config.php Check</h2>
        <?php if (file_exists('config.php')): ?>
            <div class="result-box">
                <strong>✅ config.php found</strong>
                <?php
                require_once 'config.php';
                ?>
                <table style="margin-top: 10px;">
                    <tr>
                        <td>ALLOWED_FILE_TYPES</td>
                        <td>
                            <?php 
                            if (defined('ALLOWED_FILE_TYPES')) {
                                $types = ALLOWED_FILE_TYPES;
                                echo implode(', ', $types);
                                
                                $image_types = ['jpg', 'jpeg', 'png', 'gif'];
                                $has_images = count(array_intersect($image_types, $types)) === 4;
                                
                                if ($has_images) {
                                    echo '<br><span class="success">✅ All image types included</span>';
                                } else {
                                    echo '<br><span class="error">❌ Missing image types</span>';
                                }
                            } else {
                                echo '<span class="error">❌ Not defined</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>MAX_FILE_SIZE</td>
                        <td>
                            <?php 
                            if (defined('MAX_FILE_SIZE')) {
                                $size_mb = MAX_FILE_SIZE / 1024 / 1024;
                                echo round($size_mb, 2) . ' MB';
                                if ($size_mb < 10) {
                                    echo ' <span class="warning">⚠️ Less than 10MB</span>';
                                } else {
                                    echo ' <span class="success">✅</span>';
                                }
                            } else {
                                echo '<span class="error">❌ Not defined</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>UPLOAD_DIR</td>
                        <td>
                            <?php 
                            if (defined('UPLOAD_DIR')) {
                                echo UPLOAD_DIR;
                            } else {
                                echo '<span class="error">❌ Not defined</span>';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        <?php else: ?>
            <div class="error-box">
                <strong>❌ config.php not found</strong><br>
                Make sure config.php is in the same directory as this test file.
            </div>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>5. Test Image Upload</h2>
        <div class="test-form">
            <form method="POST" enctype="multipart/form-data">
                <p><strong>Select an image file to test upload:</strong></p>
                <input type="file" name="test_image" accept=".jpg,.jpeg,.png,.gif" required>
                <br>
                <button type="submit" name="do_test">Test Upload</button>
            </form>
        </div>
        
        <?php
        if (isset($_POST['do_test']) && isset($_FILES['test_image'])) {
            echo '<h3>Upload Test Results:</h3>';
            
            $file = $_FILES['test_image'];
            
            echo '<table>';
            echo '<tr><td>File Name</td><td>' . htmlspecialchars($file['name']) . '</td></tr>';
            echo '<tr><td>File Size</td><td>' . round($file['size'] / 1024, 2) . ' KB</td></tr>';
            echo '<tr><td>File Type (Browser)</td><td>' . htmlspecialchars($file['type']) . '</td></tr>';
            echo '<tr><td>Temp Location</td><td>' . htmlspecialchars($file['tmp_name']) . '</td></tr>';
            echo '<tr><td>Error Code</td><td>' . $file['error'] . '</td></tr>';
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                echo '<tr><td>File Extension</td><td>' . $file_ext . '</td></tr>';
                
                // Check actual MIME type
                if (function_exists('finfo_open')) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    echo '<tr><td>Actual MIME Type</td><td>' . $mime . '</td></tr>';
                }
                
                // Verify it's an image
                $image_info = @getimagesize($file['tmp_name']);
                if ($image_info !== false) {
                    echo '<tr><td>Image Dimensions</td><td>' . $image_info[0] . ' x ' . $image_info[1] . ' pixels</td></tr>';
                    echo '<tr><td>Image Type</td><td>' . $image_info['mime'] . '</td></tr>';
                }
                
                echo '</table>';
                
                // Try validation
                if (function_exists('validateUploadedFile')) {
                    $validation = validateUploadedFile($file);
                    if ($validation['valid']) {
                        echo '<div class="result-box">';
                        echo '<strong>✅ SUCCESS!</strong><br>';
                        echo 'File passed all validation checks.<br>';
                        echo 'Extension: ' . $validation['extension'] . '<br>';
                        echo '<br><strong>This file CAN be uploaded in your application.</strong>';
                        echo '</div>';
                        
                        // Try actual upload
                        $test_dir = 'uploads/test/';
                        if (!is_dir($test_dir)) {
                            mkdir($test_dir, 0755, true);
                        }
                        
                        $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
                        $destination = $test_dir . $unique_name;
                        
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            echo '<div class="result-box">';
                            echo '<strong>✅ FILE SAVED SUCCESSFULLY!</strong><br>';
                            echo 'Location: ' . $destination . '<br>';
                            echo 'Size on disk: ' . filesize($destination) . ' bytes<br>';
                            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                echo '<br><strong>Preview:</strong><br>';
                                echo '<img src="' . $destination . '" style="max-width: 100%; max-height: 400px; border: 1px solid #ddd; margin-top: 10px;">';
                            }
                            echo '</div>';
                        } else {
                            echo '<div class="error-box">';
                            echo '<strong>❌ FAILED to save file</strong><br>';
                            echo 'Could not move file to: ' . $destination . '<br>';
                            echo 'Check directory permissions.';
                            echo '</div>';
                        }
                    } else {
                        echo '<div class="error-box">';
                        echo '<strong>❌ VALIDATION FAILED</strong><br>';
                        echo 'Reason: ' . htmlspecialchars($validation['message']);
                        echo '</div>';
                    }
                } else {
                    echo '<div class="warning" style="padding: 15px; background: #fff3cd; border-left: 4px solid #ff9800;">';
                    echo '<strong>⚠️ Cannot test validation</strong><br>';
                    echo 'validateUploadedFile() function not found.<br>';
                    echo 'Make sure config.php is loaded.';
                    echo '</div>';
                }
            } else {
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];
                
                echo '</table>';
                echo '<div class="error-box">';
                echo '<strong>❌ UPLOAD FAILED</strong><br>';
                echo 'Error: ' . ($error_messages[$file['error']] ?? 'Unknown error');
                echo '</div>';
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>6. Recommendations</h2>
        <?php
        $issues = [];
        
        if (!ini_get('file_uploads')) {
            $issues[] = '❌ File uploads are disabled in PHP. Enable file_uploads in php.ini';
        }
        
        if ((int)ini_get('upload_max_filesize') < 10) {
            $issues[] = '⚠️ upload_max_filesize is less than 10M. Increase in php.ini';
        }
        
        if ((int)ini_get('post_max_size') < 10) {
            $issues[] = '⚠️ post_max_size is less than 10M. Increase in php.ini';
        }
        
        if (!extension_loaded('gd')) {
            $issues[] = '⚠️ GD library not installed. Install php-gd for image processing';
        }
        
        if (is_dir('uploads/courses/') && !is_writable('uploads/courses/')) {
            $issues[] = '❌ uploads/courses/ is not writable. Run: chmod 755 uploads/courses/';
        }
        
        if (!is_dir('uploads/courses/')) {
            $issues[] = '⚠️ uploads/courses/ does not exist. It will be created automatically.';
        }
        
        if (!file_exists('config.php')) {
            $issues[] = '❌ config.php not found. Upload config.php to this directory.';
        } else {
            if (!defined('ALLOWED_FILE_TYPES')) {
                $issues[] = '❌ ALLOWED_FILE_TYPES not defined in config.php';
            } else {
                $image_types = ['jpg', 'jpeg', 'png', 'gif'];
                $missing = array_diff($image_types, ALLOWED_FILE_TYPES);
                if (!empty($missing)) {
                    $issues[] = '⚠️ Image types missing from ALLOWED_FILE_TYPES: ' . implode(', ', $missing);
                }
            }
        }
        
        if (empty($issues)) {
            echo '<div class="result-box">';
            echo '<strong>✅ All checks passed!</strong><br>';
            echo 'Your system is properly configured for image uploads.';
            echo '</div>';
        } else {
            echo '<div class="error-box">';
            echo '<strong>Issues Found:</strong><ul>';
            foreach ($issues as $issue) {
                echo '<li>' . $issue . '</li>';
            }
            echo '</ul></div>';
            
            echo '<div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196F3;">';
            echo '<strong>Quick Fixes:</strong><br>';
            echo '1. Edit php.ini and increase upload_max_filesize and post_max_size to 20M<br>';
            echo '2. Restart Apache/Nginx after changing php.ini<br>';
            echo '3. Run: chmod 755 uploads/courses/<br>';
            echo '4. Install GD: sudo apt-get install php-gd (Ubuntu) or sudo yum install php-gd (CentOS)<br>';
            echo '5. Make sure config.php has image types in ALLOWED_FILE_TYPES array';
            echo '</div>';
        }
        ?>
    </div>
    
    <div class="section" style="background: #f0f0f0; border-left: 4px solid #666;">
        <h2>System Information</h2>
        <table>
            <tr>
                <td>Server Software</td>
                <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
            </tr>
            <tr>
                <td>PHP SAPI</td>
                <td><?php echo php_sapi_name(); ?></td>
            </tr>
            <tr>
                <td>Operating System</td>
                <td><?php echo PHP_OS; ?></td>
            </tr>
            <tr>
                <td>Document Root</td>
                <td><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></td>
            </tr>
            <tr>
                <td>Current Directory</td>
                <td><?php echo __DIR__; ?></td>
            </tr>
        </table>
    </div>
</body>
</html>