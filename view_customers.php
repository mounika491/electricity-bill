 
<?php
session_start();
include "config.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Handle customer deletion
// Handle customer deletion (regular)
if (isset($_GET['delete'])) {
    $customer_number = $_GET['delete'];
    
    // Check if customer has any unpaid bills
    $check_unpaid_bills = mysqli_query($conn, "SELECT id FROM bill WHERE number = '$customer_number' AND (remaining_due > 0 OR status != 'paid')");
    
    if (mysqli_num_rows($check_unpaid_bills) > 0) {
        $error_message = "❌ Cannot delete customer with unpaid bills. Please ensure all bills are fully paid first.";
    } else {
        // Check if customer has any bills at all
        $check_any_bills = mysqli_query($conn, "SELECT id FROM bill WHERE number = '$customer_number'");
        
        if (mysqli_num_rows($check_any_bills) > 0) {
            // Customer has paid bills - redirect to manage bills page
            header("Location: manage_bills.php?customer=" . $customer_number);
            exit;
        } else {
            // Customer has no bills - proceed with deletion
            deleteCustomer($customer_number);
        }
    }
}

// Handle force delete (from manage_bills.php)
// Handle customer deletion
if (isset($_GET['delete'])) {
    $customer_number = $_GET['delete'];
    
    // Check if customer has any bills
    $check_bills = mysqli_query($conn, "SELECT id, remaining_due, status FROM bill WHERE number = '$customer_number'");
    $has_bills = mysqli_num_rows($check_bills) > 0;
    $has_unpaid_bills = false;
    
    while($bill = mysqli_fetch_assoc($check_bills)) {
        if ($bill['remaining_due'] > 0 || $bill['status'] != 'paid') {
            $has_unpaid_bills = true;
            break;
        }
    }
    
    if ($has_unpaid_bills) {
        $error_message = "❌ Cannot delete customer with unpaid bills. Please ensure all bills are fully paid first.";
    } elseif ($has_bills) {
        // Customer has only paid bills - ask user to delete bills first
        $error_message = "⚠️ Customer has paid bills that need to be deleted first.<br>
                         <a href='view_bills.php?customer=$customer_number' style='color: #3498db; text-decoration: underline;'>
                         Click here to delete their paid bills first</a>, then delete the customer.";
    } else {
        // Customer has no bills - proceed with deletion
        deleteCustomer($customer_number);
    }
}

