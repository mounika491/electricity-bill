<?php
session_start();
include "config.php";

// Check if user is admin or worker
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'worker')) {
    header("Location: login.php");
    exit;
}

// Function to get minimum charge for category
function get_minimum_charge($category, $conn) {
    $sql = "SELECT min_charge FROM minimum_charges WHERE category = '$category' 
            ORDER BY effective_from DESC LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['min_charge'];
    }
    
    // Default minimum charges if not found in table
    switch($category) {
        case 'household': return 50.00;
        case 'commercial': return 100.00;
        case 'industrial': return 200.00;
        default: return 50.00;
    }
}

// Function to calculate bill based on category
function calculate_bill($category, $units, $conn) {
    // Minimum charge applies regardless of consumption
    $min_charge = get_minimum_charge($category, $conn);
    
    // Calculate based on units consumed
    $calculated_amount = 0;
    
    if ($category == 'household') {
        if ($units <= 50) {
            $calculated_amount = $units * 1.5;
        } elseif ($units <= 100) {
            $calculated_amount = (50*1.5) + (($units-50)*2);
        } else {
            $calculated_amount = (50*1.5) + (50*2) + (($units-100)*2.5);
        }
    }
    elseif ($category == 'commercial') {
        if ($units <= 100) {
            $calculated_amount = $units * 2;
        } elseif ($units <= 500) {
            $calculated_amount = (100*2) + (($units-100)*2.5);
        } else {
            $calculated_amount = (100*2) + (400*2.5) + (($units-500)*3);
        }
    }
    else { // industrial
        if ($units <= 1000) {
            $calculated_amount = $units * 2.5;
        } elseif ($units <= 5000) {
            $calculated_amount = (1000*2.5) + (($units-1000)*3);
        } else {
            $calculated_amount = (1000*2.5) + (4000*3) + (($units-5000)*3.5);
        }
    }
    
    // Return the higher of minimum charge or calculated amount
    return max($min_charge, $calculated_amount);
}

