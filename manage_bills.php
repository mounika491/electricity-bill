
<?php
session_start();
include "config.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$customer_number = $_GET['customer'] ?? 0;

// Get customer details if specified
$customer = null;
if ($customer_number > 0) {
    $cust_sql = "SELECT * FROM customer WHERE number = '$customer_number'";
    $cust_result = mysqli_query($conn, $cust_sql);
    $customer = mysqli_fetch_assoc($cust_result);
}

// Handle bill deletion
if (isset($_POST['delete_bills'])) {
    $bill_ids = $_POST['bill_ids'] ?? [];
    
    if (!empty($bill_ids)) {
        mysqli_begin_transaction($conn);
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($bill_ids as $bill_id) {
            try {
                // Check if bill is fully paid (remaining_due = 0)
                $check_bill = mysqli_query($conn, "SELECT remaining_due, status, number FROM bill WHERE id = '$bill_id'");
                $bill_data = mysqli_fetch_assoc($check_bill);
                
                if (!$bill_data) {
                    $errors[] = "Bill #$bill_id not found";
                    $error_count++;
                    continue;
                }
                
                // Only allow deletion if bill is fully paid
                if ($bill_data['remaining_due'] > 0 || $bill_data['status'] != 'paid') {
                    $errors[] = "Bill #$bill_id has pending due (₹" . number_format($bill_data['remaining_due'], 2) . ") or is not fully paid";
                    $error_count++;
                    continue;
                }
                
                // Delete payments first
                mysqli_query($conn, "DELETE FROM payments WHERE bill_id = '$bill_id'");
                
                // Delete payment history
                mysqli_query($conn, "DELETE FROM payment_history WHERE bill_id = '$bill_id'");
                
                // Delete the bill
                $delete = mysqli_query($conn, "DELETE FROM bill WHERE id = '$bill_id'");
                
                if ($delete && mysqli_affected_rows($conn) > 0) {
                    $success_count++;
                } else {
                    $errors[] = "Failed to delete Bill #$bill_id";
                    $error_count++;
                }
                
            } catch (Exception $e) {
                $errors[] = "Error deleting Bill #$bill_id: " . $e->getMessage();
                $error_count++;
            }
        }
        
        if ($error_count == 0) {
            mysqli_commit($conn);
            $success_message = "✅ Successfully deleted $success_count paid bill(s)!";
        } else {
            mysqli_rollback($conn);
            $error_message = "❌ Deleted $success_count bill(s), failed to delete $error_count bill(s).";
            if (!empty($errors)) {
                $error_message .= "<br><small>" . implode("<br>", $errors) . "</small>";
            }
        }
    } else {
        $error_message = "❌ No bills selected for deletion.";
    }
}

// Get only paid bills (remaining_due = 0) for deletion
$where_clause = "WHERE b.remaining_due = 0 AND b.status = 'paid'";
if ($customer_number > 0) {
    $where_clause .= " AND b.number = '$customer_number'";
}

$sql = "SELECT b.*, c.name FROM bill b 
        JOIN customer c ON b.number = c.number 
        $where_clause
        ORDER BY b.year DESC, b.month DESC";
$result = mysqli_query($conn, $sql);

// Get all bills for customer (for display)
$all_bills_sql = $customer_number > 0 
    ? "SELECT b.*, c.name FROM bill b JOIN customer c ON b.number = c.number WHERE b.number = '$customer_number' ORDER BY b.year DESC, b.month DESC"
    : "SELECT b.*, c.name FROM bill b JOIN customer c ON b.number = c.number ORDER BY b.year DESC, b.month DESC";
