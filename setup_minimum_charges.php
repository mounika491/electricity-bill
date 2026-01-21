<?php
include "config.php";

echo "<h2>Setting Up Minimum Charges</h2>";

// Create minimum_charges table
$sql = "CREATE TABLE IF NOT EXISTS minimum_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(20),
    min_charge DECIMAL(10,2),
    effective_from DATE
)";

if (mysqli_query($conn, $sql)) {
    echo "✅ Minimum charges table created<br>";
} else {
    echo "❌ Error: " . mysqli_error($conn) . "<br>";
}

// Insert default minimum charges
$charges = [
    ['household', 50.00, '2020-01-01'],
    ['commercial', 100.00, '2020-01-01'],
    ['industrial', 200.00, '2020-01-01']
];

foreach ($charges as $charge) {
    list($category, $amount, $date) = $charge;
    
    $check_sql = "SELECT id FROM minimum_charges WHERE category = '$category'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) == 0) {
        $insert_sql = "INSERT INTO minimum_charges (category, min_charge, effective_from) 
                       VALUES ('$category', '$amount', '$date')";
        
        if (mysqli_query($conn, $insert_sql)) {
            echo "✅ Minimum charge for $category: ₹$amount<br>";
        } else {
            echo "❌ Error inserting $category: " . mysqli_error($conn) . "<br>";
        }
    } else {
        echo "✅ Minimum charge for $category already exists<br>";
    }
}

echo "<hr>";
echo "<h3>✅ Minimum Charges Setup Complete!</h3>";
echo "<p>Now even with zero consumption, customers will be billed minimum charges:</p>";
echo "<ul>";
echo "<li>Household: ₹50.00</li>";
echo "<li>Commercial: ₹100.00</li>";
echo "<li>Industrial: ₹200.00</li>";
echo "</ul>";
echo "<a href='generate_bill.php' style='display:inline-block; padding:10px 20px; background:#3498db; color:white; text-decoration:none; border-radius:5px;'>Test Bill Generation</a>";
?>