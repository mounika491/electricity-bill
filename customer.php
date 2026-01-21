<?php
session_start();
include "config.php";

// Check if user is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit;
}

$customer_number = $_SESSION['number'];

// Get customer details
$customer_sql = "SELECT * FROM customer WHERE number = '$customer_number'";
$customer_result = mysqli_query($conn, $customer_sql);

if (mysqli_num_rows($customer_result) == 0) {
    die("Customer not found!");
}

$customer = mysqli_fetch_assoc($customer_result);

// Get all bills for this customer
$bills_sql = "SELECT * FROM bill WHERE number = '$customer_number' ORDER BY year DESC, month DESC";
$bills_result = mysqli_query($conn, $bills_sql);

// Calculate customer payment summary
$summary_sql = "SELECT 
    COUNT(*) as total_bills,
    SUM(total) as total_billed,
    SUM(paid_amount) as total_paid,
    SUM(remaining_due) as total_due,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'partially_paid' THEN 1 END) as partial_count,
    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
    FROM bill 
    WHERE number = '$customer_number'";
    
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Get recent payments
$payments_sql = "SELECT p.*, b.month, b.year 
                 FROM payments p 
                 JOIN bill b ON p.bill_id = b.id 
                 WHERE p.customer_number = '$customer_number' 
                 ORDER BY p.payment_date DESC 
                 LIMIT 5";
