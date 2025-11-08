<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'manager') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = $error_message = '';

// معالجة إنشاء التقرير
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_report'])) {
    $report_title = trim($_POST['report_title']);
    $report_content = trim($_POST['report_content']);
    $report_type = $_POST['report_type'];
    $send_notification = isset($_POST['send_notification']);
    $notification_message = trim($_POST['notification_message']);
    
    try {
        $pdo->beginTransaction();
        
        // حفظ التقرير في قاعدة البيانات
        $stmt = $pdo->prepare("
            INSERT INTO reports (manager_id, title, content, report_type, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $report_title, $report_content, $report_type]);
        $report_id = $pdo->lastInsertId();
        
        // إرسال الإشعارات إذا طلب المدير
        if ($send_notification && !empty($notification_message)) {
            // الحصول على جميع المستخدمين
            $users_stmt = $pdo->query("SELECT id FROM users WHERE user_type = 'user'");
            $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $notification_title = "New Report: " . $report_title;
            
            // إدخال إشعار لكل مستخدم
            $notification_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, ?, ?, 'info', NOW())
            ");
            
            foreach ($users as $user) {
                $notification_stmt->execute([$user['id'], $notification_title, $notification_message]);
            }
            
            $success_message = "Report created successfully and notifications sent to all users!";
        } else {
            $success_message = "Report created successfully!";
        }
        
        $pdo->commit();
        
        // تصدير التقرير لملف Excel إذا طلب المدير
        if (isset($_POST['export_excel'])) {
            exportReportToExcel($report_id, $report_title, $report_content, $report_type);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error creating report: " . $e->getMessage();
    }
}

