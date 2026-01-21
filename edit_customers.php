
<?php
session_start();
include "config.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$customer_number = $_GET['id'] ?? 0;
$customer = null;
$success = "";
$error = "";

// Get customer details
if ($customer_number > 0) {
    $sql = "SELECT * FROM customer WHERE number = '$customer_number'";
    $result = mysqli_query($conn, $sql);
    if (mysqli_num_rows($result) > 0) {
        $customer = mysqli_fetch_assoc($result);
    } else {
        $error = "Customer not found!";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $number = $_POST['number'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $address = $_POST['address'];
    $category = $_POST['category'];
    
    // Validate inputs
    if (empty($name) || empty($phone) || empty($address) || empty($category)) {
        $error = "Please fill all required fields!";
    } else {
        // Update customer
        $update_sql = "UPDATE customer SET 
                       name = '$name',
                       phone = '$phone',
                       email = '$email',
                       address = '$address',
                       category = '$category'
                       WHERE number = '$number'";
        
        if (mysqli_query($conn, $update_sql)) {
            $success = "✅ Customer updated successfully!";
            // Refresh customer data
            $result = mysqli_query($conn, $sql);
            $customer = mysqli_fetch_assoc($result);
        } else {
            $error = "❌ Error updating customer: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Customer</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 20px;
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
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h2 {
            color: #2c3e50;
            margin-bottom: 20px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .required:after {
            content: " *";
            color: #dc3545;
        }
        
        .customer-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .info-row {
            margin-bottom: 5px;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            display: inline-block;
            width: 150px;
        }
        
        .customer-name-uppercase {
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
            color: #2c3e50;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        
        .related-actions {
            background: #e8f4fc;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
            border-left: 4px solid #3498db;
        }
        
        .related-actions h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">✎ Edit Customer</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="view_customers.php">← Back to Customers</a>
            <a href="admin.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if($error && !$customer): ?>
            <div class="card">
                <div class="message error"><?php echo $error; ?></div>
                <a href="view_customers.php" class="btn">← Back to Customers</a>
            </div>
        <?php else: ?>
            <div class="card">
                <h2>Edit Customer #<?php echo $customer_number; ?></h2>
                
                <?php if($success): ?>
                    <div class="message success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Customer Information Display -->
                <div class="customer-info">
                    <h3 style="margin-top: 0; color: #2c3e50;">Current Customer Information</h3>
                    <div class="info-row">
                        <span class="info-label">Meter Number:</span> 
                        <strong><?php echo $customer['number']; ?></strong>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Customer Name:</span> 
                        <span class="customer-name-uppercase"><?php echo htmlspecialchars(strtoupper($customer['name'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Registration Date:</span> 
                        <?php echo $customer['reg_date']; ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Current Category:</span> 
                        <span style="background: <?php 
                            echo $customer['category'] == 'household' ? '#d4edda' : 
                                  ($customer['category'] == 'commercial' ? '#cce5ff' : '#f8d7da'); 
                            ?>; padding: 3px 8px; border-radius: 12px; font-size: 12px;">
                            <?php echo ucfirst($customer['category']); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Edit Form -->
                <div class="form-section">
                    <h3>Edit Customer Details</h3>
                    <form method="POST">
                        <input type="hidden" name="number" value="<?php echo $customer['number']; ?>">
                        
                        <div class="form-group">
                            <label class="required">Customer Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                Note: Customer name will appear in UPPERCASE on printed bills
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Phone Number</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Address</label>
                            <textarea name="address" required><?php echo htmlspecialchars($customer['address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="required">Category</label>
                            <select name="category" required>
                                <option value="household" <?php echo $customer['category'] == 'household' ? 'selected' : ''; ?>>Household</option>
                                <option value="commercial" <?php echo $customer['category'] == 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                                <option value="industrial" <?php echo $customer['category'] == 'industrial' ? 'selected' : ''; ?>>Industrial</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-success">💾 Update Customer</button>
                            <a href="view_customers.php" class="btn">Cancel</a>
                            <a href="admin.php" class="btn" style="background: #6c757d;">← Dashboard</a>
                        </div>
                    </form>
                </div>
                
                <!-- Related Actions -->
                <div class="related-actions">
                    <h3>Related Actions</h3>
                    <p>Perform other actions for this customer:</p>
                    <div class="action-buttons">
                        <a href="add_reading.php?number=<?php echo $customer['number']; ?>" class="btn" style="background: #17a2b8;">
                            📝 Add Meter Reading
                        </a>
                        <a href="generate_bill.php?prefill=<?php echo $customer['number']; ?>" class="btn" style="background: #28a745;">
                            🧾 Generate Bill
                        </a>
                        <a href="view_bills.php?customer=<?php echo $customer['number']; ?>" class="btn" style="background: #6c757d;">
                            📄 View Bills
                        </a>
                        <?php if($_SESSION['role'] == 'admin'): ?>
                            <a href="view_customers.php?delete=<?php echo $customer['number']; ?>" 
                               class="btn" style="background: #dc3545;"
                               onclick="return confirm('Delete customer <?php echo htmlspecialchars(addslashes($customer['name'])); ?>?')">
                                🗑️ Delete Customer
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Preview how name will appear on bill -->
                    <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 5px; border: 1px solid #ddd;">
                        <h4 style="margin-top: 0; color: #666;">Name Preview on Bill:</h4>
                        <div style="font-size: 18px; text-transform: uppercase; font-weight: bold; letter-spacing: 1px; color: #2c3e50; padding: 10px; background: #f8f9fa; border-radius: 3px;">
                            <?php echo htmlspecialchars(strtoupper($customer['name'])); ?>
                        </div>
                        <small style="color: #666;">This is how the customer name will appear on printed bills</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Preview name as uppercase in real-time
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.querySelector('input[name="name"]');
            const namePreview = document.querySelector('.related-actions div:last-child div');
            
            nameInput.addEventListener('input', function() {
                namePreview.textContent = this.value.toUpperCase();
            });
        });
    </script>
</body>
</html>