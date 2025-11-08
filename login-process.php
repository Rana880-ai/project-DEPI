<?php
session_start();
require_once 'config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        header("Location: login.php?error=Please fill in all fields");
        exit();
    }
    
    try {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['email'] = $user['email'];
            
            if($user['user_type'] == 'user') {
                header("Location: dashboard-user.php");
            } else {
                header("Location: dashboard-manager.php");
            }
            exit();
        } else {
            header("Location: login.php?error=Invalid username or password");
            exit();
        }
    } catch(PDOException $e) {
        header("Location: login.php?error=Database error: " . $e->getMessage());
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>