<?php
session_start();
//require_once 'config/database.php';
require_once '../models.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $introduction = trim($_POST['introduction'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $hourly_rate = $_POST['hourly_rate'] ?? 0;

    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (Worker::findByEmail($email)) {
        $errors[] = "Email already exists";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (empty($category_id)) {
        $errors[] = "Service category is required";
    }

    if (empty($location)) {
        $errors[] = "Location is required";
    }

    if (empty($hourly_rate) || $hourly_rate < 0) {
        $errors[] = "Valid hourly rate is required";
    }

    // If no errors, create the worker
    if (empty($errors)) {
        try {
            $workerData = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'category_id' => $category_id,
                'location' => $location,
                'introduction' => $introduction,
                'experience' => $experience,
                'hourly_rate' => $hourly_rate,
                'available' => true,
                'verified' => false
            ];

            $workerId = Worker::create($workerData);
            
            if ($workerId) {
                $success = "Registration successful! Your account is pending verification. You can now login.";
                // Clear form data
                $name = $email = $phone = $location = $introduction = $experience = $hourly_rate = '';
                $category_id = '';
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}

// Get categories for dropdown
$categories = Category::findAll('name ASC');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker Registration - SewaLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .signup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            margin: 50px auto;
            max-width: 600px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .form-label {
            font-weight: 600;
            color: #333;
        }
        .header-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <div class="text-center mb-4">
                <i class="fas fa-user-tie header-icon"></i>
                <h2 class="mb-3">Join as a Service Provider</h2>
                <p class="text-muted">Create your worker account to start offering services</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">
                            <i class="fas fa-user me-2"></i>Full Name *
                        </label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope me-2"></i>Email Address *
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">
                            <i class="fas fa-phone me-2"></i>Phone Number *
                        </label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($phone ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="category_id" class="form-label">
                            <i class="fas fa-briefcase me-2"></i>Service Category *
                        </label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo (isset($category_id) && $category_id == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label for="location" class="form-label">
                            <i class="fas fa-map-marker-alt me-2"></i>Location/Area *
                        </label>
                        <input type="text" class="form-control" id="location" name="location" 
                               value="<?php echo htmlspecialchars($location ?? ''); ?>" 
                               placeholder="e.g., Kathmandu, Lalitpur" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="hourly_rate" class="form-label">
                            <i class="fas fa-money-bill me-2"></i>Hourly Rate (NPR) *
                        </label>
                        <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" 
                               value="<?php echo htmlspecialchars($hourly_rate ?? ''); ?>" 
                               min="0" step="0.01" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="experience" class="form-label">
                        <i class="fas fa-clock me-2"></i>Experience
                    </label>
                    <select class="form-control" id="experience" name="experience">
                        <option value="">Select experience level</option>
                        <option value="Beginner (0-1 years)" <?php echo (isset($experience) && $experience == 'Beginner (0-1 years)') ? 'selected' : ''; ?>>Beginner (0-1 years)</option>
                        <option value="Intermediate (1-3 years)" <?php echo (isset($experience) && $experience == 'Intermediate (1-3 years)') ? 'selected' : ''; ?>>Intermediate (1-3 years)</option>
                        <option value="Experienced (3-5 years)" <?php echo (isset($experience) && $experience == 'Experienced (3-5 years)') ? 'selected' : ''; ?>>Experienced (3-5 years)</option>
                        <option value="Expert (5+ years)" <?php echo (isset($experience) && $experience == 'Expert (5+ years)') ? 'selected' : ''; ?>>Expert (5+ years)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="introduction" class="form-label">
                        <i class="fas fa-info-circle me-2"></i>Introduction/Bio
                    </label>
                    <textarea class="form-control" id="introduction" name="introduction" rows="4" 
                              placeholder="Tell customers about yourself, your skills, and what makes you unique..."><?php echo htmlspecialchars($introduction ?? ''); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Password *
                        </label>
                        <input type="password" class="form-control" id="password" name="password" 
                               minlength="6" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Confirm Password *
                        </label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               minlength="6" required>
                    </div>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </div>

                <div class="text-center mt-3">
                    <p class="mb-0">Already have an account? 
                        <a href="worker_login.php" class="text-primary text-decoration-none fw-bold">Login here</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>