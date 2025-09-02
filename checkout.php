<?php
// checkout.php
require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/Cart.php';
require_once 'classes/Payment.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$cart = new Cart($db);
$payment = new Payment($db);

// Check if user is logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php?redirect=checkout');
    exit;
}

// Get user cart items
$cart_items = $cart->getUserCart($_SESSION['id']);
$total_amount = 0;

foreach ($cart_items as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

// Process checkout
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];
    $shipping_address = $_POST['shipping_address'];
    
    // Create order first
    $order_ref = 'ORD' . date('YmdHis') . rand(100, 999);
    
    try {
        $db->beginTransaction();
        
        // Create order
        $order_query = "INSERT INTO orders 
                       (order_number, user_id, total_amount, payment_method, shipping_address, status) 
                       VALUES 
                       (:order_number, :user_id, :total_amount, :payment_method, :shipping_address, 'pending')";
        
        $order_stmt = $db->prepare($order_query);
        $order_stmt->bindParam(':order_number', $order_ref);
        $order_stmt->bindParam(':user_id', $_SESSION['id']);
        $order_stmt->bindParam(':total_amount', $total_amount);
        $order_stmt->bindParam(':payment_method', $payment_method);
        $order_stmt->bindParam(':shipping_address', $shipping_address);
        
        if (!$order_stmt->execute()) {
            throw new Exception('Failed to create order');
        }
        
        $order_id = $db->lastInsertId();
        
        // Add order items
        foreach ($cart_items as $item) {
            $item_total = $item['price'] * $item['quantity'];
            
            $item_query = "INSERT INTO order_items 
                          (order_id, product_id, quantity, unit_price, total_price) 
                          VALUES 
                          (:order_id, :product_id, :quantity, :unit_price, :total_price)";
            
            $item_stmt = $db->prepare($item_query);
            $item_stmt->bindParam(':order_id', $order_id);
            $item_stmt->bindParam(':product_id', $item['product_id']);
            $item_stmt->bindParam(':quantity', $item['quantity']);
            $item_stmt->bindParam(':unit_price', $item['price']);
            $item_stmt->bindParam(':total_price', $item_total);
            
            if (!$item_stmt->execute()) {
                throw new Exception('Failed to add order items');
            }
        }
        
        // Initialize payment based on method
        if (in_array($payment_method, ['card', 'mobile_money', 'bank_transfer'])) {
            $tx_ref = 'TX' . date('YmdHis') . rand(100, 999);
            
            // Save initial transaction record
            $transaction_data = [
                'transaction_ref' => $tx_ref,
                'user_id' => $_SESSION['id'],
                'amount' => $total_amount,
                'transaction_type' => 'sale',
                'payment_method' => $payment_method,
                'payment_status' => 'pending',
                'payment_details' => json_encode(['order_id' => $order_id]),
                'order_id' => $order_id
            ];
            
            if (!$payment->saveTransaction($transaction_data)) {
                throw new Exception('Failed to save transaction');
            }
            
            // Initialize payment gateway
            $meta = [
                'order_id' => $order_id,
                'user_id' => $_SESSION['id'],
                'cart_items' => $cart_items
            ];
            
            if ($payment_method == 'card') {
                // Use Flutterwave for card payments
                $result = $payment->initializeFlutterwave(
                    $total_amount,
                    $_SESSION['email'],
                    $tx_ref,
                    SITE_URL . '/payment-callback.php',
                    $meta
                );
                
                if ($result['status'] == 'success') {
                    $db->commit();
                    header('Location: ' . $result['data']['link']);
                    exit;
                } else {
                    throw new Exception('Payment initialization failed: ' . $result['message']);
                }
            } elseif (in_array($payment_method, ['mobile_money', 'bank_transfer'])) {
                // Use Paystack for mobile money and bank transfers
                $result = $payment->initializePaystack(
                    $total_amount,
                    $_SESSION['email'],
                    $tx_ref,
                    SITE_URL . '/payment-callback.php',
                    $meta
                );
                
                if ($result['status']) {
                    $db->commit();
                    header('Location: ' . $result['data']['authorization_url']);
                    exit;
                } else {
                    throw new Exception('Payment initialization failed: ' . $result['message']);
                }
            }
        } elseif ($payment_method == 'cash') {
            // For cash on delivery
            $tx_ref = 'CASH' . date('YmdHis') . rand(100, 999);
            
            $transaction_data = [
                'transaction_ref' => $tx_ref,
                'user_id' => $_SESSION['id'],
                'amount' => $total_amount,
                'transaction_type' => 'sale',
                'payment_method' => 'cash',
                'payment_status' => 'pending',
                'payment_details' => json_encode(['order_id' => $order_id, 'cod' => true]),
                'order_id' => $order_id
            ];
            
            if ($payment->saveTransaction($transaction_data)) {
                $db->commit();
                
                // Clear cart
                $cart->clearCart($_SESSION['id']);
                
                // Redirect to success page
                header('Location: order-success.php?order_id=' . $order_id);
                exit;
            } else {
                throw new Exception('Failed to save cash transaction');
            }
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Shipping Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="checkoutForm" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" 
                                       value="<?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo $_SESSION['email']; ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Shipping Address *</label>
                                <textarea class="form-control" name="shipping_address" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" value="card" id="cardPayment" checked>
                            <label class="form-check-label" for="cardPayment">
                                <i class="bi bi-credit-card"></i> Credit/Debit Card
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" value="mobile_money" id="mobilePayment">
                            <label class="form-check-label" for="mobilePayment">
                                <i class="bi bi-phone"></i> Mobile Money
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" value="bank_transfer" id="bankPayment">
                            <label class="form-check-label" for="bankPayment">
                                <i class="bi bi-bank"></i> Bank Transfer
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" value="cash" id="cashPayment">
                            <label class="form-check-label" for="cashPayment">
                                <i class="bi bi-cash"></i> Cash on Delivery
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span><?php echo $item['name']; ?> × <?php echo $item['quantity']; ?></span>
                                <span><?php echo DEFAULT_CURRENCY . number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        
                        <hr>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?php echo DEFAULT_CURRENCY . number_format($total_amount, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span><?php echo DEFAULT_CURRENCY; ?>0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax:</span>
                            <span><?php echo DEFAULT_CURRENCY; ?>0.00</span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong><?php echo DEFAULT_CURRENCY . number_format($total_amount, 2); ?></strong>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-lock"></i> Complete Order
                        </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>