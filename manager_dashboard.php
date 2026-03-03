<?php
session_start();
include 'db.php';

/* ---------- Manager Login ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    if ($_POST['username'] === 'manager' && $_POST['password'] === 'manager123') {
        $_SESSION['manager'] = true;
        header("Location: manager_dashboard.php");
        exit();
    } else {
        die("Invalid manager login");
    }
}

if (!isset($_SESSION['manager'])) {
    header("Location: manager_login.html");
    exit();
}

/* ---------- Update Stock ---------- */
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

/* ---------- Fetch Foods ---------- */
$foods = [];
$res = $conn->query("SELECT * FROM foods ORDER BY name");
while ($row = $res->fetch_assoc()) $foods[] = $row;

/* ---------- DAILY REPORT ---------- */
$filter_date = $_GET['filter_date'] ?? '';
$reports = [];
$total_overall = 0;

if ($filter_date) {
    $stmt = $conn->prepare("SELECT * FROM sales_reports WHERE report_date=?");
    $stmt->bind_param("s", $filter_date);
} else {
    $stmt = $conn->prepare("SELECT * FROM sales_reports");
}
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $reports[] = $row;
    $total_overall += $row['total_sales'];
}
$stmt->close();

/* ---------- MONTHLY REPORT ---------- */
$month = $_GET['month'] ?? '';
$year  = $_GET['year'] ?? '';
$monthly_reports = [];
$monthly_total = 0;

if ($month && $year) {
    $stmt = $conn->prepare("
        SELECT report_date, total_sales
        FROM sales_reports
        WHERE MONTH(report_date)=? AND YEAR(report_date)=?
        ORDER BY report_date ASC
    ");
    $stmt->bind_param("ii", $month, $year);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $monthly_reports[] = $row;
        $monthly_total += $row['total_sales'];
    }
    $stmt->close();
}

/* ---------- EXPORT MONTHLY CSV ---------- */
if (isset($_GET['export_month'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="monthly_sales_report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Total Sales']);

    foreach ($monthly_reports as $m) {
        fputcsv($out, [$m['report_date'], $m['total_sales']]);
    }
    fputcsv($out, ['MONTH TOTAL', $monthly_total]);
    fclose($out);
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Manager Dashboard</title>
<link rel="stylesheet" href="manager.css">
<style>
table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
th, td { border:1px solid #000; padding:8px; text-align:center; }
.out-stock { color:red; font-weight:bold; }
.in-stock { color:green; }
</style>
</head>
<body>

<h1>Manager Dashboard</h1>
<?php if(isset($msg)) echo "<p><b>$msg</b></p>"; ?>

<!-- INVENTORY -->
<h2>Inventory</h2>
<table>
<tr>
<th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th>Update</th>
</tr>
<?php foreach($foods as $f): ?>
<tr>
<td><?= $f['name'] ?></td>
<td>₱<?= number_format($f['price'],2) ?></td>
<td><?= $f['stock'] ?></td>
<td><?= $f['available'] ? "<span class='in-stock'>Available</span>" : "<span class='out-stock'>Out</span>" ?></td>
<td>
<form method="POST">
<input type="hidden" name="food_id" value="<?= $f['id'] ?>">
<input type="number" name="food_stock" value="<?= $f['stock'] ?>" min="0">
<button name="update_stock">Update</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>

<!-- DAILY REPORT -->
<h2>Daily Sales</h2>
<form method="GET">
<input type="date" name="filter_date" value="<?= $filter_date ?>">
<button>Filter</button>
</form>

<?php if($reports): ?>
<table>
<tr><th>Date</th><th>Total</th></tr>
<?php foreach($reports as $r): ?>
<tr>
<td><?= $r['report_date'] ?></td>
<td>₱<?= number_format($r['total_sales'],2) ?></td>
</tr>
<?php endforeach; ?>
<tr>
<th>TOTAL</th>
<th>₱<?= number_format($total_overall,2) ?></th>
</tr>
</table>
<?php endif; ?>

<!-- MONTHLY REPORT -->
<h2>Monthly Sales Report</h2>
<form method="GET">
<label>Month:</label>
<select name="month" required>
<?php for($m=1;$m<=12;$m++): ?>
<option value="<?= $m ?>" <?= ($month==$m?'selected':'') ?>>
<?= date("F", mktime(0,0,0,$m,1)) ?>
</option>
<?php endfor; ?>
</select>

<label>Year:</label>
<input type="number" name="year" value="<?= $year ?: date('Y') ?>" required>

<button>Generate</button>
<?php if($month && $year): ?>
<button name="export_month" value="1">Export CSV</button>
<?php endif; ?>
</form>

<?php if($monthly_reports): ?>
<table>
<tr><th>Date</th><th>Total Sales</th></tr>
<?php foreach($monthly_reports as $m): ?>
<tr>
<td><?= $m['report_date'] ?></td>
<td>₱<?= number_format($m['total_sales'],2) ?></td>
</tr>
<?php endforeach; ?>
<tr>
<th>MONTH TOTAL</th>
<th>₱<?= number_format($monthly_total,2) ?></th>
</tr>
</table>
<?php endif; ?>

<a href="logout_manager.php"><button>Logout</button></a>

</body>
</html>