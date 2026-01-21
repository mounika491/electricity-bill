<?php
session_start();
include "config.php";

if ($_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

if ($_POST) {
    $number = $_POST['number'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $category = $_POST['category'];
    $username = $_POST['username'];
    $password = $_POST['password']; // In real app, hash this
    
    // Save customer
    $sql1 = "INSERT INTO customer (number, name, phone, address, category, reg_date) 
             VALUES ('$number', '$name', '$phone', '$address', '$category', CURDATE())";
    
    // Save user
    $sql2 = "INSERT INTO users (username, password, role, number) 
             VALUES ('$username', '$password', 'customer', '$number')";
    
    if (mysqli_query($conn, $sql1) && mysqli_query($conn, $sql2)) {
        echo "Customer added successfully! <a href='admin.php'>Go back</a>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>