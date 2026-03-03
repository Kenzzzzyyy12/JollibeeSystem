<?php
session_start();
include 'db.php';

// ----- Admin Login Check -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin123') {
        $_SESSION['admin'] = true;
        header("Location: admin_dashboard.php");
        exit();
    } else {
        die("Invalid admin login");
    }
}

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.html");
    exit();
}

// ----- Add Food -----
if (isset($_POST['add_food'])) {
    $name = $_POST['food_name'];
    $price = $_POST['food_price'];
    $stock = intval($_POST['food_stock']);
    $available = $stock > 0 ? 1 : 0;

    if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] === 0) {
        $uploadDir = __DIR__ . "/images/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = time() . "_" . basename($_FILES['food_image']['name']);
        $targetFile = $uploadDir . $filename;
        $dbPath = "images/" . $filename;

        if (move_uploaded_file($_FILES['food_image']['tmp_name'], $targetFile)) {
            $stmt = $conn->prepare("INSERT INTO foods (name, price, image, stock, available) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sdsii", $name, $price, $dbPath, $stock, $available);
            $stmt->execute();
            $stmt->close();
            $msg = "Food added successfully!";
        } else {
            $msg = "Failed to upload image.";
        }
    }
}

// ----- Update Stock -----
if (isset($_POST['update_stock'])) {
    $food_id = intval($_POST['food_id']);
    $new_stock = intval($_POST['food_stock']);
    $available = $new_stock > 0 ? 1 : 0;

    $stmt = $conn->prepare("UPDATE foods SET stock=?, available=? WHERE id=?");
    $stmt->bind_param("iii", $new_stock, $available, $food_id);
    $stmt->execute();
    $stmt->close();
    $msg = "Stock updated successfully!";
}

// ----- Delete Food -----
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM foods WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

// ----- Approve Order -----
if (isset($_GET['approve_group'])) {
    $group = $_GET['approve_group'];
    $stmt = $conn->prepare("UPDATE orders SET status='Approved' WHERE order_group=? AND status='Pending'");
    $stmt->bind_param("s", $group);
    $stmt->execute();
    $stmt->close();

    $date = $_GET['order_date'] ?? '';
    $redirect = $date ? "?view_orders=1&order_date=$date" : "?view_orders=1";
    header("Location: admin_dashboard.php$redirect");
    exit();
}

// ----- Mark Ready to Deliver -----
if (isset($_GET['deliver_group'])) {
    $group = $_GET['deliver_group'];
    $stmt = $conn->prepare("UPDATE orders SET status='On the way' WHERE order_group=? AND status='Approved'");
    $stmt->bind_param("s", $group);
    $stmt->execute();
    $stmt->close();

    $date = $_GET['order_date'] ?? '';
    $redirect = $date ? "?view_orders=1&order_date=$date" : "?view_orders=1";
    header("Location: admin_dashboard.php$redirect");
    exit();
}

// ----- Mark Delivered -----
if (isset($_GET['mark_delivered'])) {
    $group = $_GET['mark_delivered'];
    $stmt = $conn->prepare("UPDATE orders SET status='Delivered' WHERE order_group=? AND status='On the way'");
    $stmt->bind_param("s", $group);
    $stmt->execute();
    $stmt->close();

    $date = $_GET['order_date'] ?? '';
    $redirect = $date ? "?view_orders=1&order_date=$date" : "?view_orders=1";
    header("Location: admin_dashboard.php$redirect");
    exit();
}

// ----- Fetch Foods -----
$foods = [];
$result = $conn->query("SELECT * FROM foods ORDER BY name");
if ($result) while ($row = $result->fetch_assoc()) $foods[] = $row;

