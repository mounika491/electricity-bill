<?php
session_start();
include "config.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$bill_id = $_GET['id'] ?? 0;
$bill = null;
$customer_name = "Customer";
$payment_history = [];

// Get bill details
if ($bill_id > 0) {
    $sql = "SELECT b.*, c.name, c.number as customer_number FROM bill b 
            LEFT JOIN customer c ON b.number = c.number 
            WHERE b.id = '$bill_id'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $bill = mysqli_fetch_assoc($result);
        $customer_number = $bill['number'];
        $customer_name = $bill['name'] ?? "Customer #" . $customer_number;
        
        // Get payment history for this bill
        $history_sql = "SELECT * FROM payments WHERE bill_id = '$bill_id' ORDER BY payment_date DESC";
        $history_result = mysqli_query($conn, $history_sql);
        
        while($payment = mysqli_fetch_assoc($history_result)) {
            $payment_history[] = $payment;
        }
    } else {
        $error = "Bill not found!";
    }
}

// Handle payment
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['make_payment'])) {
    $bill_id = $_POST['bill_id'];
    $payment_amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $transaction_id = $_POST['transaction_id'];
    $payment_date = $_POST['payment_date'];
    $notes = $_POST['notes'];
    
    // Get current bill details
    $bill_sql = "SELECT * FROM bill WHERE id = '$bill_id'";
    $bill_result = mysqli_query($conn, $bill_sql);
    $current_bill = mysqli_fetch_assoc($bill_result);
    
    $total_amount = $current_bill['total'];
    $already_paid = $current_bill['paid_amount'] ?? 0;
    $remaining_due = $current_bill['remaining_due'] ?? $total_amount;
    
    // Validate payment amount
    if ($payment_amount <= 0) {
        $error = "Payment amount must be greater than 0!";
    } elseif ($payment_amount > $remaining_due) {
        $error = "Payment amount cannot exceed remaining due amount!";
    } else {
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Calculate new amounts
            $new_paid_amount = $already_paid + $payment_amount;
            $new_remaining_due = $remaining_due - $payment_amount;
            
            // Determine new status
            if ($new_remaining_due == 0) {
                $new_status = 'paid';
            } elseif ($new_paid_amount > 0) {
                $new_status = 'partially_paid';
            } else {
                $new_status = 'pending';
            }
            
            // 1. Insert payment record
            $payment_sql = "INSERT INTO payments (bill_id, customer_number, amount_paid, payment_date, 
                            payment_method, transaction_id, status) 
                            VALUES ('$bill_id', '{$current_bill['number']}', '$payment_amount', 
                            '$payment_date', '$payment_method', '$transaction_id', 'completed')";
            
            if (!mysqli_query($conn, $payment_sql)) {
                throw new Exception("Failed to record payment: " . mysqli_error($conn));
            }
            
            $payment_id = mysqli_insert_id($conn);
            
            // 2. Update bill with new amounts
            $update_bill_sql = "UPDATE bill SET 
                               paid_amount = '$new_paid_amount',
                               remaining_due = '$new_remaining_due',
                               status = '$new_status',
                               last_payment_date = '$payment_date'
                               WHERE id = '$bill_id'";
            
            if (!mysqli_query($conn, $update_bill_sql)) {
                throw new Exception("Failed to update bill: " . mysqli_error($conn));
            }
            
            // 3. Record in payment history
            $history_sql = "INSERT INTO payment_history (customer_number, bill_id, payment_id, 
                           previous_due, paid_amount, remaining_due, payment_date, notes) 
                           VALUES ('{$current_bill['number']}', '$bill_id', '$payment_id',
                           '$remaining_due', '$payment_amount', '$new_remaining_due', 
                           '$payment_date', '$notes')";
            
            if (!mysqli_query($conn, $history_sql)) {
                throw new Exception("Failed to record history: " . mysqli_error($conn));
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            $success = "✅ Payment of ₹" . number_format($payment_amount, 2) . " recorded successfully!";
            
            // Refresh bill data
            $bill_result = mysqli_query($conn, $bill_sql);
            $bill = mysqli_fetch_assoc($bill_result);
            
            // Get customer name again
            $customer_sql = "SELECT name FROM customer WHERE number = '{$current_bill['number']}'";
            $customer_result = mysqli_query($conn, $customer_sql);
            if ($customer_row = mysqli_fetch_assoc($customer_result)) {
                $customer_name = $customer_row['name'];
            }
            
            // Refresh payment history
            $payment_history = [];
            $history_sql = "SELECT * FROM payments WHERE bill_id = '$bill_id' ORDER BY payment_date DESC";
            $history_result = mysqli_query($conn, $history_sql);
            while($payment = mysqli_fetch_assoc($history_result)) {
                $payment_history[] = $payment;
            }
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "❌ Payment failed: " . $e->getMessage();
        }
    }
}

