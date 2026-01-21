<?php
include "config.php";

echo "<h2>Setting Up Customer Accounts</h2>";

// Check if customer table exists
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'customer'");
if (mysqli_num_rows($check_table) == 0) {
    die("❌ Customer table doesn't exist! Run setup_database.php first.");
}

// Get all customers without user accounts
$sql = "SELECT c.* FROM customer c 
        LEFT JOIN users u ON c.number = u.number AND u.role = 'customer'
        WHERE u.id IS NULL";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    echo "<h3>Creating User Accounts for Customers:</h3>";
    
    while($customer = mysqli_fetch_assoc($result)) {
        $number = $customer['number'];
        $name = $customer['name'];
        
        // Create username (first name + last 4 digits of meter number)
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . substr($number, -4);
        $password = 'customer123'; // Default password
        
        // Check if username already exists
        $check_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        
        if (mysqli_num_rows($check_user) == 0) {
            // Create user account
            $insert_sql = "INSERT INTO users (username, password, role, number) 
                          VALUES ('$username', '$password', 'customer', '$number')";
            
            if (mysqli_query($conn, $insert_sql)) {
                echo "✅ Created account for $name (Meter: $number)<br>";
                echo "&nbsp;&nbsp;Username: <strong>$username</strong><br>";
                echo "&nbsp;&nbsp;Password: <strong>$password</strong><br><br>";
            } else {
                echo "❌ Error creating account for $name: " . mysqli_error($conn) . "<br>";
            }
        } else {
            echo "⚠️ Account already exists for $name<br>";
        }
    }
} else {
    echo "✅ All customers already have user accounts.<br>";
}

// Create some test customers if none exist
$check_customers = mysqli_query($conn, "SELECT COUNT(*) as count FROM customer");
$count = mysqli_fetch_assoc($check_customers)['count'];

if ($count == 0) {
    echo "<h3>Creating Test Customers:</h3>";
    
    $test_customers = [
        [1001, 'John Doe', '9876543210', '123 Main Street', 'household'],
        [1002, 'Jane Smith', '9876543211', '456 Oak Avenue', 'commercial'],
        [1003, 'Robert Johnson', '9876543212', '789 Pine Road', 'industrial'],
        [1004, 'Sarah Williams', '9876543213', '321 Maple Lane', 'household'],
        [1005, 'Mike Brown', '9876543214', '654 Cedar Street', 'commercial']
    ];
    
    foreach ($test_customers as $customer) {
        list($number, $name, $phone, $address, $category) = $customer;
        
        $sql = "INSERT INTO customer (number, name, phone, address, category, reg_date) 
                VALUES ('$number', '$name', '$phone', '$address', '$category', CURDATE())";
        
        if (mysqli_query($conn, $sql)) {
            // Create user account
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name)) . substr($number, -4);
            $password = 'customer123';
            
            $user_sql = "INSERT INTO users (username, password, role, number) 
                        VALUES ('$username', '$password', 'customer', '$number')";
            
            mysqli_query($conn, $user_sql);
            
            echo "✅ Created customer: $name (Meter: $number)<br>";
            echo "&nbsp;&nbsp;Username: <strong>$username</strong><br>";
            echo "&nbsp;&nbsp;Password: <strong>$password</strong><br><br>";
        }
    }
}

echo "<hr>";
echo "<h3>✅ Customer Setup Complete!</h3>";
echo "<p><a href='login.php' style='display:inline-block; padding:10px 20px; background:#3498db; color:white; text-decoration:none; border-radius:5px;'>Go to Login</a></p>";

// Show all customer accounts
echo "<h3>All Customer Accounts:</h3>";
$accounts_sql = "SELECT u.username, u.password, c.name, c.number, c.category 
                 FROM users u 
                 JOIN customer c ON u.number = c.number 
                 WHERE u.role = 'customer' 
                 ORDER BY c.number";
$accounts_result = mysqli_query($conn, $accounts_sql);

if (mysqli_num_rows($accounts_result) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Username</th><th>Password</th><th>Name</th><th>Meter No.</th><th>Category</th></tr>";
    
    while($account = mysqli_fetch_assoc($accounts_result)) {
        echo "<tr>";
        echo "<td>{$account['username']}</td>";
        echo "<td>{$account['password']}</td>";
        echo "<td>{$account['name']}</td>";
        echo "<td>{$account['number']}</td>";
        echo "<td>{$account['category']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No customer accounts found.</p>";
}
?>