<?php
include "config.php";

// Check if user is worker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'worker') {
    header("Location: login.php");
    exit;
}

// Handle reading submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
    $number = $_POST['number'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $reading = $_POST['reading'];
    
    // Check if customer exists
    $check = mysqli_query($conn, "SELECT number FROM customer WHERE number='$number'");
    if (mysqli_num_rows($check) == 0) {
        $error = "Customer not found!";
    } else {
        // Insert reading
        $sql = "INSERT INTO readings (number, month, year, reading, read_date) 
                VALUES ('$number', '$month', '$year', '$reading', CURDATE())";
        
        if (mysqli_query($conn, $sql)) {
            $success = "Reading saved successfully!";
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Worker Dashboard</title>
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
            background: #27ae60;
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
            background: #219653;
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
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .message {
            padding: 10px;
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
            background: #27ae60;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #219653;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">👷 Worker Panel</div>
        <div class="nav">
            <span>Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="worker.php">Dashboard</a>
            <a href="generate_bill.php">Generate Bills</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <h2>Worker Dashboard</h2>
        
        <?php if(isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <h3>📝 Add Meter Reading</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Meter Number *</label>
                    <input type="number" name="number" required min="1000" max="99999">
                </div>
                
                <div class="form-group">
                    <label>Month *</label>
                    <select name="month" required>
                        <option value="">Select Month</option>
                        <?php for($i=1; $i<=12; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Year *</label>
                    <input type="number" name="year" value="<?php echo date('Y'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Current Reading (kWh) *</label>
                    <input type="number" step="0.01" name="reading" min="0" required>
                </div>
                
                <button type="submit" name="submit" class="btn">Submit Reading</button>
            </form>
        </div>
        
        <div class="card">
            <h3>📊 Recent Readings</h3>
            <?php
            $sql = "SELECT r.*, c.name FROM readings r 
                    JOIN customer c ON r.number = c.number 
                    ORDER BY r.read_date DESC LIMIT 15";
            $result = mysqli_query($conn, $sql);
            
            if (mysqli_num_rows($result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Meter No.</th>
                            <th>Customer</th>
                            <th>Month/Year</th>
                            <th>Reading</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo $row['read_date']; ?></td>
                            <td><?php echo $row['number']; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo $row['month'] . '/' . $row['year']; ?></td>
                            <td><?php echo $row['reading']; ?> kWh</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No readings found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>