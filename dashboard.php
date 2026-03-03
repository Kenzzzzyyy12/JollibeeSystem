<?php
session_start();
include 'db.php';

// ------------------- Check logged-in user -------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// ------------------- Fetch foods -------------------
$foods = [];
$result = $conn->query("SELECT * FROM foods ORDER BY name");
if ($result) while ($row = $result->fetch_assoc()) $foods[] = $row;

// ================= ADD TO CART =================
if (isset($_POST['add_to_cart'])) {
    $food_id = intval($_POST['food_id']);
    $quantity = max(1, intval($_POST['quantity']));

    $stmt = $conn->prepare("SELECT * FROM foods WHERE id=? AND available=1 AND stock >= ?");
    $stmt->bind_param("ii", $food_id, $quantity);
    $stmt->execute();
    $food = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($food) {
        if (isset($_SESSION['cart'][$food_id])) $_SESSION['cart'][$food_id]['quantity'] += $quantity;
        else $_SESSION['cart'][$food_id] = [
            'name' => $food['name'],
            'price' => $food['price'],
            'quantity' => $quantity
        ];
        $msg = "Added to cart!";
    } else $msg = "Food not available or insufficient stock!";
}

// ================= REMOVE FROM CART =================
if (isset($_GET['remove'])) unset($_SESSION['cart'][intval($_GET['remove'])]);

// ================= REDIRECT TO CONFIRM ORDER =================
if (isset($_POST['checkout']) && !empty($_SESSION['cart'])) {
    $payment = $_POST['payment_method'] ?? '';

    if ($payment === 'GCash') {
        $contact = $_POST['gcash_number'] ?? '';
        if (!preg_match('/^09\d{9}$/', $contact)) {
            $msg = "Invalid GCash number!";
        } else {
            $_SESSION['confirm_cart'] = $_SESSION['cart'];
            $_SESSION['confirm_payment'] = $payment;
            $_SESSION['confirm_contact'] = $contact;
            header("Location: confirm_order.php");
            exit();
        }
    } elseif ($payment === 'Cash on Delivery') {
        $_SESSION['confirm_cart'] = $_SESSION['cart'];
        $_SESSION['confirm_payment'] = $payment;
        $_SESSION['confirm_contact'] = '';
        header("Location: confirm_order.php");
        exit();
    }
}

// ================= FETCH USER ORDERS (GROUPED) =================
$orders = [];
$stmt = $conn->prepare("
    SELECT o.order_group, o.status, o.payment_method, o.contact_number, o.delivery_address, 
           MAX(o.order_time) AS order_time,
           GROUP_CONCAT(CONCAT(o.food_item, ' x', o.quantity) SEPARATOR ', ') AS foods,
           SUM(o.price) AS total_price
    FROM orders o
    WHERE o.user_id = ?
    GROUP BY o.order_group, o.status, o.payment_method, o.contact_number, o.delivery_address
    ORDER BY order_time DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res) while ($row = $res->fetch_assoc()) $orders[] = $row;
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>User Dashboard</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="user.css">

<style>
/* Basic styles for table/status badges */
.status-badge {
    padding: 3px 6px;
    border-radius: 5px;
    color: #fff;
    font-weight: bold;
}
.status-pending { background: orange; }
.status-approved { background: blue; }
.status-ontheway { background: purple; }
.status-delivered { background: green; }
.order-group { margin-bottom: 15px; border: 1px solid #ccc; padding: 10px; }
.order-group ul { list-style: none; padding-left: 0; }
.order-group li { margin-bottom: 5px; }
.view-btn a { text-decoration: none; color: #007BFF; }
</style>
</head>
<body>

<div class="container">

<header>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
    <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</header>

<?php if (isset($msg)) echo "<div class='message'>$msg</div>"; ?>

<div class="main-layout">

    <!-- ===== MAIN CONTENT: MENU ===== -->
    <div class="main-content">
        <h2><i class="fa-solid fa-utensils"></i> Menu</h2>
        <div class="menu-container">
            <?php foreach ($foods as $f): ?>
            <div class="food-card">
                <?php if (!empty($f['image'])) { ?>
                    <img src="<?php echo $f['image']; ?>" alt="<?php echo htmlspecialchars($f['name']); ?>">
                <?php } ?>
                <h3><?php echo htmlspecialchars($f['name']); ?></h3>
                <p class="price">₱<?php echo $f['price']; ?></p>

                <?php
                if ($f['stock'] <= 0) {
                    echo "<p class='out'>Out of Stock</p>";
                } elseif (!$f['available']) {
                    echo "<p class='out'>Not Available</p>";
                } else {
                ?>
                    <form method="POST">
                        <input type="hidden" name="food_id" value="<?php echo $f['id']; ?>">
                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $f['stock']; ?>">
                        <button type="submit" name="add_to_cart"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>
                    </form>
                    <small>Stock: <?php echo $f['stock']; ?></small>
                <?php } ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">

        <!-- CART -->
        <h2><i class="fa-solid fa-cart-shopping"></i> Cart</h2>
        <div class="cart-box">
        <?php if (!empty($_SESSION['cart'])): ?>
            <ul>
            <?php 
            $total = 0;
            foreach ($_SESSION['cart'] as $id => $i):
                $subtotal = $i['price'] * $i['quantity'];
                $total += $subtotal;
            ?>
                <li>
                    <?php echo $i['name'] . " x" . $i['quantity']; ?> - ₱<?php echo $subtotal; ?>
                    <a href="?remove=<?php echo $id; ?>" class="remove-btn"><i class="fa-solid fa-trash"></i></a>
                </li>
            <?php endforeach; ?>
            </ul>
            <div class="total">Total: ₱<?php echo $total; ?></div>

            <form method="POST" class="checkout-form">
                <label><input type="radio" name="payment_method" value="GCash" onclick="toggleG()"> GCash</label>
                <label><input type="radio" name="payment_method" value="Cash on Delivery" onclick="toggleG()"> Cash on Delivery</label>
                <div id="gcash" style="display:none;">
                    <input type="text" name="gcash_number" placeholder="09XXXXXXXXX">
                </div>
                <button type="submit" name="checkout"><i class="fa-solid fa-credit-card"></i> Checkout</button>
            </form>
        <?php else: ?>
            <p>Cart is empty.</p>
        <?php endif; ?>
        </div>

        <!-- YOUR ORDERS -->
        <h2>Your Orders</h2>
        <div id="orders-section">
        <?php foreach ($orders as $o): 
            $statusClass = 'status-' . strtolower(str_replace(' ', '', $o['status']));
        ?>
            <div class='order-group'>
                <h4>Order ID: <?php echo $o['order_group']; ?></h4>
                <p>Foods: <?php echo $o['foods']; ?></p>
                <p>Total: ₱<?php echo number_format($o['total_price'],2); ?></p>
                <p>Payment: <?php echo $o['payment_method']; ?></p>
                <?php if(!empty($o['contact_number'])): ?>
                    <p>Contact: <?php echo $o['contact_number']; ?></p>
                <?php endif; ?>
                <p>Delivery Address: <?php echo $o['delivery_address']; ?></p>
                <p>Order Time: <?php echo $o['order_time']; ?></p>
                <p>Status: <span class="status-badge <?php echo $statusClass; ?>"><?php echo $o['status']; ?></span></p>
            </div>
        <?php endforeach; ?>
        </div>

    </aside>

</div>

<script>
function toggleG() {
    const gcashDiv = document.getElementById("gcash");
    const gcashRadio = document.querySelector('input[value="GCash"]');
    gcashDiv.style.display = gcashRadio.checked ? "block" : "none";
}
</script>

</body>
</html>