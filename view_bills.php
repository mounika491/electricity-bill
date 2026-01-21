<?php
session_start();
include "config.php";
// Handle bill deletion
// Handle bill deletion (only for paid bills)
if (isset($_GET['delete_bill'])) {
    $bill_id = $_GET['delete_bill'];
    
    // Check if bill is fully paid
    $check_bill = mysqli_query($conn, "SELECT remaining_due, status, number, total FROM bill WHERE id = '$bill_id'");
    $bill_data = mysqli_fetch_assoc($check_bill);
    
    if (!$bill_data) {
        $error_message = "❌ Bill not found!";
    } elseif ($bill_data['remaining_due'] > 0 || $bill_data['status'] != 'paid') {
        $error_message = "❌ Cannot delete bill #$bill_id. It has ₹" . number_format($bill_data['remaining_due'], 2) . " pending due.";
    } else {
        // Start transaction for deletion
        mysqli_begin_transaction($conn);
        
        try {
            // Delete payments first
            mysqli_query($conn, "DELETE FROM payments WHERE bill_id = '$bill_id'");
            
            // Delete payment history
            mysqli_query($conn, "DELETE FROM payment_history WHERE bill_id = '$bill_id'");
            
            // Delete the bill
            $delete = mysqli_query($conn, "DELETE FROM bill WHERE id = '$bill_id'");
            
            if ($delete && mysqli_affected_rows($conn) > 0) {
                mysqli_commit($conn);
                $success_message = "✅ Bill #$bill_id (₹" . number_format($bill_data['total'], 2) . ") deleted successfully!";
            } else {
                throw new Exception("Failed to delete bill.");
            }
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = "❌ Error deleting bill: " . $e->getMessage();
        }
    }
}
// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Get all bills
$sql = "SELECT b.*, c.name, c.category FROM bill b 
        JOIN customer c ON b.number = c.number 
        ORDER BY b.year DESC, b.month DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Bills</title>
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
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-paid {
            background: #28a745;
            color: white;
        }
        
        .btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .btn-edit {
            background: #ffc107;
            color: #212529;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .search-box button {
            background: #3498db;
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .filter-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
    </style>
    <script>
        function filterBills(status) {
            // Remove active class from all buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Show/hide rows based on status
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowStatus = row.querySelector('.status-badge').textContent.toLowerCase();
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function searchBills() {
            const searchTerm = document.getElementById('search').value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function markPaid(billId) {
            if (confirm('Mark this bill as fully paid?')) {
                window.location.href = 'mark_paid.php?id=' + billId;
            }
        }
       function deleteBill(billId, customerName, billAmount) {
            if (confirm(`Delete Bill #${billId} for ${customerName}?\n\nAmount: ₹${billAmount.toFixed(2)}\n\nThis bill is fully paid and can be deleted.\nThis will also delete all related payment records.\nThis action cannot be undone!`)) {
                window.location.href = 'view_bills.php?delete_bill=' + billId;
            }
        }
    </script>
</head>
<body>
    <div class="header">
        <div class="logo">🧾 All Bills</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="admin.php">← Dashboard</a>
            <a href="generate_bill.php">Generate Bill</a>
            <a href="view_customers.php">Customers</a>
            <a href="view_bills.php" style="background: #3498db;">Bills</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>All Electricity Bills</h2>
            
            <!-- Search and Filters -->
            <div class="search-box">
                <input type="text" id="search" placeholder="Search by customer name, meter number, bill ID..." onkeyup="searchBills()">
                <button onclick="searchBills()">Search</button>
            </div>
            
            <div class="filters">
                <button class="filter-btn active" onclick="filterBills('all')">All</button>
                <button class="filter-btn" onclick="filterBills('pending')">Pending</button>
                <button class="filter-btn" onclick="filterBills('partially_paid')">Partially Paid</button>
                <button class="filter-btn" onclick="filterBills('paid')">Paid</button>
                <button class="filter-btn" onclick="filterBills('overdue')">Overdue</button>
            </div>
            
            <?php if(mysqli_num_rows($result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Customer</th>
                            <th>Meter</th>
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
                        <?php while($row = mysqli_fetch_assoc($result)): 
                            $paid = $row['paid_amount'] ?? 0;
                            $due = $row['remaining_due'] ?? $row['total'];
                            $status = strtolower($row['status']);
                        ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars(strtoupper($row['name'])); ?></td>
                            <td><?php echo $row['number']; ?></td>
                            <td><?php echo date('M', mktime(0,0,0,$row['month'],1)) . ' ' . $row['year']; ?></td>
                            <td><?php echo $row['units']; ?> kWh</td>
                            <td>₹<?php echo number_format($row['total'], 2); ?></td>
                            <td>₹<?php echo number_format($paid, 2); ?></td>
                            <td>₹<?php echo number_format($due, 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $status; ?>">
                                    <?php 
                                    $status_text = ucfirst(str_replace('_', ' ', $row['status']));
                                    echo $status_text; 
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $row['due_date']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="print_bill.php?id=<?php echo $row['id']; ?>" class="btn btn-view" target="_blank">View</a>
                                    
                                    <?php if($due > 0): ?>
                                        <a href="make_payment.php?id=<?php echo $row['id']; ?>" class="btn btn-paid">Receive Payment</a>
                                        <button onclick="markPaid(<?php echo $row['id']; ?>)" class="btn" style="background: #28a745; color: white;">Mark Paid</button>
                                    <?php else: ?>
                                        <!-- Only show delete button for fully paid bills -->
                                        <button onclick="deleteBill(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', <?php echo $row['total']; ?>)" 
                                                class="btn" style="background: #dc3545; color: white;">
                                            🗑️ Delete
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
                    <?php
                    $summary_sql = "SELECT 
                        COUNT(*) as total_bills,
                        SUM(total) as total_amount,
                        SUM(paid_amount) as total_paid,
                        SUM(remaining_due) as total_due,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                        COUNT(CASE WHEN status = 'partially_paid' THEN 1 END) as partial,
                        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid
                    FROM bill";
                    
                    $summary = mysqli_fetch_assoc(mysqli_query($conn, $summary_sql));
                    ?>
                    
                    <h4>Bill Summary</h4>
                    <div style="display: flex; gap: 30px; flex-wrap: wrap; margin-top: 10px;">
                        <div>
                            <strong>Total Bills:</strong> <?php echo $summary['total_bills']; ?>
                        </div>
                        <div>
                            <strong>Total Amount:</strong> ₹<?php echo number_format($summary['total_amount'], 2); ?>
                        </div>
                        <div>
                            <strong>Total Paid:</strong> <span style="color: #28a745;">₹<?php echo number_format($summary['total_paid'], 2); ?></span>
                        </div>
                        <div>
                            <strong>Total Due:</strong> <span style="color: #dc3545;">₹<?php echo number_format($summary['total_due'], 2); ?></span>
                        </div>
                        <div>
                            <strong>Status:</strong>
                            <span class="status-badge pending">Pending: <?php echo $summary['pending']; ?></span>
                            <span class="status-badge partially_paid">Partial: <?php echo $summary['partial']; ?></span>
                            <span class="status-badge paid">Paid: <?php echo $summary['paid']; ?></span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No bills found</h3>
                    <p>You haven't generated any bills yet.</p>
                    <a href="generate_bill.php" style="display: inline-block; margin-top: 15px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px;">
                        Generate Your First Bill
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>