<?php
session_start();
include 'db.php'; // your database connection file

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $cp = trim($_POST['cp'] ?? '');

    if(empty($username) || empty($password) || empty($cp)){
        echo json_encode(['status'=>'error','message'=>'All fields are required']);
        exit();
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        echo json_encode(['status'=>'error','message'=>'Username already exists']);
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insert new user
    $stmt2 = $conn->prepare("INSERT INTO users (username, password, cp_number) VALUES (?, ?, ?)");
    $stmt2->bind_param("sss", $username, $hashed_password, $cp);

    if($stmt2->execute()){
        echo json_encode(['status'=>'success','message'=>'Registration successful!']);
    } else {
        echo json_encode(['status'=>'error','message'=>'Database error: '.$conn->error]);
    }

    $stmt2->close();
    $conn->close();
}
?>