// Handle refund/cancel payment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_payment'])) {
    $payment_id = $_POST['payment_id'];
    
    // Get payment details
    $payment_sql = "SELECT * FROM payments WHERE id = '$payment_id'";
    $payment_result = mysqli_query($conn, $payment_sql);
    $payment_data = mysqli_fetch_assoc($payment_result);
    
    if ($payment_data) {
        $bill_id = $payment_data['bill_id'];
        $amount_to_refund = $payment_data['amount_paid'];
        
        // Get current bill
        $bill_sql = "SELECT * FROM bill WHERE id = '$bill_id'";
        $bill_result = mysqli_query($conn, $bill_sql);
        $current_bill = mysqli_fetch_assoc($bill_result);
        
        // Calculate new amounts
        $new_paid_amount = $current_bill['paid_amount'] - $amount_to_refund;
        $new_remaining_due = $current_bill['remaining_due'] + $amount_to_refund;
        
        // Determine new status
        if ($new_remaining_due == $current_bill['total']) {
            $new_status = 'pending';
        } elseif ($new_paid_amount > 0) {
            $new_status = 'partially_paid';
        } else {
            $new_status = 'pending';
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Mark payment as cancelled
            $cancel_sql = "UPDATE payments SET status = 'cancelled' WHERE id = '$payment_id'";
            if (!mysqli_query($conn, $cancel_sql)) {
                throw new Exception("Failed to cancel payment: " . mysqli_error($conn));
            }
            
            // 2. Update bill amounts
            $update_bill_sql = "UPDATE bill SET 
                               paid_amount = '$new_paid_amount',
                               remaining_due = '$new_remaining_due',
                               status = '$new_status'
                               WHERE id = '$bill_id'";
            
            if (!mysqli_query($conn, $update_bill_sql)) {
                throw new Exception("Failed to update bill: " . mysqli_error($conn));
            }
            
            // 3. Record refund in history
            $refund_notes = "Refund for payment ID: $payment_id";
            $history_sql = "INSERT INTO payment_history (customer_number, bill_id, payment_id, 
                           previous_due, paid_amount, remaining_due, payment_date, notes) 
                           VALUES ('{$current_bill['number']}', '$bill_id', '$payment_id',
                           '{$current_bill['remaining_due']}', '-$amount_to_refund', '$new_remaining_due', 
                           CURDATE(), '$refund_notes')";
            
            if (!mysqli_query($conn, $history_sql)) {
                throw new Exception("Failed to record refund: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            
            $success = "✅ Payment cancelled and ₹" . number_format($amount_to_refund, 2) . " refunded!";
            
            // Refresh data
            $bill_result = mysqli_query($conn, $bill_sql);
            $bill = mysqli_fetch_assoc($bill_result);
            
            // Get customer name
            $customer_sql = "SELECT name FROM customer WHERE number = '{$current_bill['number']}'";
            $customer_result = mysqli_query($conn, $customer_sql);
            if ($customer_row = mysqli_fetch_assoc($customer_result)) {
                $customer_name = $customer_row['name'];
            }
            
            // Refresh payment history
            $payment_history = [];
            $history_sql = "SELECT * FROM payments WHERE bill_id = '$bill_id' ORDER BY payment_date DESC";
            $history_result = mysqli_query($conn, $history_sql);
            while($payment = mysqli_fetch_assoc($history_result)) {
                $payment_history[] = $payment;
            }
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "❌ Refund failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Make Payment</title>
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
            max-width: 1200px;
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
        
        .bill-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .summary-box {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .total-amount {
            background: #f8f9fa;
            border: 2px solid #2c3e50;
        }
        
        .paid-amount {
            background: #d4edda;
            border: 2px solid #28a745;
        }
        
        .due-amount {
            background: #f8d7da;
            border: 2px solid #dc3545;
        }
        
        .summary-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: bold;
        }
        
        .total-amount .summary-value {
            color: #2c3e50;
        }
        
        .paid-amount .summary-value {
            color: #28a745;
        }
        
        .due-amount .summary-value {
            color: #dc3545;
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
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn:hover {
            opacity: 0.9;
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
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .pending { background: #fff3cd; color: #856404; }
        .partially_paid { background: #cce5ff; color: #004085; }
        .paid { background: #d4edda; color: #155724; }
        .overdue { background: #f8d7da; color: #721c24; }
        .cancelled { background: #6c757d; color: white; }
        .completed { background: #28a745; color: white; }
        
        .payment-actions {
            display: flex;
            gap: 10px;
        }
        
        .amount-slider {
            width: 100%;
            margin: 20px 0;
        }
        
        .slider-container {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .quick-amounts {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }
        
        .quick-btn {
            flex: 1;
            padding: 10px;
            background: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
        }
        
        .quick-btn:hover {
            background: #dee2e6;
        }
        
        .quick-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .customer-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
    <script>
        function updateAmount(value) {
            document.getElementById('amount').value = value;
            document.getElementById('amount-display').innerText = '₹' + parseFloat(value).toFixed(2);
            
            // Update quick buttons
            document.querySelectorAll('.quick-btn').forEach(btn => {
                btn.classList.remove('active');
                if(parseFloat(btn.dataset.amount) === parseFloat(value)) {
                    btn.classList.add('active');
                }
            });
        }
        
        function setQuickAmount(amount) {
            updateAmount(amount);
        }
        
        function setFullAmount() {
            var dueAmount = parseFloat(document.getElementById('due-amount').value);
            updateAmount(dueAmount);
        }
        
        function setHalfAmount() {
            var dueAmount = parseFloat(document.getElementById('due-amount').value);
            updateAmount(dueAmount / 2);
        }
        
        function validatePayment() {
            var amount = parseFloat(document.getElementById('amount').value);
            var dueAmount = parseFloat(document.getElementById('due-amount').value);
            
            if (amount <= 0) {
                alert('Payment amount must be greater than 0!');
                return false;
            }
            
            if (amount > dueAmount) {
                alert('Payment amount cannot exceed remaining due amount!');
                return false;
            }
            
            return true;
        }
    </script>
</head>
<body>
    <div class="header">
        <div class="logo">💳 Make Payment</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="view_bills.php">← Back to Bills</a>
            <a href="admin.php">Dashboard</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if($bill): ?>
        <!-- Customer Info -->
        <div class="customer-info">
            <h3>Payment for Bill #<?php echo $bill_id; ?></h3>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($customer_name); ?></p>
            <p><strong>Meter Number:</strong> <?php echo $bill['number']; ?></p>
            <p><strong>Billing Period:</strong> <?php echo date('F Y', mktime(0,0,0,$bill['month'],1)); ?></p>
        </div>
        
        <!-- Bill Summary -->
        <div class="card">
            <h2>Payment Summary</h2>
            
            <div class="bill-summary">
                <div class="summary-box total-amount">
                    <div class="summary-label">Total Bill Amount</div>
                    <div class="summary-value">₹<?php echo number_format($bill['total'], 2); ?></div>
                </div>
                
                <div class="summary-box paid-amount">
                    <div class="summary-label">Already Paid</div>
                    <div class="summary-value">₹<?php echo number_format($bill['paid_amount'] ?? 0, 2); ?></div>
                </div>
                
                <div class="summary-box due-amount">
                    <div class="summary-label">Remaining Due</div>
                    <div class="summary-value">₹<?php echo number_format($bill['remaining_due'] ?? $bill['total'], 2); ?></div>
                    <input type="hidden" id="due-amount" value="<?php echo $bill['remaining_due'] ?? $bill['total']; ?>">
                </div>
                
                <div class="summary-box">
                    <div class="summary-label">Current Status</div>
                    <div style="margin-top: 10px;">
                        <span class="status-badge <?php echo $bill['status']; ?>">
                            <?php 
                            $status_text = ucfirst(str_replace('_', ' ', $bill['status']));
                            echo $status_text; 
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Form -->
        <div class="card">
            <h3>Make Payment</h3>
            
            <form method="POST" action="" onsubmit="return validatePayment()">
                <input type="hidden" name="bill_id" value="<?php echo $bill_id; ?>">
                
                <div class="form-group">
                    <label>Payment Amount *</label>
                    <div class="slider-container">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span>₹0</span>
                            <span id="amount-display">₹0.00</span>
                            <span>₹<?php echo number_format($bill['remaining_due'] ?? $bill['total'], 2); ?></span>
                        </div>
                        <input type="range" id="amount-slider" class="amount-slider" min="0" 
                               max="<?php echo $bill['remaining_due'] ?? $bill['total']; ?>" 
                               step="0.01" value="0" oninput="updateAmount(this.value)">
                        <input type="hidden" name="amount" id="amount" value="0" required>
                    </div>
                    
                    <div class="quick-amounts">
                        <div class="quick-btn" onclick="setQuickAmount(100)" data-amount="100">₹100</div>
                        <div class="quick-btn" onclick="setQuickAmount(500)" data-amount="500">₹500</div>
                        <div class="quick-btn" onclick="setQuickAmount(1000)" data-amount="1000">₹1,000</div>
                        <div class="quick-btn" onclick="setHalfAmount()">50%</div>
                        <div class="quick-btn" onclick="setFullAmount()">Full Amount</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="net_banking">Net Banking</option>
                        <option value="upi">UPI</option>
                        <option value="cheque">Cheque</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Transaction ID / Reference Number</label>
                    <input type="text" name="transaction_id" placeholder="Enter transaction reference">
                </div>
                
                <div class="form-group">
                    <label>Payment Date *</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" rows="3" placeholder="Add any notes about this payment"></textarea>
                </div>
                
                <button type="submit" name="make_payment" class="btn btn-success">✓ Record Payment</button>
                <a href="view_bills.php" class="btn">Cancel</a>
            </form>
        </div>
        
        <!-- Payment History -->
        <div class="card">
            <h3>Payment History</h3>
            
            <?php if(!empty($payment_history)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payment_history as $payment): ?>
                        <tr>
                            <td><?php echo $payment['payment_date']; ?></td>
                            <td>₹<?php echo number_format($payment['amount_paid'], 2); ?></td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                            <td><?php echo $payment['transaction_id'] ?: 'N/A'; ?></td>
                            <td>
                                <span class="status-badge <?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                            <td class="payment-actions">
                                <?php if($payment['status'] == 'completed'): ?>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                        <button type="submit" name="cancel_payment" class="btn btn-danger" 
                                                onclick="return confirm('Cancel this payment? This will refund the amount.')">
                                            Cancel
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No payment history found.</p>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <div class="card">
            <div class="message error">Bill not found or invalid bill ID.</div>
            <a href="view_bills.php" class="btn">← Back to Bills</a>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Initialize amount display
        document.addEventListener('DOMContentLoaded', function() {
            var dueAmount = parseFloat(document.getElementById('due-amount')?.value || 0);
            updateAmount(0);
            
            // Set max value for slider
            var slider = document.getElementById('amount-slider');
            if (slider) {
                slider.max = dueAmount;
            }
        });
    </script>
</body>
</html>