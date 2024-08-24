<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection settings
$host = 'localhost:3307';
$dbname = 'test';
$username = 'root';
$password = '';

// Create a new MySQLi instance for database connection
$mysqli = new mysqli($host, $username, $password, $dbname, 3307);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Function to get the next receipt number
function getNextReceiptNumber($mysqli) {
    $datePrefix = date('Ym');

    $stmt = $mysqli->prepare("SELECT receipt_number FROM receipt_numbers WHERE receipt_number LIKE ? ORDER BY receipt_number DESC LIMIT 1");
    $likePattern = $datePrefix . '%';
    $stmt->bind_param('s', $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();

    $latestReceipt = $result->fetch_assoc();
    $stmt->close();

    if ($latestReceipt) {
        $latestNumber = intval(substr($latestReceipt['receipt_number'], 6));
        $nextNumber = $latestNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $datePrefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $totalAmount = $_POST['total-amount'];

    // Extract sections data
    $sections = [];
    foreach ($_POST as $key => $value) {
        if (preg_match('/source-(\d+)/', $key, $matches)) {
            $sectionIndex = $matches[1];
            $sections[$sectionIndex]['source'] = $_POST['source-' . $sectionIndex];
            $sections[$sectionIndex]['description'] = $_POST['description-' . $sectionIndex];
            $sections[$sectionIndex]['mode_of_payment'] = $_POST['mode-of-payment-' . $sectionIndex];
            $sections[$sectionIndex]['bank_accounts'] = $_POST['bank-accounts-' . $sectionIndex];
            $sections[$sectionIndex]['amount'] = $_POST['amount-' . $sectionIndex];
        }
    }

    $receiptNumber = getNextReceiptNumber($mysqli);

    $mysqli->begin_transaction();

    try {
        $stmt = $mysqli->prepare("INSERT INTO receipt_numbers (receipt_number) VALUES (?)");
        $stmt->bind_param('s', $receiptNumber);
        $stmt->execute();
        $stmt->close();

        foreach ($sections as $section) {
            $stmt = $mysqli->prepare("INSERT INTO invoices3 (receipt_number, date, source, description, mode_of_payment, bank_accounts, amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssd', $receiptNumber, $date, $section['source'], $section['description'], $section['mode_of_payment'], $section['bank_accounts'], $section['amount']);
            $stmt->execute();
            $stmt->close();
        }

        $mysqli->commit();

        // Redirect to the same page with the receipt number in the query string
        header("Location: " . $_SERVER['PHP_SELF'] . "?receipt_number=" . urlencode($receiptNumber));
        exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        die("Could not save invoice: " . $e->getMessage());
    }
}

// Retrieve invoice details for display
$invoice = null;
$totalAmount = 0; // Initialize total amount

if (isset($_GET['receipt_number'])) {
    $receiptNumber = $_GET['receipt_number'];

    $stmt = $mysqli->prepare("SELECT * FROM invoices3 WHERE receipt_number = ?");
    $stmt->bind_param('s', $receiptNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    $invoice = $result->fetch_all(MYSQLI_ASSOC); // Fetch all rows
    $stmt->close();

    if (!$invoice) {
        die("Invoice not found.");
    }

    // Calculate the total amount
    foreach ($invoice as $section) {
        $totalAmount += floatval($section['amount']);
    }
}

$mysqli->close();

$logoPath = "./head.png"; // Path to your logo image
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Form For Income</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: linear-gradient(135deg, #f4f4f9, #e0e0e0);
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }
        h1, .invoice-header h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
            font-size: 28px; /* Increased font size */
            font-weight: 600;
        }
        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: bold;
            font-size: 20px; /* Increased font size */
        }
        input[type="text"],
        input[type="date"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 14px;
            margin-bottom: 15px;
            border: 1px solid black;
            border-radius: 6px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
            font-size: 18px; /* Increased font size */
            box-sizing: border-box;
        }
        textarea {
            height: 140px; /* Increased height */
        }
        button {
            background-color: #28a745;
            color: white;
            padding: 14px 22px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 18px; /* Increased font size */
            font-weight: bold;
            transition: background-color 0.3s, transform 0.2s;
        }
        button:hover {
            background-color: #218838;
        }
        button:active {
            background-color: #1e7e34;
            transform: translateY(1px);
        }
        input[readonly] {
            background-color: #f0f0f0;
            cursor: not-allowed;
        }
        .form-section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f9f9f9;
        }
        .form-section h3 {
            margin-top: 0;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .invoice-header img {
            max-width: 800px; /* Increased size */
            height: auto;
            margin-bottom: 20px;
            padding-right: 40px;
        }
        .invoice-header p {
            margin: 5px 0;
            font-size: 18px; /* Increased font size */
            color: black;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 16px; /* Increased font size */
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 14px; /* Increased padding */
            text-align:center;
        }
        th {
            font-size: 18px;

            background-color: #f2f2f2;
            font-weight: bold;
        }
        td{
            font-size: 18px;
            color: black;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .total-row {
            font-weight: bold;
            text-align: right;
            background-color: #f2f2f2;
        }
        @media print {
            .button {
                display: none;
            }
        }
        #amount{
            font-size: 20px;
            font-size: bold;
        }
    </style>
</head>
    <div class="container">
        <?php if (!isset($_GET['receipt_number'])): ?>
        <!-- Display Invoice Form -->
        <h1>Invoice For Income</h1>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" id="invoice-form">
            <div id="form-container">
                <!-- Dynamic sections will be added here -->
                <!-- Example static section (remove if you have dynamic sections) -->
                <div class="form-section">
                    <label for="date">Date:</label>
                    <input type="date" id="date" name="date" required>

                    <label for="source-1">Source:</label>
                    <input type="text" id="source-1" name="source-1" required>
                    
                    <label for="description-1">Description:</label>
                    <textarea id="description-1" name="description-1" required></textarea>
                    
                    <label for="mode-of-payment-1">Mode of Payment:</label>
                    <select id="mode-of-payment-1" name="mode-of-payment-1" required>
                        <option value="cash">Cash</option>
                        <option value="credit">Credit Card</option>
                        <option value="bank-transfer">Bank Transfer</option>
                    </select>
                    
                    <label for="bank-accounts-1">Bank Accounts:</label>
                    <input type="text" id="bank-accounts-1" name="bank-accounts-1">
                    
                    <label for="amount-1">Amount:</label>
                    <input type="number" id="amount-1" name="amount-1" step="0.01" required>
                </div>
            </div>
            <button type="submit">Submit Invoice</button>
        </form>
        <?php else: ?>
        <!-- Display Invoice Details -->
        <div class="invoice-header">
            <img src="<?php echo $logoPath; ?>" alt="Logo">
            <hr>
            <h2>Invoice</h2>
            <p>Receipt Number: <?php echo htmlspecialchars($invoice[0]['receipt_number']); ?></p>
            <p>Date: <?php echo htmlspecialchars($invoice[0]['date']); ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Description</th>
                    <th>Mode of Payment</th>
                    <th>Bank Accounts</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoice as $section): ?>
                <tr>
                    <td><?php echo htmlspecialchars($section['source']); ?></td>
                    <td><?php echo htmlspecialchars($section['description']); ?></td>
                    <td><?php echo htmlspecialchars($section['mode_of_payment']); ?></td>
                    <td><?php echo htmlspecialchars($section['bank_accounts']); ?></td>
                    <td id="amount"><?php echo htmlspecialchars(number_format($section['amount'], 2)); ?></td>
                </tr>
                <?php endforeach; ?>
               
            </tbody>
        </table>
        <br>
        <button id="print" onclick="window.print()">Print Invoice</button>
        <?php endif; ?>
    </div>
</body>
</html>
