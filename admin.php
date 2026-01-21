
<?php
session_start();

// SIMPLE AUTH CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Connect to database
$conn = mysqli_connect("localhost", "root", "", "electricity");
if (!$conn) {
    die("Database connection failed");
}

$success = "";
$error = "";
$new_username = "";
$new_password = "";

// Handle customer registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $number = $_POST['number'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $category = $_POST['category'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($number) || empty($name) || empty($phone) || empty($address) || empty($category) || empty($username) || empty($password)) {
        $error = "Please fill all required fields!";
    } elseif (!is_numeric($number) || $number < 1000 || $number > 99999) {
        $error = "Meter number must be between 1000 and 99999!";
    } else {
        // Check if meter number already exists
        $check_meter = mysqli_query($conn, "SELECT number FROM customer WHERE number = '$number'");
        if (mysqli_num_rows($check_meter) > 0) {
            $error = "Meter number $number already exists!";
        } else {
            // Check if username already exists
            $check_user = mysqli_query($conn, "SELECT username FROM users WHERE username = '$username'");
            if (mysqli_num_rows($check_user) > 0) {
                $error = "Username '$username' already exists! Choose a different username.";
            } else {
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Insert customer
                    $sql1 = "INSERT INTO customer (number, name, phone, address, email, category, reg_date) 
                            VALUES ('$number', '$name', '$phone', '$address', '$email', '$category', CURDATE())";
                    
                    if (!mysqli_query($conn, $sql1)) {
                        throw new Exception("Customer insert failed: " . mysqli_error($conn));
                    }
                    
                    // Insert user account for customer
                    $sql2 = "INSERT INTO users (username, password, role, number) 
                            VALUES ('$username', '$password', 'customer', '$number')";
                    
                    if (!mysqli_query($conn, $sql2)) {
                        throw new Exception("User account creation failed: " . mysqli_error($conn));
                    }
                    
                    mysqli_commit($conn);
                    $success = "✅ Customer registered successfully!<br>";
                    $success .= "Username: <strong>$username</strong><br>";
                    $success .= "Password: <strong>$password</strong><br>";
                    $success .= "Customer can now login with these credentials.";
                    
                    // Store for autofill
                    $new_username = $username;
                    $new_password = $password;
                    
                    // Clear form fields
                    echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            document.querySelector("input[name=\"number\"]").value = "";
                            document.querySelector("input[name=\"name\"]").value = "";
                            document.querySelector("input[name=\"phone\"]").value = "";
                            document.querySelector("textarea[name=\"address\"]").value = "";
                            document.querySelector("input[name=\"email\"]").value = "";
                            document.querySelector("input[name=\"username\"]").value = "";
                            document.querySelector("input[name=\"password\"]").value = "";
                        });
                    </script>';
                    
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = "❌ Error: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial; background: #f5f5f5; }
        .header { 
            background: #2c3e50; 
            color: white; 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
        }
        .nav a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .nav a:hover {
            background: #34495e;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 20px;
        }
        .card {
            background: white;
            padding: 25px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        button {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #218838;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        .customer-name-uppercase {
            text-transform: uppercase;
            font-weight: bold;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        .required:after {
            content: " *";
            color: #dc3545;
        }
        .credentials-box {
            background: #e8f4fc;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
            border-left: 4px solid #3498db;
        }
        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        .household { color: #28a745; }
        .commercial { color: #17a2b8; }
        .industrial { color: #dc3545; }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-btn {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-edit { background: #3498db; color: white; }
        .btn-bills { background: #17a2b8; color: white; }
        .btn-reading { background: #28a745; color: white; }
        .quick-links {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .quick-link {
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .quick-link:hover {
            opacity: 0.9;
        }
    </style>
    <script>
        function copyCredentials() {
            const username = document.querySelector('input[name="username"]').value;
            const password = document.querySelector('input[name="password"]').value;
            
            if (username && password) {
                const text = `Username: ${username}\nPassword: ${password}`;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Credentials copied to clipboard!');
                });
            }
        }
    </script>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">⚡ Admin Dashboard</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="admin.php" style="background: #3498db;">Dashboard</a>
            <a href="view_customers.php">Customers</a>
            <a href="view_bills.php">Bills</a>
            <a href="generate_bill.php">Generate Bill</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
        
    <div class="container">
        <!-- Statistics -->
        <div class="stat-cards">
            <?php
            $total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM customer"))['count'];
            $total_bills = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM bill"))['count'];
            $total_readings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM readings"))['count'];
            $total_payments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM payments"))['count'];
            ?>
            
            <div class="stat-card">
                <div class="stat-label">Total Customers</div>
                <div class="stat-value"><?php echo $total_customers; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Total Bills</div>
                <div class="stat-value"><?php echo $total_bills; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Meter Readings</div>
                <div class="stat-value"><?php echo $total_readings; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Payments</div>
                <div class="stat-value"><?php echo $total_payments; ?></div>
            </div>
        </div>
        
        <!-- Add New Customer -->
        <div class="card">
            <h2>Add New Customer</h2>
            
            <?php if($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="message success"><?php echo $success; ?></div>
                <?php if($new_username && $new_password): ?>
                <div class="credentials-box">
                    <strong>New Customer Credentials:</strong><br>
                    <strong>Username:</strong> <?php echo $new_username; ?><br>
                    <strong>Password:</strong> <?php echo $new_password; ?><br>
                    <small>Customer can login at: <?php echo $_SERVER['HTTP_HOST']; ?>/login.php</small>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Meter Number</label>
                        <input type="number" name="number" min="1000" max="99999" required 
                               placeholder="e.g., 1001">
                        <small style="color: #666;">Must be between 1000 and 99999</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Customer Name</label>
                        <input type="text" name="name" required placeholder="Full Name">
                        <small style="color: #666;">Will appear in UPPERCASE on bills</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Phone Number</label>
                        <input type="text" name="phone" required placeholder="10-digit mobile number">
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="customer@example.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="required">Address</label>
                    <textarea name="address" required placeholder="Full address with area, city, pincode"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Category</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <option value="household">Household</option>
                            <option value="commercial">Commercial</option>
                            <option value="industrial">Industrial</option>
                        </select>
                        <small style="color: #666;">Affects billing rates and minimum charges</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Username</label>
                        <input type="text" name="username" required placeholder="Login username">
                        <small style="color: #666;">For customer login</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Password</label>
                        <input type="text" name="password" required placeholder="Login password">
                        <small style="color: #666;">For customer login</small>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 20px;">
                    <button type="submit" name="register" class="btn-success">➕ Add Customer</button>
                    <button type="button" onclick="copyCredentials()" class="btn" style="background: #6c757d;">
                        📋 Copy Credentials
                    </button>
                    <button type="reset" class="btn" style="background: #ffc107; color: #212529;">
                        🗑️ Clear Form
                    </button>
                </div>
            </form>
        </div>
        
        <!-- All Customers -->
        <div class="card">
            <h2>All Customers (<?php echo $total_customers; ?>)</h2>
            
            <?php
            $result = mysqli_query($conn, "SELECT * FROM customer ORDER BY number DESC");
            if (mysqli_num_rows($result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Meter No.</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Category</th>
                            <th>Address</th>
                            <th>Reg. Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $row['number']; ?></td>
                            <td class="customer-name-uppercase"><?php echo htmlspecialchars(strtoupper($row['name'])); ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td>
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 12px; 
                                      background: <?php echo $row['category'] == 'household' ? '#d4edda' : 
                                                          ($row['category'] == 'commercial' ? '#cce5ff' : '#f8d7da'); ?>;">
                                    <?php echo ucfirst($row['category']); ?>
                                </span>
                            </td>
                            <td title="<?php echo htmlspecialchars($row['address']); ?>">
                                <?php echo strlen($row['address']) > 30 ? substr($row['address'], 0, 30) . '...' : $row['address']; ?>
                            </td>
                            <td><?php echo $row['reg_date']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_customer.php?id=<?php echo $row['number']; ?>" 
                                       class="action-btn btn-edit">
                                        Edit
                                    </a>
                                    <a href="view_bills.php?customer=<?php echo $row['number']; ?>" 
                                       class="action-btn btn-bills">
                                        Bills
                                    </a>
                                    <a href="add_reading.php?number=<?php echo $row['number']; ?>" 
                                       class="action-btn btn-reading">
                                        Reading
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No customers found</h3>
                    <p>Add your first customer using the form above.</p>
                </div>
            <?php endif; ?>
            
            <!-- Category Summary -->
            <?php
            $category_result = mysqli_query($conn, "SELECT category, COUNT(*) as count FROM customer GROUP BY category");
            if (mysqli_num_rows($category_result) > 0): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <strong>Customer Categories:</strong>
                    <?php while($cat = mysqli_fetch_assoc($category_result)): ?>
                        <span style="margin-left: 15px;">
                            <span class="stat-value <?php echo $cat['category']; ?>"><?php echo $cat['count']; ?></span>
                            <?php echo ucfirst($cat['category']); ?>
                        </span>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Links -->
        <div class="quick-links">
            <a href="generate_bill.php" class="quick-link">🧾 Generate New Bill</a>
            <a href="add_reading.php" class="quick-link">📝 Add Meter Reading</a>
            <a href="view_bills.php" class="quick-link">📄 View All Bills</a>
        </div>
    </div>
</body>
</html>