$payments_result = mysqli_query($conn, $payments_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customer Dashboard</title>
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
            background: #3498db;
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
            background: #2980b9;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        h2, h3, h4 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .profile-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .info-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 18px;
            color: #333;
            font-weight: bold;
        }
        
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        
        .household { background: #d4edda; color: #155724; }
        .commercial { background: #cce5ff; color: #004085; }
        .industrial { background: #f8d7da; color: #721c24; }
        
        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .summary-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .total-billed .summary-value { color: #2c3e50; }
        .total-paid .summary-value { color: #28a745; }
        .total-due .summary-value { color: #dc3545; }
        
        .bill-status {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .pending { background: #fff3cd; color: #856404; }
        .partially_paid { background: #cce5ff; color: #004085; }
        .paid { background: #d4edda; color: #155724; }
        .overdue { background: #f8d7da; color: #721c24; }
        
        /* Bills Table */
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
        
        .btn {
            display: inline-block;
            padding: 6px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-view {
            background: #17a2b8;
        }
        
        .btn-print {
            background: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        /* Payment History */
        .payment-history {
            margin-top: 30px;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .payment-date {
            color: #666;
            font-size: 14px;
        }
        
        .payment-amount {
            font-weight: bold;
            color: #28a745;
        }
        
        .no-bills {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-bills-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #ddd;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .month-year {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .due-date {
            color: #dc3545;
            font-weight: bold;
        }
        
        .paid-date {
            color: #28a745;
            font-weight: bold;
        }
    </style>
    <script>
        function filterBills(status) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const rowStatus = row.querySelector('.status-badge').textContent.toLowerCase().replace(' ', '_');
                if (status === 'all' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update active filter button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
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
        
        function printBill(billId) {
            window.open('print_bill.php?id=' + billId, '_blank');
        }
    </script>
</head>
<body>
    <div class="header">
        <div class="logo">👤 Customer Dashboard</div>
        <div class="nav">
            <span style="text-transform: uppercase; font-weight: bold;">Welcome, <?php echo htmlspecialchars(strtoupper($customer['name'])); ?></span>
            <a href="customer.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <!-- Customer Profile -->
        <div class="card">
            <h2>My Profile</h2>
            <div class="profile-info">
                <div class="info-item">
                    <div class="info-label">Meter Number</div>
                    <div class="info-value"><?php echo $customer['number']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Customer Name</div>
                    <div class="info-value" style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars(strtoupper($customer['name'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?php echo $customer['phone']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo $customer['email'] ? htmlspecialchars($customer['email']) : 'N/A'; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Category</div>
                    <div class="info-value">
                        <span class="badge <?php echo $customer['category']; ?>">
                            <?php echo ucfirst($customer['category']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Registration Date</div>
                    <div class="info-value"><?php echo $customer['reg_date']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($customer['address']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Payment Summary -->
        <div class="card">
            <h3>Payment Summary</h3>
            <div class="summary-cards">
                <div class="summary-card total-billed">
                    <div class="summary-label">Total Billed</div>
                    <div class="summary-value">₹<?php echo number_format($summary['total_billed'] ?? 0, 2); ?></div>
                </div>
                
                <div class="summary-card total-paid">
                    <div class="summary-label">Total Paid</div>
                    <div class="summary-value">₹<?php echo number_format($summary['total_paid'] ?? 0, 2); ?></div>
                </div>
                
                <div class="summary-card total-due">
                    <div class="summary-label">Total Due</div>
                    <div class="summary-value">₹<?php echo number_format($summary['total_due'] ?? 0, 2); ?></div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-label">Total Bills</div>
                    <div class="summary-value"><?php echo $summary['total_bills'] ?? 0; ?></div>
                </div>
            </div>
            
            <div class="bill-status">
                <span class="status-badge pending">Pending: <?php echo $summary['pending_count'] ?? 0; ?></span>
                <span class="status-badge partially_paid">Partial: <?php echo $summary['partial_count'] ?? 0; ?></span>
                <span class="status-badge paid">Paid: <?php echo $summary['paid_count'] ?? 0; ?></span>
            </div>
            
            <?php if(($summary['total_due'] ?? 0) > 0): ?>
            <div class="alert alert-warning">
                ⚠️ You have an outstanding due of <strong>₹<?php echo number_format($summary['total_due'], 2); ?></strong>. 
                Please pay at the earliest to avoid service disruption.
            </div>
            <?php endif; ?>
        </div>
        
        <!-- All Bills -->
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>My Electricity Bills</h3>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="search" placeholder="Search bills..." onkeyup="searchBills()" 
                           style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    
                    <div style="display: flex; gap: 5px;">
                        <button class="filter-btn active" onclick="filterBills('all')" style="padding: 8px 12px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">All</button>
                        <button class="filter-btn" onclick="filterBills('pending')" style="padding: 8px 12px; background: #e9ecef; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer;">Pending</button>
                        <button class="filter-btn" onclick="filterBills('paid')" style="padding: 8px 12px; background: #e9ecef; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer;">Paid</button>
                        <button class="filter-btn" onclick="filterBills('partially_paid')" style="padding: 8px 12px; background: #e9ecef; border: 1px solid #dee2e6; border-radius: 4px; cursor: pointer;">Partial</button>
                    </div>
                </div>
            </div>
            
            <?php if(mysqli_num_rows($bills_result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Month/Year</th>
                            <th>Units (kWh)</th>
                            <th>Bill Amount</th>
                            <th>Paid Amount</th>
                            <th>Due Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($bill = mysqli_fetch_assoc($bills_result)): 
                            $paid = $bill['paid_amount'] ?? 0;
                            $due = $bill['remaining_due'] ?? $bill['total'];
                            $status = strtolower($bill['status']);
                            $month_name = date('F', mktime(0,0,0,$bill['month'],1));
                        ?>
                        <tr>
                            <td>#<?php echo $bill['id']; ?></td>
                            <td class="month-year"><?php echo $month_name . ' ' . $bill['year']; ?></td>
                            <td><?php echo $bill['units']; ?> kWh</td>
                            <td><strong>₹<?php echo number_format($bill['total'], 2); ?></strong></td>
                            <td style="color: #28a745;">₹<?php echo number_format($paid, 2); ?></td>
                            <td style="color: #dc3545;">₹<?php echo number_format($due, 2); ?></td>
                            <td class="due-date"><?php echo $bill['due_date']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $status; ?>">
                                    <?php 
                                    $status_text = ucfirst(str_replace('_', ' ', $bill['status']));
                                    echo $status_text; 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="print_bill.php?id=<?php echo $bill['id']; ?>" class="btn btn-view" target="_blank">View Bill</a>
                                    <button onclick="printBill(<?php echo $bill['id']; ?>)" class="btn btn-print">Print</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Bill Statistics -->
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <strong>Showing <?php echo mysqli_num_rows($bills_result); ?> bills</strong> | 
                    Total Amount: ₹<?php echo number_format($summary['total_billed'] ?? 0, 2); ?> | 
                    Total Paid: ₹<?php echo number_format($summary['total_paid'] ?? 0, 2); ?> | 
                    Total Due: ₹<?php echo number_format($summary['total_due'] ?? 0, 2); ?>
                </div>
            <?php else: ?>
                <div class="no-bills">
                    <div class="no-bills-icon">📄</div>
                    <h3>No Bills Found</h3>
                    <p>You don't have any electricity bills yet.</p>
                    <p style="color: #999; margin-top: 10px;">Bills will appear here once they are generated by the admin.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Payments -->
        <div class="card">
            <h3>Recent Payments</h3>
            <?php if(mysqli_num_rows($payments_result) > 0): ?>
                <div class="payment-history">
                    <?php while($payment = mysqli_fetch_assoc($payments_result)): 
                        $payment_month = date('F', mktime(0,0,0,$payment['month'],1));
                    ?>
                    <div class="payment-item">
                        <div>
                            <strong><?php echo $payment_month . ' ' . $payment['year']; ?> Bill</strong>
                            <div class="payment-date">Paid on: <?php echo $payment['payment_date']; ?></div>
                            <small>Method: <?php echo ucfirst($payment['payment_method']); ?></small>
                        </div>
                        <div class="payment-amount">
                            ₹<?php echo number_format($payment['amount_paid'], 2); ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="payment_history.php" style="color: #3498db; text-decoration: none;">View All Payment History →</a>
                </div>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No payment history found.</p>
            <?php endif; ?>
        </div>
        
        <!-- Bill Information -->
        <div class="card">
            <h3>Understanding Your Bill</h3>
            <div style="color: #666; line-height: 1.8;">
                <p><strong>Bill Status Meanings:</strong></p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li><span class="status-badge pending" style="margin-right: 5px;">Pending</span> - Bill is unpaid</li>
                    <li><span class="status-badge partially_paid" style="margin-right: 5px;">Partially Paid</span> - Partial payment made</li>
                    <li><span class="status-badge paid" style="margin-right: 5px;">Paid</span> - Bill is fully paid</li>
                    <li><span class="status-badge overdue" style="margin-right: 5px;">Overdue</span> - Payment past due date</li>
                </ul>
                
                <p><strong>How to Pay Your Bill:</strong></p>
                <ol style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Visit our payment center with your bill ID</li>
                    <li>Use online payment options mentioned on the bill</li>
                    <li>Contact customer support for assistance</li>
                </ol>
                
                <div class="alert alert-info">
                    <strong>📞 Need Help?</strong> Contact our customer support: 1800-123-4567<br>
                    Email: support@electricitybilling.com<br>
                    Office Hours: 9 AM - 6 PM (Monday to Saturday)
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize search and filter
        document.addEventListener('DOMContentLoaded', function() {
            // Set first filter button as active
            document.querySelector('.filter-btn').classList.add('active');
        });
    </script>
</body>
</html>