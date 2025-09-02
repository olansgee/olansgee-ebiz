<?php
// register-service-provider.php
include 'config/constants.php';
include 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and process registration
    $required_fields = [
        'business_name', 'business_description', 'business_address',
        'business_phone', 'service_category', 'years_experience'
    ];
    
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        $error = "Please fill in all required fields: " . implode(', ', $missing_fields);
    } elseif (!isset($_SESSION['user_id'])) {
        $error = "You must be logged in to register as a service provider";
    } else {
        try {
            // Check if user already has a service provider application
            $check_query = "SELECT id FROM service_providers WHERE user_id = :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = "You already have a service provider application pending review";
            } else {
                // Insert service provider application
                $query = "INSERT INTO service_providers 
                         (user_id, business_name, business_description, business_address, 
                         business_phone, business_email, service_category, years_of_experience, 
                         qualifications, service_areas, status) 
                         VALUES 
                         (:user_id, :business_name, :business_description, :business_address,
                         :business_phone, :business_email, :service_category, :years_experience,
                         :qualifications, :service_areas, 'pending')";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->bindParam(':business_name', $_POST['business_name']);
                $stmt->bindParam(':business_description', $_POST['business_description']);
                $stmt->bindParam(':business_address', $_POST['business_address']);
                $stmt->bindParam(':business_phone', $_POST['business_phone']);
                $stmt->bindParam(':business_email', $_POST['business_email']);
                $stmt->bindParam(':service_category', $_POST['service_category']);
                $stmt->bindParam(':years_experience', $_POST['years_experience']);
                $stmt->bindParam(':qualifications', $_POST['qualifications']);
                $stmt->bindParam(':service_areas', $_POST['service_areas']);
                
                if ($stmt->execute()) {
                    $success = "Your service provider application has been submitted successfully! It will be reviewed by our administrators.";
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'service_provider_application', 'Service provider application submitted');
                    
                    // Send notification to admin
                    notifyAdmins("New Service Provider Application", 
                                "User {$_SESSION['username']} has submitted a service provider application.");
                } else {
                    $error = "Error submitting application. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register as Service Provider - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">
                            <i class="bi bi-briefcase me-2"></i>Register as Service Provider
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php else: ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Business Name *</label>
                                        <input type="text" class="form-control" name="business_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Service Category *</label>
                                        <select class="form-select" name="service_category" required>
                                            <option value="">Select Category</option>
                                            <option value="plumbing">Plumbing</option>
                                            <option value="electrical">Electrical</option>
                                            <option value="cleaning">Cleaning</option>
                                            <option value="repair">Repair Services</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Business Description *</label>
                                    <textarea class="form-control" name="business_description" rows="3" required 
                                              placeholder="Describe your business and services..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Business Address *</label>
                                    <textarea class="form-control" name="business_address" rows="2" required></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Business Phone *</label>
                                        <input type="tel" class="form-control" name="business_phone" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Business Email</label>
                                        <input type="email" class="form-control" name="business_email">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Years of Experience *</label>
                                        <input type="number" class="form-control" name="years_experience" min="0" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Service Areas</label>
                                        <input type="text" class="form-control" name="service_areas" 
                                               placeholder="e.g., Lagos, Abuja, Port Harcourt">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Qualifications/Certifications</label>
                                    <textarea class="form-control" name="qualifications" rows="2" 
                                              placeholder="List your qualifications and certifications..."></textarea>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Your application will be reviewed by our administrators. 
                                    You will be notified once your application is approved.
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Submit Application
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="user/dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>