function deleteCustomer($customer_number) {
    global $conn;
    
    mysqli_begin_transaction($conn);
    
    try {
        // 1. Delete user account
        mysqli_query($conn, "DELETE FROM users WHERE number = '$customer_number'");
        
        // 2. Delete readings
        mysqli_query($conn, "DELETE FROM readings WHERE number = '$customer_number'");
        
        // 3. Delete customer
        $delete_customer = mysqli_query($conn, "DELETE FROM customer WHERE number = '$customer_number'");
        
        if ($delete_customer) {
            mysqli_commit($conn);
            $success_message = "✅ Customer deleted successfully!";
        } else {
            throw new Exception("Failed to delete customer: " . mysqli_error($conn));
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = "❌ Error: " . $e->getMessage();
    }
}

// Get all customers
$customers = mysqli_query($conn, "SELECT * FROM customer ORDER BY number");
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Customers</title>
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
            font-size: 24px;
            font-weight: bold;
        }
        
        .nav a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 5px 10px;
            border-radius: 3px;
            transition: background 0.3s;
        }
        
        .nav a:hover {
            background: #34495e;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .message {
            padding: 15px;
            border-radius: 5px;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .household { background: #d4edda; color: #155724; }
        .commercial { background: #cce5ff; color: #004085; }
        .industrial { background: #f8d7da; color: #721c24; }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-view-bills {
            background: #28a745;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .modal-close {
            cursor: pointer;
            font-size: 24px;
            color: #999;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .customer-count {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
    <script>
        function confirmDelete(customerNumber, customerName) {
            if (confirm(`Are you sure you want to delete customer "${customerName}" (Meter: ${customerNumber})?\n\nThis will also delete their user account and all meter readings.`)) {
                window.location.href = 'view_customers.php?delete=' + customerNumber;
            }
        }
        
        function showDeleteModal(customerNumber, customerName) {
            document.getElementById('delete-customer-name').innerText = customerName;
            document.getElementById('delete-customer-number').innerText = customerNumber;
            document.getElementById('delete-modal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('delete-modal').style.display = 'none';
        }
        
        function confirmDeleteFinal() {
            var customerNumber = document.getElementById('delete-customer-number').innerText;
            window.location.href = 'view_customers.php?delete=' + customerNumber;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('delete-modal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        function viewBills(customerNumber) {
            window.location.href = 'manage_bills.php?customer=' + customerNumber;
        }
        function editCustomer(customerNumber) {
            window.location.href = 'edit_customer.php?id=' + customerNumber;
        }
        
        function checkForBills(customerNumber, customerName) {
            // In a real implementation, you would make an AJAX call here
            // For now, we'll show the delete modal
            showDeleteModal(customerNumber, customerName);
        }
    </script>
</head>
<body>
    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">⚠️ Confirm Deletion</div>
                <span class="modal-close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete customer <strong id="delete-customer-name"></strong> (Meter: <strong id="delete-customer-number"></strong>)?</p>
                <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <strong>⚠️ WARNING:</strong> This will permanently delete:
                    <ul style="margin: 5px 0 0 20px;">
                        <li>Customer record</li>
                        <li>User login account</li>
                        <li>All meter readings</li>
                    </ul>
                </div>
                <p><strong>Note:</strong> If the customer has bills, you must delete them first.</p>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal()" class="btn" style="background: #6c757d;">Cancel</button>
                <button onclick="confirmDeleteFinal()" class="btn btn-delete">Delete Permanently</button>
            </div>
        </div>
    </div>
    
    <div class="header">
        <div class="logo">👥 Manage Customers</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="admin.php">Dashboard</a>
            <a href="view_customers.php" style="background: #3498db;">Customers</a>
            <a href="view_bills.php">Bills</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if(isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>All Customers</h2>
                <a href="admin.php" class="btn" style="background: #28a745;">+ Add New Customer</a>
            </div>
            
            <?php 
            $customer_count = mysqli_num_rows($customers);
            mysqli_data_seek($customers, 0); // Reset pointer
            ?>
            
            <div class="customer-count">
                <strong>Total Customers:</strong> <?php echo $customer_count; ?>
            </div>
            
            <?php if($customer_count > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Meter No.</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Category</th>
                            <th>Address</th>
                            <th>Reg. Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($customers)): ?>
                        <tr>
                            <td><?php echo $row['number']; ?></td>
                            <td style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars(strtoupper($row['name'])); ?></td>
                            <td><?php echo $row['phone']; ?></td>
                            <td><?php echo $row['email'] ? htmlspecialchars($row['email']) : 'N/A'; ?></td>
                            <td>
                                <span class="badge <?php echo $row['category']; ?>">
                                    <?php echo ucfirst($row['category']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['address']); ?></td>
                            <td><?php echo $row['reg_date']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="viewBills(<?php echo $row['number']; ?>)" 
                                            class="btn btn-view-bills" title="View Bills">
                                        📄 Bills
                                    </button>
                                    <button onclick="editCustomer(<?php echo $row['number']; ?>)" 
                                            class="btn btn-edit" title="Edit Customer">
                                        ✎ Edit
                                    </button>
                                    <button onclick="checkForBills(<?php echo $row['number']; ?>, '<?php echo addslashes($row['name']); ?>')" 
                                            class="btn btn-delete" title="Delete Customer">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Summary -->
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <strong>Customer Categories:</strong>
                    <?php
                    $categories = mysqli_query($conn, "SELECT category, COUNT(*) as count FROM customer GROUP BY category");
                    while($cat = mysqli_fetch_assoc($categories)) {
                        echo "<span class='badge {$cat['category']}' style='margin-left: 10px;'>{$cat['category']}: {$cat['count']}</span>";
                    }
                    ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No customers found</h3>
                    <p>You haven't added any customers yet.</p>
                    <a href="admin.php" class="btn" style="background: #3498db; margin-top: 15px; padding: 10px 20px;">
                        + Add Your First Customer
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Links -->
        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <a href="admin.php" class="btn" style="background: #17a2b8;">← Back to Admin Dashboard</a>
            <a href="generate_bill.php" class="btn" style="background: #28a745;">Generate New Bill</a>
        </div>
    </div>
</body>
</html>
 