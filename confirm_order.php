<?php
session_start();
include 'db.php';

// SECURITY: check if user logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ------------------ Update cart quantities ------------------
if (isset($_POST['update_cart'])) {
    if (!empty($_SESSION['cart'])) {
        foreach ($_POST['quantities'] as $food_id => $qty) {
            $food_id = intval($food_id);
            $qty = max(1, intval($qty));
            if (isset($_SESSION['cart'][$food_id])) {
                $_SESSION['cart'][$food_id]['quantity'] = $qty;
            }
        }
        $_SESSION['notify'] = "Cart updated successfully!";
    } else {
        $_SESSION['notify'] = "Cart is empty. Nothing to update.";
    }
}

// ------------------ Remove item ------------------
if (isset($_GET['remove'])) {
    $remove_id = intval($_GET['remove']);
    unset($_SESSION['cart'][$remove_id]);
}

// ------------------ Confirm order ------------------
if (isset($_POST['confirm_order'])) {
    if (empty($_SESSION['cart'])) {
        $_SESSION['notify'] = "You have no food in your cart!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    $payment = $_SESSION['confirm_payment'] ?? '';
    $contact = $_POST['gcash_number'] ?? '';
    $address = $_POST['delivery_address'] ?? '';

    $group = 'ORD_' . substr(md5(time() . rand()), 0, 12);
    $status = 'Pending';

    foreach ($_SESSION['cart'] as $item) {
        $total = $item['price'] * $item['quantity'];

        $stmt = $conn->prepare("
            INSERT INTO orders
            (user_id, food_item, quantity, price, status, order_group, payment_method, contact_number, delivery_address)
            VALUES (?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "isdssssss",
            $user_id,
            $item['name'],
            $item['quantity'],
            $total,
            $status,
            $group,
            $payment,
            $contact,
            $address
        );
        $stmt->execute();
        $stmt->close();

        // Update stock
        $stmt = $conn->prepare("
            UPDATE foods
            SET stock = stock - ?,
                available = CASE WHEN stock - ? <= 0 THEN 0 ELSE 1 END
            WHERE id = ?
        ");
        $stmt->bind_param("iii", $item['quantity'], $item['quantity'], $item['id']);
        $stmt->execute();
        $stmt->close();
    }

    unset($_SESSION['cart']);
    unset($_SESSION['confirm_payment']);
    unset($_SESSION['confirm_gcash']);
    unset($_SESSION['confirm_address']);

    $_SESSION['notify'] = "Order confirmed successfully!";
    header("Location: dashboard.php");
    exit();
}

// ------------------ Load foods for adding more ------------------
$foods = [];
$res = $conn->query("SELECT * FROM foods ORDER BY name");
if ($res) while ($row = $res->fetch_assoc()) $foods[] = $row;
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Confirm Order</title>
<style>
body { font-family: Arial; background:#f5f5f5; }
.container { max-width:800px; margin:30px auto; background:#fff; padding:20px; border-radius:6px; }
table { width:100%; border-collapse:collapse; margin-bottom:15px; }
th,td { border:1px solid #ccc; padding:8px; text-align:center; }
.total { font-weight:bold; }
button { padding:10px 20px; margin-top:15px; cursor:pointer; }
.back, .add-food { background:#aaa; color:#fff; text-decoration:none; padding:8px 15px; display:inline-block; margin-top:10px; margin-right:10px; }
.confirm { background:green; color:#fff; border:none; }
input[type="number"], input[type="text"] { width:100%; padding:5px; margin-top:5px; margin-bottom:10px; }
img { width:60px; height:50px; object-fit:cover; }
</style>
</head>
<body>

<div class="container">
<h2>Confirm Your Order</h2>

<?php
// Show notification if exists
if (isset($_SESSION['notify'])) {
    echo "<script>alert('" . addslashes($_SESSION['notify']) . "');</script>";
    unset($_SESSION['notify']);
}
?>

<form method="POST">
<table>
<tr>
<th>Image</th>
<th>Food</th>
<th>Qty</th>
<th>Price</th>
<th>Action</th>
</tr>

<?php
$grand = 0;
if(!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $food_id => $item):
        $subtotal = $item['price'] * $item['quantity'];
        $grand += $subtotal;
?>
<tr>
<td>
    <img src="<?php echo !empty($item['image']) ? htmlspecialchars($item['image']) : 'images/placeholder.png'; ?>">
</td>
<td><?php echo htmlspecialchars($item['name']); ?></td>
<td>
    <input type="number" name="quantities[<?php echo $food_id; ?>]" value="<?php echo $item['quantity']; ?>" min="1">
</td>
<td>₱<?php echo number_format($subtotal, 2); ?></td>
<td>
    <a href="?remove=<?php echo $food_id; ?>" onclick="return confirm('Remove this item?')">Remove</a>
</td>
</tr>
<?php 
    endforeach; 
} else {
    echo "<tr><td colspan='5'>Your cart is empty.</td></tr>";
}
?>

<tr class="total">
<td colspan="3">TOTAL</td>
<td>₱<?php echo number_format($grand, 2); ?></td>
<td></td>
</tr>
</table>

<button type="submit" name="update_cart">Update Cart</button>
</form>

<h3>Payment & Delivery</h3>
<form method="POST">
<label>Contact Number</label>
<input type="text" name="gcash_number" value="<?php echo htmlspecialchars($_SESSION['confirm_gcash'] ?? ''); ?>" required>

<label>Delivery Address</label>
<input type="text" name="delivery_address" value="<?php echo htmlspecialchars($_SESSION['confirm_address'] ?? ''); ?>" required>

<button type="submit" name="confirm_order" class="confirm">✅ Confirm Order</button>
<a href="dashboard.php" class="back add-food">➕ Add More Food</a>
</form>

</div>
</body>
</html>