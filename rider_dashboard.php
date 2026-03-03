<?php
session_start();
include 'db.php';

/* ------------------- Rider login check (demo version) ------------------- */
if (!isset($_SESSION['rider'])) {
    $_SESSION['rider'] = true; // simple demo login
}

/* ------------------- Mark order as Delivered ------------------- */
if (isset($_GET['delivered'])) {
    $group = $_GET['delivered'];

    $stmt = $conn->prepare("
        UPDATE orders 
        SET status='Delivered'
        WHERE order_group=? AND status='On the way'
    ");
    $stmt->bind_param("s", $group);
    $stmt->execute();
    $stmt->close();

    header("Location: rider_dashboard.php");
    exit();
}

/* ------------------- Date Filter ------------------- */
$filter_date = $_GET['filter_date'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
<title>Rider Dashboard</title>
<link rel="stylesheet" href="rider.css">

<style>
body { font-family: Arial, sans-serif; padding: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 15px; }
th, td { border: 1px solid #000; padding: 8px; text-align: center; }
form { margin-bottom: 15px; }
button, a { padding: 5px 10px; text-decoration: none; }
a.button-link { background: #28a745; color: #fff; border-radius: 4px; }
</style>
</head>
<body>

<h1>Rider Dashboard</h1>

<h2>Orders Ready for Delivery</h2>

<!-- DATE FILTER -->
<form method="GET">
    <label>Filter by Date:</label>
    <input type="date" name="filter_date" value="<?php echo $filter_date; ?>">
    <button type="submit">Filter</button>
</form>

<table>
<tr>
    <th>Order Group</th>
    <th>Foods</th>
    <th>Total</th>
    <th>Contact Number</th>
    <th>Delivery Address</th>
    <th>Order Time</th>
    <th>Action</th>
</tr>

<?php
// Fetch orders that are ready for delivery (status = 'On the way')
$sql = "
SELECT 
    order_group,
    GROUP_CONCAT(CONCAT(food_item,' x',quantity) SEPARATOR ', ') AS foods,
    SUM(price) AS total,
    MAX(contact_number) AS contact_number,
    MAX(delivery_address) AS delivery_address,
    MAX(order_time) AS order_time
FROM orders
WHERE status='On the way'
";

if ($filter_date) {
    $sql .= " AND DATE(order_time) = '$filter_date' ";
}

$sql .= "
GROUP BY order_group
ORDER BY order_time ASC
";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "<tr>
            <td>{$row['order_group']}</td>
            <td>{$row['foods']}</td>
            <td>₱" . number_format($row['total'], 2) . "</td>
            <td>{$row['contact_number']}</td>
            <td>{$row['delivery_address']}</td>
            <td>{$row['order_time']}</td>
            <td>
                <a href='?delivered={$row['order_group']}'
                   class='button-link'
                   onclick=\"return confirm('Confirm delivery?')\">
                   Mark as Delivered
                </a>
            </td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='7'>No deliveries found.</td></tr>";
}
?>

</table>

</body>
</html>