<?php
include "config.php";

echo "<h2>Updating Database for Payment Management</h2>";

// Add new tables
$tables = [
    "payments" => "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_id INT,
        customer_number INT,
        amount_paid DECIMAL(10,2),
        payment_date DATE,
        payment_method VARCHAR(50),
        transaction_id VARCHAR(100),
        status VARCHAR(20) DEFAULT 'completed',
        FOREIGN KEY (bill_id) REFERENCES bill(id)
    )",
    
    "payment_history" => "CREATE TABLE IF NOT EXISTS payment_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_number INT,
        bill_id INT,
        payment_id INT,
        previous_due DECIMAL(10,2),
        paid_amount DECIMAL(10,2),
        remaining_due DECIMAL(10,2),
        payment_date DATE,
        notes TEXT
    )"
];

foreach ($tables as $name => $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "✅ Table '$name' created<br>";
    } else {
        echo "❌ Error creating table '$name': " . mysqli_error($conn) . "<br>";
    }
}

// Add columns to bill table
$columns = [
    "ADD COLUMN IF NOT EXISTS paid_amount DECIMAL(10,2) DEFAULT 0",
    "ADD COLUMN IF NOT EXISTS remaining_due DECIMAL(10,2) DEFAULT 0",
    "ADD COLUMN IF NOT EXISTS last_payment_date DATE",
    "MODIFY COLUMN status VARCHAR(20) DEFAULT 'pending'"
];

foreach ($columns as $sql) {
    $alter_sql = "ALTER TABLE bill $sql";
    if (mysqli_query($conn, $alter_sql)) {
        echo "✅ Column added/modified<br>";
    } else {
        echo "❌ Error: " . mysqli_error($conn) . "<br>";
    }
}

// Initialize existing bills
echo "<h3>Initializing existing bills...</h3>";
$init_sql = "UPDATE bill SET 
             remaining_due = total,
             status = CASE 
                WHEN status = 'paid' THEN 'paid'
                ELSE 'pending'
             END
             WHERE remaining_due IS NULL";
             
if (mysqli_query($conn, $init_sql)) {
    $affected = mysqli_affected_rows($conn);
    echo "✅ Initialized $affected existing bills<br>";
} else {
    echo "❌ Error initializing bills: " . mysqli_error($conn) . "<br>";
}

echo "<hr><h3>✅ Payment Management System Ready!</h3>";
echo "<p>You can now:</p>";
echo "<ul>";
echo "<li><a href='make_payment.php?id=1'>Test Payment System</a></li>";
echo "<li><a href='view_bills.php'>View Bills with Payment Status</a></li>";
echo "<li><a href='admin.php'>Go to Admin Dashboard</a></li>";
echo "</ul>";
?>