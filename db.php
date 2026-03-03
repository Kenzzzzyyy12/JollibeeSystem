<?php
$servername = "localhost";
$dbusername = "root";  // your MySQL username
$dbpassword = "";      // your MySQL password
$dbname = "jollibee"; // your database name

$conn = new mysqli($servername, $dbusername, $dbpassword, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
