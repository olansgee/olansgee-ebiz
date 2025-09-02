<?php
// login.php
include 'config/constants.php';
include 'config/database.php';
include 'classes/User.php';
include 'includes/functions.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user->username = trim($_POST["username"]);
    $user->password = trim($_POST["password"]);
    
   if ($user->login()) {
    $_SESSION["loggedin"] = true;
    $_SESSION["id"] = $user->id;
    $_SESSION["username"] = $user->username;
    $_SESSION["role"] = $user->role;
    $_SESSION["first_name"] = $user->first_name;
    $_SESSION["last_name"] = $user->last_name;
    
    // Log login activity - FIXED
    logActivity($user->id, "user_login", "User logged in successfully", "users", $user->id);
        
        switch ($user->role) {
            case 'admin':
                header("location: admin/dashboard.php");
                break;
            case 'cashier':
                header("location: cashier/dashboard.php");
                break;
            case 'service_provider':
                header("location: service-provider/dashboard.php");
                break;
            default:
                header("location: user/dashboard.php");
        }
        exit;
    } else {
    $login_err = "Invalid username or password.";
    // Log failed login attempt - SIMPLIFIED
    logActivity(null, "login_failed", "Failed login attempt for username: " . $user->username);
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="card shadow">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="assets/images/logo.png" alt="Logo" height="60" class="mb-3">
                    <h2 class="card-title"><?php echo SITE_NAME; ?></h2>
                    <p class="text-muted">Sign in to your account</p>
                </div>
                
                <?php if (!empty($login_err)): ?>
                    <div class="alert alert-danger"><?php echo $login_err; ?></div>
                <?php endif; ?>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-2"></i>Sign In</button>
                    </div>
                </form>
                
                <hr>
                <div class="text-center">
                    <p class="mb-0">Don't have an account? <a href="register.php">Sign up</a></p>
                    <p><a href="forgot-password.php">Forgot your password?</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>