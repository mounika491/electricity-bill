<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "electricity";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>