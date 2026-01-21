<?php
session_start();
include "config.php";

// Check if user is admin or worker
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'worker')) {
    header("Location: login.php");
    exit;
}

$success = "";
$error = "";
$meter_number = $_GET['number'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $number = $_POST['number'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $reading = $_POST['reading'];
    
    // Check if reading already exists
    $check_sql = "SELECT * FROM readings WHERE number='$number' AND month='$month' AND year='$year'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Update existing reading
        $sql = "UPDATE readings SET reading='$reading', read_date=CURDATE() 
                WHERE number='$number' AND month='$month' AND year='$year'";
        $action = "updated";
    } else {
        // Insert new reading
        $sql = "INSERT INTO readings (number, month, year, reading, read_date) 
                VALUES ('$number', '$month', '$year', '$reading', CURDATE())";
        $action = "added";
    }
    
    if (mysqli_query($conn, $sql)) {
        $success = "✅ Reading $action successfully!";
    } else {
        $error = "❌ Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add/Update Meter Reading</title>
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
        
        h2, h3 {
            color: #2c3e50;
            margin-bottom: 20px;
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
        
        input, select {
            width: 100%;
            padding: 12px;
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
        
        .btn:hover {
            opacity: 0.9;
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
        
        .reading-history {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .history-table th,
        .history-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .history-table th {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">📝 Add/Update Reading</div>
        <div class="nav">
            <span style="color: #95a5a6;">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="generate_bill.php">← Back to Bill</a>
            <?php if($_SESSION['role'] == 'admin'): ?>
                <a href="admin.php">Dashboard</a>
            <?php else: ?>
                <a href="worker.php">Dashboard</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Add/Update Meter Reading</h2>
            
            <?php if($success): ?>
                <div class="message success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Meter Number *</label>
                    <input type="number" name="number" value="<?php echo htmlspecialchars($meter_number); ?>" min="1000" max="99999" required>
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
                            <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Year *</label>
                    <input type="number" name="year" value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                </div>
                
                <div class="form-group">
                    <label>Current Reading (kWh) *</label>
                    <input type="number" step="0.01" name="reading" min="0" required placeholder="Enter current meter reading">
                    <small style="color: #666;">Note: Reading should be higher than previous month's reading</small>
                </div>
                
                <button type="submit" class="btn btn-success">Save Reading</button>
                <button type="button" class="btn" onclick="window.history.back()">Cancel</button>
            </form>
            
            <?php if($meter_number): ?>
            <div class="reading-history">
                <h3>Recent Readings for Meter <?php echo $meter_number; ?></h3>
                <?php
                $history_sql = "SELECT * FROM readings WHERE number='$meter_number' ORDER BY year DESC, month DESC LIMIT 5";
                $history_result = mysqli_query($conn, $history_sql);
                
                if (mysqli_num_rows($history_result) > 0): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Month/Year</th>
                                <th>Reading (kWh)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($history_result)): 
                                $month_name = date('F', mktime(0,0,0,$row['month'],1));
                            ?>
                            <tr>
                                <td><?php echo $month_name . ' ' . $row['year']; ?></td>
                                <td><?php echo number_format($row['reading'], 2); ?></td>
                                <td><?php echo $row['read_date']; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666;">No previous readings found.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>