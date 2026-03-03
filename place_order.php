<?php
session_start();
include 'db.php';
if(!isset($_SESSION['user_id'])) exit();

$data = json_decode(file_get_contents("php://input"),true);
$food_id = $data['food_id'];
$quantity = $data['quantity'];

// Get food details
$stmt = $conn->prepare("SELECT name, price FROM foods WHERE id=?");
$stmt->bind_param("i",$food_id);
$stmt->execute();
$stmt->bind_result($food_name,$price);
$stmt->fetch();
$stmt->close();

// Insert into orders
$stmt2 = $conn->prepare("INSERT INTO orders (user_id, food_item, quantity, price) VALUES (?,?,?,?)");
$total = $price*$quantity;
$stmt2->bind_param("isid",$_SESSION['user_id'],$food_name,$quantity,$total);
$stmt2->execute();
$stmt2->close();
$conn->close();
?>