// جلب التقارير السابقة
try {
    $reports_stmt = $pdo->prepare("
        SELECT r.*, u.username as manager_name 
        FROM reports r 
        JOIN users u ON r.manager_id = u.id 
        ORDER BY r.created_at DESC
    ");
    $reports_stmt->execute();
    $reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// دالة تصدير Excel
function exportReportToExcel($report_id, $title, $content, $type) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="report_' . $report_id . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    $html = "
        <html>
        <head>
            <style>
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid black; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .title { font-size: 18px; font-weight: bold; }
                .header { background: #2c3e50; color: white; }
            </style>
        </head>
        <body>
            <table>
                <tr class='header'>
                    <th colspan='2'>SmartPark - Management Report</th>
                </tr>
                <tr>
                    <td class='title'>Report Title:</td>
                    <td>{$title}</td>
                </tr>
                <tr>
                    <td>Report ID:</td>
                    <td>#{$report_id}</td>
                </tr>
                <tr>
                    <td>Report Type:</td>
                    <td>{$type}</td>
                </tr>
                <tr>
                    <td>Generated Date:</td>
                    <td>" . date('Y-m-d H:i:s') . "</td>
                </tr>
                <tr>
                    <td colspan='2' class='title'>Report Content:</td>
                </tr>
                <tr>
                    <td colspan='2' style='height: 200px; vertical-align: top;'>{$content}</td>
                </tr>
            </table>
        </body>
        </html>
    ";
    
    echo $html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management - SmartPark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #17a2b8;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --sidebar-width: 250px;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
        }

        .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }

        .logo h1 {
            color: var(--primary);
            font-size: 24px;
            font-weight: 700;
        }

        .nav-links {
            list-style: none;
            padding: 0 20px;
        }

        .nav-links li {
            margin-bottom: 10px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            text-decoration: none;
            color: var(--dark);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover, .nav-links a.active {
            background: var(--secondary);
            color: white;
        }

        .nav-links i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--warning);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }

        .manager-badge {
            background: var(--warning);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .card h2 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-weight: normal;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--secondary);
            color: var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: white;
        }

        .reports-grid {
            display: grid;
            gap: 20px;
        }

        .report-item {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--secondary);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .report-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .report-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 10px;
        }

        .report-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .report-meta {
            display: flex;
            gap: 15px;
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .report-type {
            background: var(--info);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .report-content {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .report-actions {
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .logo h1, .sidebar .nav-text {
                display: none;
            }
            .main-content {
                margin-left: 70px;
            }
            .nav-links a {
                justify-content: center;
            }
            .nav-links i {
                margin-right: 0;
                font-size: 20px;
            }
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h1>SmartPark</h1>
        </div>
        <ul class="nav-links">
            <li><a href="dashboard-manager.php"><i class="fas fa-home"></i> <span class="nav-text">Dashboard</span></a></li>
            <li><a href="parking_maps_manager.php"><i class="fas fa-map-marked-alt"></i> <span class="nav-text">Parking Maps</span></a></li>
            <li><a href="booking_manager.php"><i class="fas fa-calendar-check"></i> <span class="nav-text">Parking Management</span></a></li>
            <li><a href="users_manager.php"><i class="fas fa-users"></i> <span class="nav-text">Users</span></a></li>
            <li><a href="payments_manager.php"><i class="fas fa-credit-card"></i> <span class="nav-text">Payments</span></a></li>
            <li><a href="reports_manager.php" class="active"><i class="fas fa-chart-bar"></i> <span class="nav-text">Reports</span></a></li>
            <li><a href="notifications_manager.php"><i class="fas fa-bell"></i> <span class="nav-text">Notifications</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Reports Management</h1>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="manager-badge">Manager</div>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Create Report Form -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Create New Report</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="report_title">Report Title *</label>
                    <input type="text" id="report_title" name="report_title" class="form-control" 
                           placeholder="Enter report title..." required>
                </div>

                <div class="form-group">
                    <label for="report_type">Report Type *</label>
                    <select id="report_type" name="report_type" class="form-control" required>
                        <option value="">Select Report Type</option>
                        <option value="financial">Financial Report</option>
                        <option value="usage">Usage Statistics</option>
                        <option value="maintenance">Maintenance Report</option>
                        <option value="security">Security Report</option>
                        <option value="monthly">Monthly Summary</option>
                        <option value="custom">Custom Report</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="report_content">Report Content *</label>
                    <textarea id="report_content" name="report_content" class="form-control" 
                              placeholder="Write your report content here..." required></textarea>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="send_notification" name="send_notification" value="1">
                    <label for="send_notification">Send notification to all users</label>
                </div>

                <div class="form-group" id="notification_message_group">
                    <label for="notification_message">Notification Message</label>
                    <textarea id="notification_message" name="notification_message" class="form-control" 
                              placeholder="Enter notification message for users..."></textarea>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="export_excel" name="export_excel" value="1" checked>
                    <label for="export_excel">Export to Excel file</label>
                </div>

                <div class="form-group">
                    <button type="submit" name="create_report" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </button>
                    <button type="reset" class="btn btn-outline">
                        <i class="fas fa-redo"></i> Reset Form
                    </button>
                </div>
            </form>
        </div>

        <!-- Previous Reports -->
        <div class="card">
            <h2><i class="fas fa-history"></i> Previous Reports</h2>
            
            <?php if (empty($reports)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>No reports generated yet</p>
                </div>
            <?php else: ?>
                <div class="reports-grid">
                    <?php foreach ($reports as $report): ?>
                    <div class="report-item">
                        <div class="report-header">
                            <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                            <span class="report-type"><?php echo ucfirst($report['report_type']); ?></span>
                        </div>
                        <div class="report-meta">
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($report['manager_name']); ?></span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y g:i A', strtotime($report['created_at'])); ?></span>
                            <span><i class="fas fa-hashtag"></i> #<?php echo $report['id']; ?></span>
                        </div>
                        <div class="report-content">
                            <?php echo nl2br(htmlspecialchars(substr($report['content'], 0, 200))); ?>
                            <?php if (strlen($report['content']) > 200): ?>...<?php endif; ?>
                        </div>
                        <div class="report-actions">
                            <button class="btn btn-outline btn-sm" onclick="viewFullReport(<?php echo $report['id']; ?>)">
                                <i class="fas fa-eye"></i> View Full
                            </button>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <input type="hidden" name="report_title" value="<?php echo htmlspecialchars($report['title']); ?>">
                                <input type="hidden" name="report_content" value="<?php echo htmlspecialchars($report['content']); ?>">
                                <input type="hidden" name="report_type" value="<?php echo $report['report_type']; ?>">
                                <button type="submit" name="export_single" class="btn btn-success btn-sm">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // إظهار/إخفاء حقل رسالة الإشعار
        document.getElementById('send_notification').addEventListener('change', function() {
            const notificationGroup = document.getElementById('notification_message_group');
            if (this.checked) {
                notificationGroup.style.display = 'block';
            } else {
                notificationGroup.style.display = 'none';
            }
        });

        // إخفاء حقل الإشعار افتراضيًا
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('notification_message_group').style.display = 'none';
        });

        // عرض التقرير الكامل (يمكن تطويره لعرض modal)
        function viewFullReport(reportId) {
            alert('Viewing full report #' + reportId + '\n\nIn a real implementation, this would open a modal or new page with the full report content.');
            // يمكن استبدال هذا ب modal يعرض المحتوى الكامل للتقرير
        }

        // عدّاد الأحرف للمحتوى
        document.getElementById('report_content').addEventListener('input', function() {
            const charCount = this.value.length;
            let counter = this.parentElement.querySelector('.char-counter');
            
            if (!counter) {
                counter = document.createElement('div');
                counter.className = 'char-counter';
                counter.style.fontSize = '12px';
                counter.style.color = '#666';
                counter.style.marginTop = '5px';
                this.parentElement.appendChild(counter);
            }
            
            counter.textContent = charCount + ' characters';
            
            if (charCount < 50) {
                counter.style.color = '#e74c3c';
            } else if (charCount < 200) {
                counter.style.color = '#f39c12';
            } else {
                counter.style.color = '#27ae60';
            }
        });

        // توليد عنوان تلقائي بناءً على النوع
        document.getElementById('report_type').addEventListener('change', function() {
            const titleField = document.getElementById('report_title');
            const type = this.value;
            
            if (type && !titleField.value) {
                const titles = {
                    'financial': 'Financial Report - ' + new Date().toLocaleDateString(),
                    'usage': 'Usage Statistics Report - ' + new Date().toLocaleDateString(),
                    'maintenance': 'Maintenance Report - ' + new Date().toLocaleDateString(),
                    'security': 'Security Report - ' + new Date().toLocaleDateString(),
                    'monthly': 'Monthly Summary - ' + new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
                    'custom': 'Custom Report - ' + new Date().toLocaleDateString()
                };
                
                if (titles[type]) {
                    titleField.value = titles[type];
                }
            }
        });
    </script>
</body>
</html>