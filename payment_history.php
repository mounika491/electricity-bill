<?php
session_start();
include "config.php";

// Check if user is customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit;
}

$customer_number = $_SESSION['number'];

// Get all payments for this customer
$sql = "SELECT p.*, b.month, b.year, b.total as bill_amount 
        FROM payments p 
        JOIN bill b ON p.bill_id = b.id 
        WHERE p.customer_number = '$customer_number' 
        ORDER BY p.payment_date DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment History</title>
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
            background: #2980b9;
        }
        
        .container {
            max-width: 1000px;
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
        
        h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
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
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .completed { background: #d4edda; color: #155724; }
        .cancelled { background: #f8d7da; color: #721c24; }
        .pending { background: #fff3cd; color: #856404; }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .no-payments {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .payment-amount {
            color: #28a745;
            font-weight: bold;
        }
        
        .bill-period {
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">💰 Payment History</div>
        <div class="nav">
            <a href="customer.php">← Back to Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>My Payment History</h2>
            
            <?php if(mysqli_num_rows($result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Bill Period</th>
                            <th>Payment Method</th>
                            <th>Transaction ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_paid = 0;
                        while($payment = mysqli_fetch_assoc($result)): 
                            $total_paid += $payment['amount_paid'];
                            $month_name = date('F', mktime(0,0,0,$payment['month'],1));
                        ?>
                        <tr>
                            <td><?php echo $payment['payment_date']; ?></td>
                            <td class="bill-period"><?php echo $month_name . ' ' . $payment['year']; ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                            <td><?php echo $payment['transaction_id'] ?: 'N/A'; ?></td>
                            <td class="payment-amount">₹<?php echo number_format($payment['amount_paid'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="print_bill.php?id=<?php echo $payment['bill_id']; ?>" class="btn" target="_blank">View Bill</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                
                <!-- Summary -->
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <strong>Total Payments Made:</strong> ₹<?php echo number_format($total_paid, 2); ?> | 
                    <strong>Total Transactions:</strong> <?php echo mysqli_num_rows($result); ?>
                </div>
            <?php else: ?>
                <div class="no-payments">
                    <h3>No Payment History</h3>
                    <p>You haven't made any payments yet.</p>
                    <a href="customer.php" class="btn" style="margin-top: 15px;">← Back to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>