// ----- Daily Sales Report -----
$report_date = $_GET['report_date'] ?? '';
$report = [];
$total_sales = 0;
if ($report_date) {
    $stmt = $conn->prepare("
        SELECT o.order_group, u.username,
               GROUP_CONCAT(CONCAT(o.food_item,' x',o.quantity) SEPARATOR ', ') AS items,
               SUM(o.price) AS total
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE DATE(o.order_time)=?
        GROUP BY o.order_group, u.username
    ");
    $stmt->bind_param("s", $report_date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $report[] = $row;
        $total_sales += $row['total'];
    }
    $stmt->close();
}

// ----- Send Report -----
if (isset($_POST['send_report'])) {
    $send_date = $_POST['report_date_send'];
    $send_total = floatval($_POST['total_sales_send']);
    if ($send_date && $send_total >= 0) {
        $stmt = $conn->prepare("INSERT INTO sales_reports (report_date, total_sales) VALUES (?, ?)");
        $stmt->bind_param("sd", $send_date, $send_total);
        $stmt->execute();
        $stmt->close();
        $msg = "Report sent to manager successfully!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<h1>Admin Dashboard</h1>
<?php if (isset($msg)) echo "<p><strong>$msg</strong></p>"; ?>

<!-- ADD FOOD -->
<h2>Add New Food</h2>
<form method="POST" enctype="multipart/form-data">
    <input type="text" name="food_name" placeholder="Food Name" required><br>
    <input type="number" step="0.01" name="food_price" placeholder="Price" required><br>
    <input type="number" name="food_stock" placeholder="Stock Quantity" min="0" required><br>
    <input type="file" name="food_image" required><br>
    <button type="submit" name="add_food">Add Food</button>
</form>

<!-- EXISTING FOODS -->
<h2>Existing Foods</h2>
<table>
<tr>
<th>Name</th>
<th>Price</th>
<th>Image</th>
<th>Stock</th>
<th>Availability</th>
<th>Action</th>
<th>Update Stock</th>
</tr>
<?php foreach ($foods as $f): ?>
<tr>
<td><?php echo $f['name']; ?></td>
<td>₱<?php echo $f['price']; ?></td>
<td><img src="<?php echo $f['image']; ?>"></td>
<td><?php echo $f['stock']; ?></td>
<td><?php echo $f['available'] ? "<span class='in-stock'>Available</span>" : "<span class='out-stock'>Out of Stock</span>"; ?></td>
<td><a href="?delete=<?php echo $f['id']; ?>" onclick="return confirm('Delete this food?')" class="button-link">Delete</a></td>
<td>
<form method="POST" style="display:inline-block;">
    <input type="number" name="food_stock" value="<?php echo $f['stock']; ?>" min="0">
    <input type="hidden" name="food_id" value="<?php echo $f['id']; ?>">
    <button type="submit" name="update_stock">Update</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>

<!-- VIEW ORDERS -->
<h2>Manage Orders</h2>
<form method="GET">
    <label>Select Date:</label>
    <input type="date" name="order_date" value="<?php echo $_GET['order_date'] ?? ''; ?>">
    <button type="submit">View Orders</button>
</form>

<?php
if (isset($_GET['order_date']) && $_GET['order_date'] != '') {
    $order_date = $_GET['order_date'];

    // Fetch orders grouped by order_group with proper status
    $stmt = $conn->prepare("
        SELECT o.order_group, u.username, 
               GROUP_CONCAT(CONCAT(o.food_item, ' x', o.quantity) SEPARATOR ', ') AS foods,
               SUM(o.price) AS total_price,
               MAX(CASE 
                   WHEN o.status='Pending' THEN 1
                   WHEN o.status='Approved' THEN 2
                   WHEN o.status='On the way' THEN 3
                   WHEN o.status='Delivered' THEN 4
               END) AS status_order,
               o.payment_method,
               o.contact_number,
               o.delivery_address,
               MAX(o.order_time) AS order_time
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE DATE(o.order_time) = ?
        GROUP BY o.order_group, u.username, o.payment_method, o.contact_number, o.delivery_address
        ORDER BY order_time DESC
    ");
    $stmt->bind_param("s", $order_date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        echo "<h3>Orders for: $order_date</h3>";
        echo "<table>
        <tr>
            <th>User</th>
            <th>Foods Ordered</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Contact</th>
            <th>Address</th>
            <th>Time</th>
            <th>Status</th>
            <th>Action</th>
        </tr>";

        while ($row = $result->fetch_assoc()) {
            // Convert status_order back to text
            switch ($row['status_order']) {
                case 1: $status_text='Pending'; break;
                case 2: $status_text='Approved'; break;
                case 3: $status_text='On the way'; break;
                case 4: $status_text='Delivered'; break;
                default: $status_text='Unknown';
            }
            $status_class = str_replace(' ', '', $status_text);

            echo "<tr>
                <td>{$row['username']}</td>
                <td>{$row['foods']}</td>
                <td>₱{$row['total_price']}</td>
                <td>{$row['payment_method']}</td>
                <td>{$row['contact_number']}</td>
                <td>{$row['delivery_address']}</td>
                <td>{$row['order_time']}</td>
                <td><span class='status-badge $status_class'>$status_text</span></td>
                <td>";

            if ($status_text === 'Pending') {
                echo "<a href='?approve_group={$row['order_group']}&order_date=$order_date' class='button-link' onclick=\"return confirm('Approve this order?')\">Approve</a>";
            } elseif ($status_text === 'Approved') {
                echo "<a href='?deliver_group={$row['order_group']}&order_date=$order_date' class='button-link' onclick=\"return confirm('Mark as Ready to Deliver?')\">Ready to Deliver</a>";
            } elseif ($status_text === 'On the way') {
                echo "<a href='?mark_delivered={$row['order_group']}&order_date=$order_date' class='button-link' onclick=\"return confirm('Mark as Delivered?')\">Mark as Delivered</a>";
            }
            echo "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No orders for this day.</p>";
    }
    $stmt->close();
}
?>

<!-- DAILY SALES REPORT -->
<h2>Daily Sales Report</h2>
<form method="GET">
    <label>Select Date:</label>
    <input type="date" name="report_date" value="<?php echo $report_date; ?>" required>
    <button type="submit">Generate Report</button>
</form>

<?php if ($report_date && !empty($report)): ?>
<h3>Report for: <?php echo $report_date; ?></h3>
<table>
<tr>
<th>Order Group</th>
<th>User</th>
<th>Items</th>
<th>Total</th>
</tr>
<?php foreach ($report as $r): ?>
<tr>
<td><?php echo $r['order_group']; ?></td>
<td><?php echo $r['username']; ?></td>
<td><?php echo $r['items']; ?></td>
<td>₱<?php echo $r['total']; ?></td>
</tr>
<?php endforeach; ?>
<tr>
<th colspan="3">TOTAL SALES</th>
<th>₱<?php echo $total_sales; ?></th>
</tr>
</table>

<form method="POST">
    <input type="hidden" name="report_date_send" value="<?php echo $report_date; ?>">
    <input type="hidden" name="total_sales_send" value="<?php echo $total_sales; ?>">
    <button type="submit" name="send_report">Send Report to Manager</button>
</form>
<?php endif; ?>

<br>
<a href="logout.php" class="logout-btn">Logout Admin</a>
</body>
</html>