$bill_data = null;
$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $number = $_POST['number'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    // Validate inputs
    if (empty($number) || empty($month) || empty($year)) {
        $error = "Please fill all fields!";
    } else {
        // Check if customer exists
        $customer_sql = "SELECT * FROM customer WHERE number='$number'";
        $customer_result = mysqli_query($conn, $customer_sql);
        
        if (mysqli_num_rows($customer_result) == 0) {
            $error = "❌ Customer with meter number $number not found!";
        } else {
            $customer = mysqli_fetch_assoc($customer_result);
            $category = $customer['category'];
            
            // Get minimum charge for this category
            $minimum_charge = get_minimum_charge($category, $conn);
            
            // Get current reading
            $current_sql = "SELECT * FROM readings WHERE number='$number' AND month='$month' AND year='$year'";
            $current_result = mysqli_query($conn, $current_sql);
            
            if (mysqli_num_rows($current_result) == 0) {
                $error = "❌ No meter reading found for $month/$year! Please add reading first.";
            } else {
                $current_reading_data = mysqli_fetch_assoc($current_result);
                $current_reading = $current_reading_data['reading'];
                $reading_date = $current_reading_data['read_date'];
                
                // Validate current reading
                if ($current_reading < 0) {
                    $error = "❌ Invalid current reading! Reading cannot be negative.";
                } else {
                    // Get previous reading
                    $prev_month = ($month == 1) ? 12 : $month - 1;
                    $prev_year = ($month == 1) ? $year - 1 : $year;
                    
                    $prev_sql = "SELECT * FROM readings WHERE number='$number' AND month='$prev_month' AND year='$prev_year'";
                    $prev_result = mysqli_query($conn, $prev_sql);
                    
                    $prev_reading = 0;
                    $prev_reading_date = "N/A";
                    if (mysqli_num_rows($prev_result) > 0) {
                        $prev_reading_data = mysqli_fetch_assoc($prev_result);
                        $prev_reading = $prev_reading_data['reading'];
                        $prev_reading_date = $prev_reading_data['read_date'];
                        
                        // Validate: Current reading should be >= previous reading
                        if ($current_reading < $prev_reading) {
                            $error = "⚠️ WARNING: Current reading ($current_reading kWh) is LESS THAN previous reading ($prev_reading kWh)!<br><br>";
                            $error .= "Possible reasons:<br>";
                            $error .= "1. Meter reading error<br>";
                            $error .= "2. Meter was reset/replaced<br>";
                            $error .= "3. Wrong reading entry<br><br>";
                            $error .= "Please verify the readings. If meter was reset, use current reading as total consumption.";
                            
                            // Still show bill but with warning
                            $units = $current_reading; // Use current reading as total if meter reset
                            $is_meter_reset = true;
                        } else {
                            $units = $current_reading - $prev_reading;
                            $is_meter_reset = false;
                        }
                    } else {
                        // First reading - no previous reading
                        $units = $current_reading;
                        $prev_reading = 0;
                        $prev_reading_date = "First Reading";
                        $is_meter_reset = false;
                    }
                    
                    if (empty($error)) {
                        // Check if units are zero or very low
                        $is_zero_consumption = ($units == 0);
                        
                        // Calculate bill amount (minimum charge applies)
                        $amount = calculate_bill($category, $units, $conn);
                        
                        // Get previous unpaid bills
                        $due_sql = "SELECT * FROM bill WHERE number='$number' AND status IN ('pending', 'partially_paid')";
                        $due_result = mysqli_query($conn, $due_sql);
                        
                        $prev_due = 0;
                        $fine = 0;
                        $previous_bills = [];
                        
                        while($due_row = mysqli_fetch_assoc($due_result)) {
                            $prev_due += $due_row['remaining_due'] ?? $due_row['total'];
                            $previous_bills[] = $due_row;
                        }
                        
                        // Calculate fine (5% of previous due)
                        $fine = $prev_due * 0.05;
                        
                        // Calculate GST (18% on amount + fine)
                        $gst = ($amount + $fine) * 0.18;
                        
                        // Total amount
                        $total = $amount + $gst + $prev_due + $fine;
                        
                        // Due date (15th of next month)
                        $next_month = ($month == 12) ? 1 : $month + 1;
                        $next_year = ($month == 12) ? $year + 1 : $year;
                        $due_date = date("$next_year-$next_month-15");
                        
                        // Service number (Bill number format: YYYYMM-customernumber)
                        $service_number = $year . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . $number;
                        
                        // Prepare bill data for display
                        $bill_data = [
                            'customer' => $customer,
                            'current_reading' => $current_reading,
                            'current_reading_date' => $reading_date,
                            'previous_reading' => $prev_reading,
                            'previous_reading_date' => $prev_reading_date,
                            'units' => $units,
                            'amount' => $amount,
                            'minimum_charge' => $minimum_charge,
                            'gst' => $gst,
                            'prev_due' => $prev_due,
                            'fine' => $fine,
                            'total' => $total,
                            'due_date' => $due_date,
                            'service_number' => $service_number,
                            'month' => $month,
                            'year' => $year,
                            'previous_bills' => $previous_bills,
                            'is_meter_reset' => $is_meter_reset,
                            'is_zero_consumption' => $is_zero_consumption
                        ];
                        
                        // Save to database if confirmed
                        if (isset($_POST['confirm'])) {
                            $insert_sql = "INSERT INTO bill (number, month, year, units, amount, gst, fine, prev_due, total, due_date, service_number, remaining_due) 
                                           VALUES ('$number', '$month', '$year', '$units', '$amount', '$gst', '$fine', '$prev_due', '$total', '$due_date', '$service_number', '$total')";
                            
                            if (mysqli_query($conn, $insert_sql)) {
                                $bill_id = mysqli_insert_id($conn);
                                echo "<script>
                                    alert('✅ Bill generated successfully! Bill ID: $bill_id');
                                    window.location.href = 'print_bill.php?id=$bill_id&print=1';
                                </script>";
                            } else {
                                $error = "❌ Error saving bill: " . mysqli_error($conn);
                            }
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generate Electricity Bill</title>
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
            max-width: 1000px;
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
        
        h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
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
        
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        /* Bill Preview Styles */
        .bill-preview {
            border: 2px solid #2c3e50;
            padding: 20px;
            margin: 20px 0;
            background: white;
        }
        
        .bill-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 20px;
        }
        
        .bill-header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .bill-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .customer-info, .reading-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px dotted #ddd;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .calculation-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
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
            font-weight: bold;
            background: #f8f9fa;
        }
        
        .due-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        
        .print-btn {
            background: #17a2b8;
        }
        
        .reading-warning {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        
        .minimum-charge-note {
            background: #e8f4fc;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #3498db;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🧾 Generate Bill</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <?php if($_SESSION['role'] == 'admin'): ?>
                <a href="admin.php">← Dashboard</a>
            <?php else: ?>
                <a href="worker.php">← Dashboard</a>
            <?php endif; ?>
            <a href="add_reading.php">Add Reading</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Generate Electricity Bill</h2>
            
            <?php if($error && !isset($bill_data)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php elseif(isset($bill_data) && $bill_data['is_meter_reset']): ?>
                <div class="warning">
                    <strong>⚠️ METER READING ALERT!</strong><br>
                    Current reading is less than previous reading.<br>
                    This bill assumes meter was reset. Please verify readings.
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Meter Number *</label>
                    <input type="number" name="number" min="1000" max="99999" required 
                           value="<?php echo isset($_POST['number']) ? $_POST['number'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>Month *</label>
                    <select name="month" required>
                        <option value="">Select Month</option>
                        <?php 
                        $months = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        foreach($months as $num => $name): 
                        ?>
                            <option value="<?php echo $num; ?>" 
                                <?php echo (isset($_POST['month']) && $_POST['month'] == $num) ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Year *</label>
                    <input type="number" name="year" value="<?php echo isset($_POST['year']) ? $_POST['year'] : date('Y'); ?>" min="2020" max="2030" required>
                </div>
                
                <button type="submit" class="btn">Calculate Bill</button>
                <button type="button" class="btn" onclick="window.location.href='add_reading.php'">Add New Reading</button>
            </form>
        </div>
        
        <?php if($bill_data): ?>
        <div class="card">
            <div class="bill-preview">
                <!-- Bill Header -->
                <div class="bill-header">
                    <h1>ELECTRICITY BILL</h1>
                    <p><strong>Service Number:</strong> <?php echo $bill_data['service_number']; ?></p>
                    <p><strong>Billing Period:</strong> <?php echo date('F Y', mktime(0,0,0,$bill_data['month'],1)); ?></p>
                    <?php if($bill_data['is_meter_reset']): ?>
                        <p style="color: #dc3545; background: #f8d7da; padding: 5px; border-radius: 3px;">
                            ⚠️ METER ASSUMED RESET - Please verify readings
                        </p>
                    <?php endif; ?>
                    
                    <?php if($bill_data['is_zero_consumption']): ?>
                        <div class="minimum-charge-note">
                            ⚡ <strong>Minimum Charge Applied:</strong> 
                            ₹<?php echo number_format($bill_data['minimum_charge'], 2); ?> for 
                            <?php echo ucfirst($bill_data['customer']['category']); ?> connection
                            (Even with zero consumption)
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Reading Alert -->
                <?php if($bill_data['is_meter_reset']): ?>
                <div class="reading-warning">
                    <strong>Reading Discrepancy Detected!</strong><br>
                    Current reading (<?php echo $bill_data['current_reading']; ?> kWh) is less than 
                    previous reading (<?php echo $bill_data['previous_reading']; ?> kWh).<br>
                    Bill calculated using current reading as total consumption.
                </div>
                <?php endif; ?>
                
                <!-- Customer and Reading Info -->
                <div class="bill-details">
                    <div class="customer-info">
                        <h3>Customer Information</h3>
                        <div class="info-row">
                            <span class="info-label">Meter Number:</span>
                            <span class="info-value"><?php echo $bill_data['customer']['number']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Customer Name:</span>
                            <span class="info-value" style="text-transform: uppercase; font-weight: bold;"><?php echo htmlspecialchars(strtoupper($bill_data['customer']['name'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo htmlspecialchars($bill_data['customer']['address']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo $bill_data['customer']['phone']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Category:</span>
                            <span class="info-value">
                                <?php echo ucfirst($bill_data['customer']['category']); ?>
                                <br><small style="color: #666;">Minimum charge: ₹<?php echo number_format($bill_data['minimum_charge'], 2); ?></small>
                            </span>
                        </div>
                    </div>
                    
                    <div class="reading-info">
                        <h3>Meter Readings</h3>
                        <div class="info-row">
                            <span class="info-label">Previous Reading:</span>
                            <span class="info-value"><?php echo number_format($bill_data['previous_reading'], 2); ?> kWh</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Previous Reading Date:</span>
                            <span class="info-value"><?php echo $bill_data['previous_reading_date']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Current Reading:</span>
                            <span class="info-value"><?php echo number_format($bill_data['current_reading'], 2); ?> kWh</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Current Reading Date:</span>
                            <span class="info-value"><?php echo $bill_data['current_reading_date']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Units Consumed:</span>
                            <span class="info-value">
                                <strong style="color: #2c3e50; font-size: 18px;">
                                    <?php echo number_format($bill_data['units'], 2); ?> kWh
                                </strong>
                                <?php if($bill_data['is_zero_consumption']): ?>
                                    <br><small style="color: #856404;">(Minimum charge applied)</small>
                                <?php endif; ?>
                                <?php if($bill_data['is_meter_reset']): ?>
                                    <br><small style="color: #dc3545;">(Meter reset assumed)</small>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Previous Due Warning -->
                <?php if($bill_data['prev_due'] > 0): ?>
                <div class="due-warning">
                    <h4>⚠️ Previous Unpaid Bills</h4>
                    <p>Total Previous Due: <strong>₹<?php echo number_format($bill_data['prev_due'], 2); ?></strong></p>
                    <p>Late Payment Fine (5%): <strong>₹<?php echo number_format($bill_data['fine'], 2); ?></strong></p>
                </div>
                <?php endif; ?>
                
                <!-- Bill Calculation -->
                <h3>Bill Calculation</h3>
                <table class="calculation-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Details</th>
                            <th>Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Energy Charges</td>
                            <td>
                                <?php if($bill_data['is_zero_consumption']): ?>
                                    Minimum charge for <?php echo $bill_data['customer']['category']; ?> connection
                                    <br><small>(<?php echo number_format($bill_data['units'], 2); ?> kWh consumed)</small>
                                <?php else: ?>
                                    <?php echo number_format($bill_data['units'], 2); ?> kWh 
                                    @ <?php echo $bill_data['customer']['category']; ?> rates
                                <?php endif; ?>
                            </td>
                            <td>
                                ₹<?php echo number_format($bill_data['amount'], 2); ?>
                                <?php if($bill_data['is_zero_consumption']): ?>
                                    <br><small>(Includes min. charge)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>GST</td>
                            <td>18% on energy charges</td>
                            <td>₹<?php echo number_format($bill_data['gst'], 2); ?></td>
                        </tr>
                        <?php if($bill_data['prev_due'] > 0): ?>
                        <tr style="background: #fff3cd;">
                            <td>Previous Due Amount</td>
                            <td>Carry forward from previous bills</td>
                            <td>₹<?php echo number_format($bill_data['prev_due'], 2); ?></td>
                        </tr>
                        <tr style="background: #f8d7da;">
                            <td>Late Payment Fine</td>
                            <td>5% on previous due</td>
                            <td>₹<?php echo number_format($bill_data['fine'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="total-row">
                            <td colspan="2"><strong>TOTAL AMOUNT PAYABLE</strong></td>
                            <td><strong>₹<?php echo number_format($bill_data['total'], 2); ?></strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Due Date -->
                <div style="text-align: center; margin: 30px 0; padding: 20px; background: #2c3e50; color: white; border-radius: 5px;">
                    <h3 style="color: white; margin-bottom: 10px;">Due Date: <?php echo date('d M Y', strtotime($bill_data['due_date'])); ?></h3>
                    <p>Please pay before due date to avoid additional charges</p>
                </div>
                
                <!-- Action Buttons -->
                <div style="text-align: center; margin-top: 30px;">
                    <form method="POST" action="">
                        <input type="hidden" name="number" value="<?php echo $_POST['number']; ?>">
                        <input type="hidden" name="month" value="<?php echo $_POST['month']; ?>">
                        <input type="hidden" name="year" value="<?php echo $_POST['year']; ?>">
                        <input type="hidden" name="confirm" value="1">
                        
                        <button type="submit" class="btn btn-success">✓ Generate & Save This Bill</button>
                        <button type="button" class="btn print-btn" onclick="window.print()">🖨️ Print Preview</button>
                        <button type="button" class="btn btn-warning" onclick="window.location.href='add_reading.php?number=<?php echo $_POST['number']; ?>'">
                            ✎ Correct Reading
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Help -->
        <div class="card">
            <h3>💡 Minimum Charges Information</h3>
            <div style="color: #666; line-height: 1.8;">
                <p><strong>Even with zero consumption, minimum charges apply:</strong></p>
                <ul style="margin-left: 20px; margin-bottom: 15px;">
                    <li><strong>Household:</strong> ₹50.00 minimum charge</li>
                    <li><strong>Commercial:</strong> ₹100.00 minimum charge</li>
                    <li><strong>Industrial:</strong> ₹200.00 minimum charge</li>
                </ul>
                <p>These charges cover connection maintenance and fixed costs.</p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-focus on meter number field
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="number"]').focus();
        });
    </script>
</body>
</html>