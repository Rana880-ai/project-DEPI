<?php
// بدء الجلسة إذا لم تكن بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إعدادات قاعدة البيانات
$host = 'localhost';
$dbname = 'user_management';
$username = 'root';
$password = '2005r2005';

// مفاتيح الـ APIs
define('GOOGLE_MAPS_API_KEY', 'AIzaSyDummyKeyReplaceWithYourOwn');
define('MAPBOX_ACCESS_TOKEN', 'pk.your_mapbox_access_token_here');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// دالة للتحقق من تسجيل الدخول
function checkUserAuth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'user') {
        header("Location: login.php");
        exit();
    }
}

// دالة للتحقق من صلاحية المشرف
function checkAdminAuth() {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
        header("Location: login.php");
        exit();
    }
}

?>