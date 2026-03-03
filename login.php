<?php
session_start();
include 'db.php'; // your database connection file

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if(empty($username) || empty($password)){
        echo json_encode(['status'=>'error','message'=>'All fields are required']);
        exit();
    }

    // Fetch user
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])){
            // Login successful, store session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo json_encode(['status'=>'success','message'=>'Login successful']);
        } else {
            echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
        }
    } else {
        echo json_encode(['status'=>'error','message'=>'Invalid username or password']);
    }

    $stmt->close();
    $conn->close();
}
?>
