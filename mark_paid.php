<?php
session_start();
include "config.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$bill_id = $_GET['id'] ?? 0;

if ($bill_id > 0) {
    // Get bill details
    $bill_sql = "SELECT * FROM bill WHERE id = '$bill_id'";
    $bill_result = mysqli_query($conn, $bill_sql);
    
    if (mysqli_num_rows($bill_result) > 0) {
        $bill = mysqli_fetch_assoc($bill_result);
        $total_amount = $bill['total'];
        $already_paid = $bill['paid_amount'] ?? 0;
        $remaining_due = $bill['remaining_due'] ?? $total_amount;
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // If there's remaining due, record full payment
            if ($remaining_due > 0) {
                // Record full payment
                $payment_sql = "INSERT INTO payments (bill_id, customer_number, amount_paid, payment_date, 
                                payment_method, transaction_id, status) 
                                VALUES ('$bill_id', '{$bill['number']}', '$remaining_due', 
                                CURDATE(), 'cash', 'ADMIN_FULL_PAY', 'completed')";
                
                if (!mysqli_query($conn, $payment_sql)) {
                    throw new Exception("Failed to record payment: " . mysqli_error($conn));
                }
                
                $payment_id = mysqli_insert_id($conn);
                
                // Update bill as fully paid
                $update_sql = "UPDATE bill SET 
                              status = 'paid',
                              paid_amount = total,
                              remaining_due = 0,
                              last_payment_date = CURDATE()
                              WHERE id = '$bill_id'";
                
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Failed to update bill: " . mysqli_error($conn));
                }
                
                // Record in payment history
                $history_sql = "INSERT INTO payment_history (customer_number, bill_id, payment_id, 
                               previous_due, paid_amount, remaining_due, payment_date, notes) 
                               VALUES ('{$bill['number']}', '$bill_id', '$payment_id',
                               '$remaining_due', '$remaining_due', 0, 
                               CURDATE(), 'Full payment by admin')";
                
                if (!mysqli_query($conn, $history_sql)) {
                    throw new Exception("Failed to record history: " . mysqli_error($conn));
                }
            } else {
                // Bill is already paid or has no due
                $update_sql = "UPDATE bill SET status = 'paid' WHERE id = '$bill_id'";
                
                if (!mysqli_query($conn, $update_sql)) {
                    throw new Exception("Failed to update bill: " . mysqli_error($conn));
                }
            }
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Success message
            echo "<script>
                alert('✅ Bill #$bill_id marked as fully paid!');
                window.location.href = 'view_bills.php';
            </script>";
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<script>
                alert('❌ Error: " . addslashes($e->getMessage()) . "');
                window.location.href = 'view_bills.php';
            </script>";
            exit;
        }
    } else {
        echo "<script>
            alert('❌ Bill not found!');
            window.location.href = 'view_bills.php';
        </script>";
        exit;
    }
} else {
    header("Location: view_bills.php");
}
?>