<?php
session_start();
include "config.php";

if ($_POST) {
    $number = $_POST['number'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $reading = $_POST['reading'];
    
    $sql = "INSERT INTO readings (number, month, year, reading, read_date) 
            VALUES ('$number', '$month', '$year', '$reading', CURDATE())";
    
    if (mysqli_query($conn, $sql)) {
        echo "Reading saved! <a href='worker.php'>Go back</a>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>