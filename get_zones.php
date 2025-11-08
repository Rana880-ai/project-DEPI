<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$mall_name = $_GET['mall'] ?? '';

if ($mall_name) {
    try {
        $zones_stmt = $pdo->prepare("
            SELECT DISTINCT zone_name 
            FROM parking_zones 
            JOIN malls ON parking_zones.mall_id = malls.id 
            WHERE malls.mall_name = ?
        ");
        $zones_stmt->execute([$mall_name]);
        $zones = $zones_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        header('Content-Type: application/json');
        echo json_encode($zones);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>