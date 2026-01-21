
<?php
session_start();
include "config.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$bill_id = $_GET['id'] ?? 0;

// Get bill details
$sql = "SELECT b.*, c.* FROM bill b 
        JOIN customer c ON b.number = c.number 
        WHERE b.id = '$bill_id'";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    die("Bill not found!");
}

$bill = mysqli_fetch_assoc($result);

// Get readings
$current_sql = "SELECT * FROM readings WHERE number='{$bill['number']}' AND month='{$bill['month']}' AND year='{$bill['year']}'";
$current_result = mysqli_query($conn, $current_sql);
$current_reading = mysqli_fetch_assoc($current_result);

// Get previous reading
$prev_month = ($bill['month'] == 1) ? 12 : $bill['month'] - 1;
$prev_year = ($bill['month'] == 1) ? $bill['year'] - 1 : $bill['year'];

$prev_sql = "SELECT * FROM readings WHERE number='{$bill['number']}' AND month='$prev_month' AND year='$prev_year'";
$prev_result = mysqli_query($conn, $prev_sql);
$prev_reading = mysqli_num_rows($prev_result) > 0 ? mysqli_fetch_assoc($prev_result) : null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Print Bill #<?php echo $bill_id; ?></title>
    <style>
        @media print {
            body * {
                visibility: hidden;
            }
            .print-bill, .print-bill * {
                visibility: visible;
            }
            .print-bill {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .print-bill {
            width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .bill-header {
            text-align: center;
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .bill-header h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
        }
        
        .bill-header h3 {
            color: #666;
            margin: 5px 0;
        }
        
        .bill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .info-box {
            flex: 1;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            margin: 0 10px;
        }
        
        .info-box h4 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding-bottom: 5px;
            border-bottom: 1px dotted #ddd;
        }
        
        .label {
            font-weight: bold;
            color: #555;
        }
        
        .value {
            color: #333;
        }
        
        .readings-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .readings-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .readings-table th,
        .readings-table td {
            padding: 12px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .readings-table th {
            background: #2c3e50;
            color: white;
        }
        
        .calculation-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .calculation-table th,
        .calculation-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .calculation-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .total-row {
            background: #2c3e50;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .due-date {
            text-align: center;
            padding: 20px;
            background: #2c3e50;
            color: white;
            border-radius: 5px;
            margin: 30px 0;
        }
        
        .due-date h3 {
            margin: 0;
            font-size: 24px;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 10px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-print {
            background: #17a2b8;
        }
        
        .btn-download {
            background: #28a745;
        }
        
        .customer-name-uppercase {
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="print-bill">
        <!-- Bill Header -->
        <div class="bill-header">
            <h1>ELECTRICITY BILL</h1>
            <h3>Service Number: <?php echo $bill['service_number'] ?? $bill['year'] . str_pad($bill['month'], 2, '0', STR_PAD_LEFT) . '-' . $bill['number']; ?></h3>
            <h3>Bill ID: #<?php echo $bill_id; ?> | Date: <?php echo date('d/m/Y'); ?></h3>
        </div>
        
        <!-- Customer and Bill Info -->
        <div class="bill-info">
            <div class="info-box">
                <h4>Customer Information</h4>
                <div class="info-row">
                    <span class="label">Meter Number:</span>
                    <span class="value"><?php echo $bill['number']; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Name:</span>
                    <span class="value customer-name-uppercase"><?php echo htmlspecialchars(strtoupper($bill['name'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Address:</span>
                    <span class="value"><?php echo htmlspecialchars($bill['address']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Phone:</span>
                    <span class="value"><?php echo $bill['phone']; ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Category:</span>
                    <span class="value"><?php echo ucfirst($bill['category']); ?></span>
                </div>
            </div>
            
            <div class="info-box">
                <h4>Billing Information</h4>
                <div class="info-row">
                    <span class="label">Billing Period:</span>
                    <span class="value"><?php echo date('F Y', mktime(0,0,0,$bill['month'],1)); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Bill Date:</span>
                    <span class="value"><?php echo date('d/m/Y'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Due Date:</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime($bill['due_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Status:</span>
                    <span class="value" style="color: <?php echo $bill['status'] == 'paid' ? 'green' : 'red'; ?>">
                        <?php echo strtoupper($bill['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Meter Readings -->
        <div class="readings-section">
            <h3 style="text-align: center; color: #2c3e50;">Meter Readings</h3>
            <table class="readings-table">
                <thead>
                    <tr>
                        <th>Reading Type</th>
                        <th>Date</th>
                        <th>Reading (kWh)</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Previous Reading</td>
                        <td><?php echo $prev_reading ? $prev_reading['read_date'] : 'N/A'; ?></td>
                        <td><?php echo $prev_reading ? $prev_reading['reading'] : '0'; ?></td>
                        <td><?php echo $prev_reading ? '' : 'First Reading'; ?></td>
                    </tr>
                    <tr>
                        <td>Current Reading</td>
                        <td><?php echo $current_reading['read_date']; ?></td>
                        <td><?php echo $current_reading['reading']; ?></td>
                        <td>Billed</td>
                    </tr>
                    <tr style="background: #e8f4fc; font-weight: bold;">
                        <td colspan="2">Units Consumed</td>
                        <td colspan="2"><?php echo $bill['units']; ?> kWh</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Minimum Charge Note -->
        <?php if($bill['units'] == 0): ?>
        <div style="background: #e8f4fc; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #3498db;">
            <h4 style="margin-top: 0; color: #2c3e50;">⚡ Minimum Charge Applied</h4>
            <p>
                Even with zero consumption, a minimum charge of 
                <strong>₹<?php 
                    $min_charge = 0;
                    if($bill['category'] == 'household') $min_charge = 50;
                    elseif($bill['category'] == 'commercial') $min_charge = 100;
                    else $min_charge = 200;
                    echo number_format($min_charge, 2);
                ?></strong> 
                applies for <?php echo $bill['category']; ?> connections.
            </p>
        </div>
        <?php endif; ?>
        
        <!-- Previous Due Warning -->
        <?php if($bill['prev_due'] > 0): ?>
        <div class="warning-box">
            <h4 style="margin-top: 0; color: #856404;">⚠️ IMPORTANT NOTICE</h4>
            <p>You have a previous due amount of <strong>₹<?php echo number_format($bill['prev_due'], 2); ?></strong></p>
            <p>A late payment fine of 5% (<strong>₹<?php echo number_format($bill['fine'], 2); ?></strong>) has been added to your current bill.</p>
            <p>Please clear all dues to avoid service disconnection.</p>
        </div>
        <?php endif; ?>
        
        <!-- Bill Calculation -->
        <h3 style="color: #2c3e50; text-align: center;">Bill Calculation</h3>
        <table class="calculation-table">
            <thead>
                <tr>
                    <th width="40%">Description</th>
                    <th width="40%">Details</th>
                    <th width="20%">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Energy Charges</td>
                    <td><?php echo $bill['units']; ?> kWh consumed</td>
                    <td><?php echo number_format($bill['amount'], 2); ?></td>
                </tr>
                <tr>
                    <td>GST</td>
                    <td>18% on energy charges</td>
                    <td><?php echo number_format($bill['gst'], 2); ?></td>
                </tr>
                <?php if($bill['prev_due'] > 0): ?>
                <tr style="background: #fff3cd;">
                    <td>Previous Due Amount</td>
                    <td>Carry forward from previous bills</td>
                    <td><?php echo number_format($bill['prev_due'], 2); ?></td>
                </tr>
                <tr style="background: #f8d7da;">
                    <td>Late Payment Fine</td>
                    <td>5% penalty on previous due</td>
                    <td><?php echo number_format($bill['fine'], 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="2"><strong>TOTAL AMOUNT PAYABLE</strong></td>
                    <td><strong>₹<?php echo number_format($bill['total'], 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <!-- Due Date -->
        <div class="due-date">
            <h3>PAY BEFORE: <?php echo date('d M Y', strtotime($bill['due_date'])); ?></h3>
            <p>Payment after due date will attract additional charges</p>
        </div>
        
        <!-- Payment Instructions -->
        <div style="padding: 20px; background: #f8f9fa; border-radius: 5px; margin: 20px 0;">
            <h4 style="color: #2c3e50; margin-top: 0;">Payment Instructions:</h4>
            <p>1. Pay online at www.electricitybill.com</p>
            <p>2. Visit nearest payment center with this bill</p>
            <p>3. Use Bill ID: <strong>#<?php echo $bill_id; ?></strong> for reference</p>
            <p>4. For queries, call: 1800-123-4567</p>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; color: #666; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p>This is a computer generated bill. No signature required.</p>
            <p>© 2024 Electricity Board. All rights reserved.</p>
        </div>
    </div>
    
    <!-- Action Buttons (Hidden when printing) -->
    <div class="actions no-print">
        <button class="btn btn-print" onclick="window.print()">🖨️ Print Bill</button>
        <button class="btn" onclick="window.location.href='generate_bill.php'">← Back to Generate Bill</button>
        <?php if($_SESSION['role'] == 'admin'): ?>
            <button class="btn" onclick="window.location.href='admin.php'">← Dashboard</button>
        <?php else: ?>
            <button class="btn" onclick="window.location.href='worker.php'">← Dashboard</button>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-print option
        <?php if(isset($_GET['print']) && $_GET['print'] == '1'): ?>
        window.onload = function() {
            window.print();
        }
        <?php endif; ?>
    </script>
</body>
</html>