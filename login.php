<?php
session_start();
include "config.php";

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin.php");
    } elseif ($_SESSION['role'] == 'worker') {
        header("Location: worker.php");
    } else {
        header("Location: customer.php");
    }
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Simple credentials check (for testing)
    if ($username == 'admin' && $password == 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';
        header("Location: admin.php");
        exit;
    }
    elseif ($username == 'worker' && $password == 'worker123') {
        $_SESSION['user_id'] = 2;
        $_SESSION['username'] = 'worker';
        $_SESSION['role'] = 'worker';
        header("Location: worker.php");
        exit;
    }
    else {
        // Check if it's a customer
        $sql = "SELECT u.*, c.name, c.number FROM users u 
                JOIN customer c ON u.number = c.number 
                WHERE u.username = '$username' AND u.password = '$password' 
                AND u.role = 'customer'";
        $result = mysqli_query($conn, $sql);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['number'] = $row['number'];
            $_SESSION['name'] = $row['name'];
            header("Location: customer.php");
            exit;
        } else {
            $error = "Invalid username or password! Try: admin/admin123 or worker/worker123";
        }
    }
}

// If no users exist, create default ones
$check = mysqli_query($conn, "SELECT * FROM users");
if (mysqli_num_rows($check) == 0) {
    // Create default users
    mysqli_query($conn, "INSERT INTO users (username, password, role) VALUES 
        ('admin', 'admin123', 'admin'),
        ('worker', 'worker123', 'worker')");
    
    // Create a test customer with user account
    mysqli_query($conn, "INSERT INTO customer (number, name, phone, address, category, reg_date) VALUES 
        (1001, 'Test Customer', '9876543210', '123 Test Street', 'household', CURDATE())");
    
    mysqli_query($conn, "INSERT INTO users (username, password, role, number) VALUES 
        ('customer1', 'customer123', 'customer', 1001)");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Electricity System</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 350px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .input-group {
            margin-bottom: 20px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn:hover {
            background: #5a67d8;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .test-creds {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 14px;
        }
        .test-creds h4 {
            margin-top: 0;
            color: #666;
        }
        .role {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
        }
        .admin { background: #dc3545; color: white; }
        .worker { background: #ffc107; color: #212529; }
        .customer { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔌 Electricity Billing System</h2>
        
        <?php if($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="test-creds">
            <h4>Test Credentials:</h4>
            <p>
                <strong>Admin:</strong> admin / admin123 
                <span class="role admin">Admin</span>
            </p>
            <p>
                <strong>Worker:</strong> worker / worker123 
                <span class="role worker">Worker</span>
            </p>
            <p>
                <strong>Customer:</strong> customer1 / customer123 
                <span class="role customer">Customer</span>
            </p>
            <p style="margin-top: 10px; color: #666; font-size: 12px;">
                Need more customers? Register them in Admin panel.
            </p>
        </div>
    </div>
</body>
</html>