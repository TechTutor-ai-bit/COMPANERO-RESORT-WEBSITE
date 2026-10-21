<?php
/**
 * Companero Resort — Admin Setup Script
 * 
 * ⚠️  ONE-TIME SETUP ONLY ⚠️
 * 
 * This script creates the database schema and initial admin account.
 * DELETE THIS FILE IMMEDIATELY AFTER FIRST USE.
 * 
 * Run this ONCE by accessing: /admin/setup.php
 * Then delete this file.
 * 
 * DO NOT commit this file to production repositories.
 */

// Prevent accidental execution
if (file_exists(__DIR__ . '/../.setup_complete')) {
    die('❌ Setup already completed. If you need to reset, delete the .setup_complete file.');
}

require_once __DIR__ . '/../includes/config.php';

// Simple password for initial setup
$initial_password = 'CompaneroResort2024!';  // CHANGE THIS AFTER FIRST LOGIN
$admin_email = 'admin@companero-resort.local';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companero Resort — Setup</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f7fb;
            color: #102a43;
            padding: 40px 20px;
            text-align: center;
        }
        .setup-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            border: 1px solid rgba(16, 42, 67, 0.14);
        }
        h1 { color: #12345a; font-size: 2em; margin-bottom: 10px; }
        .warning { 
            background: #edf2f8; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 20px 0;
            border-left: 4px solid #12345a;
        }
        .success { 
            background: #edf2f8; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 20px 0;
            color: #12345a;
        }
        .error { 
            background: #edf2f8; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 20px 0;
            color: #102a43;
        }
        code { 
            background: #edf2f8; 
            padding: 2px 6px; 
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .step { text-align: left; margin: 20px 0; }
        .step h3 { color: #12345a; }
        button { 
            background: #12345a; 
            color: #fff; 
            border: none; 
            padding: 10px 20px; 
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 20px;
        }
        button:hover { background: #1e4b78; }
        .credentials {
            background: #edf2f8;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            text-align: left;
            font-family: monospace;
            word-break: break-all;
        }
        .delete-note {
            background: #edf2f8;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            border-left: 4px solid #12345a;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>🔧 Companero Resort Setup</h1>
        
        <div class="warning">
            <strong>⚠️  WARNING:</strong><br>
            This is a ONE-TIME SETUP script. After you complete the setup below, 
            you MUST delete this file immediately.
        </div>

        <?php
        // Try to connect to MySQL and create database
        try {
            // First, connect to MySQL without specifying database
            $mysql = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT,
                DB_USER,
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Create database
            $dbName = DB_NAME;
            $mysql->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Connect to the created database
            $mysql = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASSWORD,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Read schema from SQL file
            $schemaFile = __DIR__ . '/../database/schema.sql';
            if (!file_exists($schemaFile)) {
                echo '<div class="error">❌ schema.sql not found at: ' . htmlspecialchars($schemaFile) . '</div>';
                exit;
            }

            $schema = file_get_contents($schemaFile);
            
            // Split by semicolon and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $schema)),
                fn($s) => !empty($s) && !str_starts_with(trim($s), '--')
            );

            foreach ($statements as $statement) {
                $mysql->exec($statement);
            }

            // Mark setup as complete
            touch(__DIR__ . '/../.setup_complete');

            echo '<div class="success">
                <strong>✅ Database setup completed!</strong><br>
                All tables have been created successfully.
            </div>';

            echo '<div class="step">
                <h3>📝 Initial Admin Account Created</h3>
                <p>A default admin account has been created with these credentials:</p>
                <div class="credentials">
                    <strong>Username:</strong> admin<br>
                    <strong>Email:</strong> ' . htmlspecialchars($admin_email) . '<br>
                    <strong>Password:</strong> ' . htmlspecialchars($initial_password) . '<br>
                    <strong>Role:</strong> Super Admin
                </div>
                <p><strong>Save these credentials in a secure location.</strong></p>
            </div>';

            echo '<div class="step">
                <h3>🔒 Next Steps</h3>
                <ol>
                    <li>Copy and save the credentials above somewhere secure</li>
                    <li><a href="/companero-resort-premium/admin/login.php" style="color: #12345a;">Go to Admin Login</a></li>
                    <li>Sign in with the credentials above</li>
                    <li>Change your password immediately in admin settings</li>
                    <li><strong>Delete this setup.php file</strong></li>
                </ol>
            </div>';

            echo '<div class="delete-note">
                <strong>🗑️  CRITICAL:</strong><br>
                Delete the file <code>admin/setup.php</code> immediately after setup completes.
                Anyone with access to this file can reset your admin credentials.
            </div>';

            echo '<a href="/companero-resort-premium/admin/login.php" style="color: #12345a; text-decoration: none;">
                <button>Continue to Admin Login →</button>
            </a>';

        } catch (PDOException $e) {
            echo '<div class="error">
                <strong>❌ Setup Failed</strong><br>
                ' . htmlspecialchars($e->getMessage()) . '<br><br>
                <strong>Troubleshooting:</strong>
                <ul style="text-align: left;">
                    <li>Verify MySQL is running</li>
                    <li>Check database credentials in /includes/config.php</li>
                    <li>Ensure database user has CREATE permissions</li>
                </ul>
            </div>';
        }
        ?>
    </div>
</body>
</html>