$all_bills_result = mysqli_query($conn, $all_bills_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Bills</title>
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .pending { 
            background: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeaa7;
        }
        
        .partially_paid { 
            background: #cce5ff; 
            color: #004085; 
            border: 1px solid #b8daff;
        }
        
        .paid { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        
        .overdue { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .select-all {
            margin-right: 10px;
        }
        
        .delete-section {
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        
        .customer-info {
            background: #e8f4fc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        
        .paid-bills-section {
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        
        input[type="checkbox"] {
            transform: scale(1.2);
            cursor: pointer;
        }
        
        .no-bills {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .bill-amount {
            font-weight: bold;
        }
        
        .due-amount {
            color: #dc3545;
            font-weight: bold;
        }
        
        .paid-amount {
            color: #28a745;
            font-weight: bold;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        
        .tab.active {
            background: white;
            border-color: #dee2e6 #dee2e6 white;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        function toggleSelectAll(source) {
            const checkboxes = document.querySelectorAll('input[name="bill_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = source.checked;
            });
        }
        
        function confirmBulkDelete() {
            const checkboxes = document.querySelectorAll('input[name="bill_ids[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one fully paid bill to delete.');
                return false;
            }
            
            return confirm(`Are you sure you want to delete ${checkboxes.length} fully paid bill(s)?\n\nThis will also delete all related payment records.\nThis action cannot be undone!`);
        }
        
        function deleteSingleBill(billId, customerName) {
            if (confirm(`Delete Bill #${billId} for ${customerName}?\n\nThis will also delete all related payment records.\nThis action cannot be undone!`)) {
                // Create a form to submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'bill_ids[]';
                input.value = billId;
                form.appendChild(input);
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'delete_bills';
                submitInput.value = '1';
                form.appendChild(submitInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Activate tab button
            event.target.classList.add('active');
        }
        
        function markAllBillsPaid(customerNumber) {
            if (confirm('Mark all pending bills as paid for this customer?\n\nThis will create payment records for the full amount.')) {
                window.location.href = 'mark_all_paid.php?customer=' + customerNumber;
            }
        }
        
        function deleteCustomer(customerNumber) {
            if (confirm('Delete this customer and all their data?\n\nThis will delete:\n• Customer record\n• User account\n• All meter readings\n• All bills (including paid ones)\n\nThis action cannot be undone!')) {
                window.location.href = 'view_customers.php?force_delete=' + customerNumber;
            }
        }
    </script>
</head>
<body>
    <div class="header">
        <div class="logo">🗑️ Manage Bills & Customers</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="view_bills.php">← All Bills</a>
            <a href="view_customers.php">Customers</a>
            <a href="admin.php">Dashboard</a>
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
            <?php if($customer): ?>
                <h2>Manage Bills for <?php echo htmlspecialchars($customer['name']); ?> (Meter: <?php echo $customer_number; ?>)</h2>
                
                <div class="customer-info">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                        <div>
                            <strong>Customer:</strong> <?php echo htmlspecialchars($customer['name']); ?>
                        </div>
                        <div>
                            <strong>Meter Number:</strong> <?php echo $customer['number']; ?>
                        </div>
                        <div>
                            <strong>Phone:</strong> <?php echo $customer['phone']; ?>
                        </div>
                        <div>
                            <strong>Category:</strong> <?php echo ucfirst($customer['category']); ?>
                        </div>
                    </div>
                    <div style="margin-top: 15px;">
                        <a href="view_customers.php" class="btn btn-secondary">← Back to Customers</a>
                        <a href="edit_customer.php?id=<?php echo $customer_number; ?>" class="btn btn-primary">✎ Edit Customer</a>
                        <button onclick="deleteCustomer(<?php echo $customer_number; ?>)" class="btn btn-danger">🗑️ Delete Customer</button>
                    </div>
                </div>
                
                <!-- Tabs -->
                <div class="tabs">
                    <div class="tab active" onclick="showTab('all-bills-tab')">All Bills</div>
                    <div class="tab" onclick="showTab('paid-bills-tab')">Paid Bills (Can Delete)</div>
                </div>
                
                <!-- Tab 1: All Bills -->
                <div id="all-bills-tab" class="tab-content active">
                    <h3>All Bills for This Customer</h3>
                    
                    <?php if(mysqli_num_rows($all_bills_result) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Bill ID</th>
                                    <th>Month/Year</th>
                                    <th>Units</th>
                                    <th>Total Amount</th>
                                    <th>Paid</th>
                                    <th>Due</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_billed = 0;
                                $total_paid = 0;
                                $total_due = 0;
                                while($row = mysqli_fetch_assoc($all_bills_result)): 
                                    $status = strtolower($row['status']);
                                    $month_name = date('M', mktime(0,0,0,$row['month'],1));
                                    $paid = $row['paid_amount'] ?? 0;
                                    $due = $row['remaining_due'] ?? $row['total'];
                                    
                                    $total_billed += $row['total'];
                                    $total_paid += $paid;
                                    $total_due += $due;
                                ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo $month_name . ' ' . $row['year']; ?></td>
                                    <td><?php echo $row['units']; ?> kWh</td>
                                    <td class="bill-amount">₹<?php echo number_format($row['total'], 2); ?></td>
                                    <td class="paid-amount">₹<?php echo number_format($paid, 2); ?></td>
                                    <td class="due-amount">₹<?php echo number_format($due, 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $status; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['due_date']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="print_bill.php?id=<?php echo $row['id']; ?>" class="btn btn-primary" target="_blank">View</a>
                                            
                                            <?php if($due > 0): ?>
                                                <a href="make_payment.php?id=<?php echo $row['id']; ?>" class="btn btn-success">Receive Payment</a>
                                            <?php endif; ?>
                                            
                                            <?php if($due == 0 && $status == 'paid'): ?>
                                                <button onclick="deleteSingleBill(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>')" 
                                                        class="btn btn-danger">
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        
                        <!-- Summary -->
                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                            <strong>Summary:</strong>
                            <span style="margin-left: 20px;">Total Billed: ₹<?php echo number_format($total_billed, 2); ?></span>
                            <span style="margin-left: 20px; color: #28a745;">Total Paid: ₹<?php echo number_format($total_paid, 2); ?></span>
                            <span style="margin-left: 20px; color: #dc3545;">Total Due: ₹<?php echo number_format($total_due, 2); ?></span>
                        </div>
                        
                        <?php if($total_due > 0): ?>
                            <div class="warning" style="margin-top: 20px; padding: 15px;">
                                ⚠️ This customer has pending dues. You cannot delete them until all bills are fully paid.
                                <button onclick="markAllBillsPaid(<?php echo $customer_number; ?>)" class="btn btn-warning" style="margin-left: 20px;">
                                    Mark All as Paid
                                </button>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="no-bills">
                            <h3>No bills found for this customer</h3>
                            <p>You can delete this customer since they have no bills.</p>
                            <button onclick="deleteCustomer(<?php echo $customer_number; ?>)" class="btn btn-danger" style="margin-top: 15px;">
                                🗑️ Delete Customer
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Tab 2: Paid Bills (Can Delete) -->
                <div id="paid-bills-tab" class="tab-content">
                    <h3>Paid Bills - Can Be Deleted</h3>
                    <div class="paid-bills-section">
                        <p><strong>Note:</strong> Only fully paid bills (₹0 due amount) can be deleted. Deleting bills will also remove all payment records.</p>
                        
                        <form method="POST" onsubmit="return confirmBulkDelete()">
                            <?php if(mysqli_num_rows($result) > 0): ?>
                                <button type="submit" name="delete_bills" class="btn btn-danger">
                                    🗑️ Delete Selected Paid Bills
                                </button>
                            <?php endif; ?>
                    </div>
                    
                    <?php if(mysqli_num_rows($result) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" class="select-all" onclick="toggleSelectAll(this)" title="Select All">
                                    </th>
                                    <th>Bill ID</th>
                                    <th>Month/Year</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php mysqli_data_seek($result, 0); // Reset pointer ?>
                                <?php while($row = mysqli_fetch_assoc($result)): 
                                    $month_name = date('M', mktime(0,0,0,$row['month'],1));
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="bill_ids[]" value="<?php echo $row['id']; ?>">
                                    </td>
                                    <td>#<?php echo $row['id']; ?></td>
                                    <td><?php echo $month_name . ' ' . $row['year']; ?></td>
                                    <td class="bill-amount">₹<?php echo number_format($row['total'], 2); ?></td>
                                    <td>
                                        <span class="status-badge paid">
                                            Fully Paid
                                        </span>
                                    </td>
                                    <td><?php echo $row['due_date']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="print_bill.php?id=<?php echo $row['id']; ?>" class="btn btn-primary" target="_blank">View</a>
                                            <button onclick="deleteSingleBill(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>')" 
                                                    class="btn btn-danger">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        </form> <!-- Close the form here -->
                        
                        <div class="message warning" style="margin-top: 20px;">
                            ⚠️ <strong>Warning:</strong> Deleting bills will permanently remove them from the system along with all payment records. 
                            This action cannot be undone. Only delete if absolutely necessary.
                        </div>
                    <?php else: ?>
                        <div class="no-bills">
                            <h3>No fully paid bills found</h3>
                            <p>Only bills with ₹0 due amount and status "Paid" can be deleted.</p>
                            <p>Check the "All Bills" tab to see pending bills.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <h2>Manage All Bills</h2>
                <div class="message warning">
                    ⚠️ <strong>Note:</strong> Please select a customer from the <a href="view_customers.php">Customers page</a> to manage their bills.
                    This page is for managing bills for specific customers.
                </div>
                <a href="view_customers.php" class="btn btn-primary">← Go to Customers</a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Initialize first tab as active
        document.addEventListener('DOMContentLoaded', function() {
            showTab('all-bills-tab');
        });
    </script>
</body>
</html>