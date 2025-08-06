<?php
session_start();

// Database connection
require_once 'db_config.php'; // Assume this file contains the DB connection setup

//Check if user is logged in (you can modify this logic based on your authentication system)
// if (!isset($_SESSION['user_logged_in'])) {
//     header('Location: logins/user_login.php');
//     exit();
// }

if (!isset($_SESSION['user_id'])) {
    header("Location: logins/user_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];


// Database service class
class DatabaseService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getWorkers($category = null)
    {
        $sql = "SELECT w.*, c.name AS category_name 
                FROM workers w
                JOIN categories c ON w.category_id = c.id
                WHERE w.available = 1";

        $params = [];

        if ($category) {
            $sql .= " AND c.name = ?";
            $params[] = $category;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories()
    {
        $stmt = $this->pdo->query("SELECT id, name FROM categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserBookings($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, w.name AS worker_name, c.name AS worker_category 
            FROM bookings b
            JOIN workers w ON b.worker_id = w.id
            JOIN categories c ON w.category_id = c.id
            WHERE b.user_id = ?
            ORDER BY b.preferred_date DESC, b.preferred_time DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWorkerDetails($worker_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT w.*, c.name AS category_name 
            FROM workers w
            JOIN categories c ON w.category_id = c.id
            WHERE w.id = ?
        ");
        $stmt->execute([$worker_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createBooking($data)
    {
        // 1. First check if the date & time slot is already booked
        $checkStmt = $this->pdo->prepare("
        SELECT COUNT(*) 
        FROM bookings 
        WHERE preferred_date = ? 
        AND preferred_time = ?
        AND worker_id = ?
        AND status != 'cancelled'  
    ");

        $checkStmt->execute([
            $data['preferred_date'],
            $data['preferred_time'],
            $data['worker_id']
        ]);

        $conflictExists = (bool) $checkStmt->fetchColumn();

        if ($conflictExists) {
            throw new Exception("This time slot is already booked. Please choose another date/time.");
        }

        // 2. If no conflict, proceed with booking
        $stmt = $this->pdo->prepare("
        INSERT INTO bookings (
            user_id, worker_id, service_description, preferred_date, preferred_time,
            customer_name, customer_phone, address, notes, estimated_duration, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");

        return $stmt->execute([
            $data['user_id'],
            $data['worker_id'],
            $data['service_description'],
            $data['preferred_date'],
            $data['preferred_time'],
            $data['customer_name'],
            $data['customer_phone'],
            $data['address'],
            $data['notes'] ?? null,
            $data['estimated_duration'] ?? 2
        ]);
    }

    public function cancelBooking($booking_id, $user_id)
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings SET status = 'cancelled' 
            WHERE id = ? AND user_id = ? AND status = 'pending'
        ");
        return $stmt->execute([$booking_id, $user_id]);
    }

    public function submitRating($data)
    {
        // First get booking details to validate
        $stmt = $this->pdo->prepare("
            SELECT worker_id FROM bookings 
            WHERE id = ? AND user_id = ? AND status = 'completed'
        ");
        $stmt->execute([$data['booking_id'], $data['user_id']]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking)
            return false;

        // Insert review
        $stmt = $this->pdo->prepare("
            INSERT INTO reviews (
                booking_id, user_id, worker_id, rating, review_text
            ) VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $data['booking_id'],
            $data['user_id'],
            $booking['worker_id'],
            $data['rating'],
            $data['review_text'] ?? null
            // $data['recommend'] ? 1 : 0
        ]);
    }

    public function getBookingForRating($booking_id, $user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, w.name AS worker_name, c.name AS worker_category 
            FROM bookings b
            JOIN workers w ON b.worker_id = w.id
            JOIN categories c ON w.category_id = c.id
            WHERE b.id = ? AND b.user_id = ? AND b.status = 'completed'
        ");
        $stmt->execute([$booking_id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getSiteStats()
    {
        $stats = [];

        // Total workers
        $stmt = $this->pdo->query("SELECT COUNT(*) as total_workers FROM workers");
        $stats['total_workers'] = $stmt->fetchColumn();

        // Average rating
        $stmt = $this->pdo->query("SELECT AVG(rating) as avg_rating FROM reviews");
        $stats['avg_rating'] = round($stmt->fetchColumn(), 1);

        // Total jobs
        $stmt = $this->pdo->query("SELECT COUNT(*) as total_jobs FROM bookings WHERE status = 'completed'");
        $stats['total_jobs'] = $stmt->fetchColumn();

        return $stats;
    }
}

$dbService = new DatabaseService($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'book_worker':
                if (isset($_POST['worker_id'])) {
                    $bookingData = [
                        'user_id' => $user_id,
                        'worker_id' => $_POST['worker_id'],
                        'service_description' => $_POST['service'],
                        'preferred_date' => $_POST['date'],
                        'preferred_time' => $_POST['time'],
                        'customer_name' => $_POST['customer_name'],
                        'customer_phone' => $_POST['phone'],
                        'address' => $_POST['address'],
                        'notes' => $_POST['notes'] ?? null,
                        'estimated_duration' => $_POST['duration'] ?? 2
                    ];


                    try {
                        $bookingCreated = $dbService->createBooking($bookingData);
                        if ($bookingCreated) {
                            $_SESSION['booking_success'] = true;
                        }
                    } catch (Exception $e) {
                        // Show error message
                        echo "<script>alert('" . addslashes($e->getMessage()) . "'); history.back();</script>";
                        exit();
                    }
                }
                break;

            case 'submit_rating':
                if (isset($_POST['booking_id'])) {
                    $ratingData = [
                        'booking_id' => $_POST['booking_id'],
                        'user_id' => $user_id,
                        'rating' => $_POST['rating'],
                        'review_text' => $_POST['review'] ?? null,
                        'recommend' => isset($_POST['recommend'])
                    ];

                    if ($dbService->submitRating($ratingData)) {
                        $_SESSION['rating_submitted'] = true;
                    }
                }
                break;

            case 'cancel_booking':
                if (isset($_POST['booking_id'])) {
                    if ($dbService->cancelBooking($_POST['booking_id'], $user_id)) {
                        $_SESSION['booking_cancelled'] = true;
                    }
                }
                break;
        }
    }
    header("Location: {$_SERVER['PHP_SELF']}?page=" . ($_GET['page'] ?? 'home'));
    exit();
}

// Get current page
$page = $_GET['page'] ?? 'home';

// Get data based on current page
$category_filter = $_GET['category'] ?? null;
$workers = $dbService->getWorkers($category_filter);
$categories = $dbService->getCategories();
$userBookings = $dbService->getUserBookings($user_id);
$siteStats = $dbService->getSiteStats();

// For rating page
$booking_for_rating = null;
if ($page === 'rating' && isset($_GET['booking_id'])) {
    $booking_for_rating = $dbService->getBookingForRating($_GET['booking_id'], $user_id);
    if (!$booking_for_rating) {
        header('Location: ?page=bookings');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SewaLink - User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .worker-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .worker-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .rating-stars {
            color: #ffc107;
        }

        .availability-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        .navbar-brand {
            font-weight: bold;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: box-shadow 0.15s ease-in-out;
        }

        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
        }

        .rating-input {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }

        .rating-input input {
            display: none;
        }

        .rating-input label {
            cursor: pointer;
            color: #e4e5e9;
            transition: color 0.2s;
        }

        .rating-input label:hover,
        .rating-input label:hover~label {
            color: #ffc107 !important;
        }

        .rating-input input:checked~label {
            color: #ffc107;
        }

        .stats-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 0;
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="?page=home">
                <i class="fas fa-tools me-2"></i>SewaLink
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <div class="navbar-nav me-auto">
                    <a class="nav-link <?= $page === 'home' ? 'active' : '' ?>" href="?page=home">
                        <i class="fas fa-home me-1"></i>Find Services
                    </a>
                    <a class="nav-link <?= $page === 'bookings' ? 'active' : '' ?>" href="?page=bookings">
                        <i class="fas fa-calendar-alt me-1"></i>My Bookings
                    </a>
                    <a class="nav-link <?= $page === 'rating' ? 'active' : '' ?>" href="?page=rating">
                        <i class="fas fa-star me-1"></i>Rate Service
                    </a>
                </div>

                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i
                                class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name'] ?? 'Account') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#">
                                    <i class="fas fa-user me-2"></i>My Profile
                                </a></li>
                            <li><a class="dropdown-item" href="#">
                                    <i class="fas fa-cog me-2"></i>Settings
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="logins/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                                </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid">
        <?php if ($page === 'home'): ?>
            <!-- Hero Section -->
            <div class="hero-section">
                <div class="container text-center">
                    <h1 class="display-4 fw-bold mb-3">Find Local Service Professionals</h1>
                    <p class="lead mb-4">Connect with trusted, verified professionals in your area for all your service
                        needs</p>
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="input-group input-group-lg">
                                <input type="text" class="form-control" id="serviceSearch"
                                    placeholder="Search for services...">
                                <button class="btn btn-warning" type="button" id="searchButton">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Section -->
            <div class="container my-5">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h3><?= number_format($siteStats['total_workers']) ?>+</h3>
                                <p>Verified Professionals</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-star fa-2x mb-2"></i>
                                <h3><?= $siteStats['avg_rating'] ?></h3>
                                <p>Average Rating</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-handshake fa-2x mb-2"></i>
                                <h3><?= number_format($siteStats['total_jobs']) ?>+</h3>
                                <p>Jobs Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <i class="fas fa-clock fa-2x mb-2"></i>
                                <h3>24/7</h3>
                                <p>Support Available</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Worker Listings -->
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-search me-2"></i>Available Services</h2>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Filter by Category
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?page=home">All Categories</a></li>
                            <?php foreach ($categories as $category): ?>
                                <li><a class="dropdown-item" href="?page=home&category=<?= urlencode($category['name']) ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <?php if (isset($_SESSION['booking_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>Booking request sent successfully! The worker will contact you
                        soon.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['booking_success']); ?>
                <?php endif; ?>

                <div class="row" id="workersContainer">
                    <?php if (empty($workers)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-tools fa-3x text-muted mb-3"></i>
                            <h4>No available workers found</h4>
                            <p class="text-muted">Try changing your search criteria or check back later</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($workers as $worker): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card worker-card h-100 position-relative">
                                    <span class="badge bg-success availability-badge">
                                        Available
                                    </span>
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="fas fa-user-circle fa-3x text-primary me-3"></i>
                                            <div>
                                                <h5 class="card-title mb-0"><?= htmlspecialchars($worker['name']) ?></h5>
                                                <small class="text-muted"><?= htmlspecialchars($worker['category_name']) ?></small>
                                            </div>
                                        </div>

                                        <div class="mb-2">
                                            <span class="rating-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star<?= $i <= floor($worker['rating']) ? '' : '-o' ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                            <span class="ms-1"><?= number_format($worker['rating'], 1) ?>
                                                (<?= $worker['reviews_count'] ?> reviews)</span>
                                        </div>

                                        <p class="card-text small">
                                            <?= htmlspecialchars(substr($worker['introduction'], 0, 100)) ?>...
                                        </p>

                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span
                                                class="text-success fw-bold">$<?= number_format($worker['hourly_rate'], 2) ?>/hr</span>
                                            <small class="text-muted"><i class="fas fa-map-marker-alt"></i>
                                                <?= htmlspecialchars($worker['location']) ?></small>
                                        </div>

                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-briefcase me-1"></i><?= htmlspecialchars($worker['experience']) ?>
                                                experience
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-primary btn-sm" onclick="showBookingModal(<?= $worker['id'] ?>)">
                                                <i class="fas fa-calendar-plus me-1"></i>Book Service
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm"
                                                onclick="showProfileModal(<?= $worker['id'] ?>)">
                                                <i class="fas fa-eye me-1"></i>View Profile
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($page === 'bookings'): ?>
            <!-- User Bookings -->
            <div class="container my-4">
                <h2><i class="fas fa-calendar-alt me-2"></i>My Bookings</h2>

                <?php if (isset($_SESSION['booking_cancelled'])): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>Booking has been cancelled successfully.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['booking_cancelled']); ?>
                <?php endif; ?>

                <div class="row">
                    <?php if (empty($userBookings)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h4>No bookings yet</h4>
                            <p class="text-muted">Start by browsing available services</p>
                            <a href="?page=home" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Find Services
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($userBookings as $booking): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0">Booking #<?= $booking['id'] ?></h6>
                                        <span class="badge bg-<?=
                                            $booking['status'] === 'confirmed' ? 'success' :
                                            ($booking['status'] === 'pending' ? 'warning' :
                                                ($booking['status'] === 'completed' ? 'info' : 'secondary'))
                                            ?>">
                                            <?= ucfirst($booking['status']) ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <strong>Worker:</strong> <?= htmlspecialchars($booking['worker_name']) ?>
                                            <br><small
                                                class="text-muted"><?= htmlspecialchars($booking['worker_category']) ?></small>
                                        </div>
                                        <p><strong>Service:</strong> <?= htmlspecialchars($booking['service_description']) ?></p>
                                        <p><strong>Date & Time:</strong>
                                            <?= date('M j, Y', strtotime($booking['preferred_date'])) ?> at
                                            <?= date('g:i A', strtotime($booking['preferred_time'])) ?>
                                        </p>
                                        <p><strong>Address:</strong> <?= htmlspecialchars($booking['address']) ?></p>
                                        <?php if ($booking['status'] === 'completed' && $booking['total_cost']): ?>
                                            <p><strong>Total Cost:</strong> <span
                                                    class="text-success">$<?= number_format($booking['total_cost'], 2) ?></span></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-footer">
                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-outline-danger btn-sm"
                                                    onclick="cancelBooking(<?= $booking['id'] ?>)">
                                                    <i class="fas fa-times me-1"></i>Cancel Booking
                                                </button>
                                            </div>
                                        <?php elseif ($booking['status'] === 'completed'): ?>
                                            <div class="d-grid gap-2">
                                                <a href="?page=rating&booking_id=<?= $booking['id'] ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-star me-1"></i>Rate Service
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($page === 'rating' && $booking_for_rating): ?>
            <!-- Rating Page -->
            <div class="container my-4">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h4><i class="fas fa-star me-2"></i>Rate Service</h4>
                            </div>
                            <div class="card-body">
                                <?php if (isset($_SESSION['rating_submitted'])): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>Thank you for your rating! Your feedback helps
                                        other users make better decisions.
                                    </div>
                                    <div class="text-center">
                                        <a href="?page=bookings" class="btn btn-primary">Back to My Bookings</a>
                                    </div>
                                    <?php unset($_SESSION['rating_submitted']); ?>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="submit_rating">
                                        <input type="hidden" name="booking_id" value="<?= $booking_for_rating['id'] ?>">

                                        <div class="mb-3">
                                            <label class="form-label">Service Provider</label>
                                            <div class="p-2 bg-light rounded">
                                                <strong><?= htmlspecialchars($booking_for_rating['worker_name']) ?></strong> -
                                                <?= htmlspecialchars($booking_for_rating['worker_category']) ?><br>
                                                <small
                                                    class="text-muted"><?= htmlspecialchars($booking_for_rating['service_description']) ?>
                                                    on
                                                    <?= date('M j, Y', strtotime($booking_for_rating['preferred_date'])) ?></small>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Rate your experience:</label>
                                            <div class="rating-input">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" name="rating" value="<?= $i ?>" id="star<?= $i ?>" required>
                                                    <label for="star<?= $i ?>" class="fs-4"><i class="fas fa-star"></i></label>
                                                <?php endfor; ?>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Review (optional):</label>
                                            <textarea class="form-control" name="review" rows="4"
                                                placeholder="Share your experience to help others..."></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="recommend" id="recommend">
                                                <label class="form-check-label" for="recommend">
                                                    I would recommend this service provider to others
                                                </label>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-1"></i>Submit Rating
                                            </button>
                                            <a href="?page=bookings" class="btn btn-outline-secondary">Cancel</a>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Book Service</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="book_worker">
                    <input type="hidden" name="worker_id" id="modalWorkerId">
                    <div class="modal-body">
                        <div id="workerInfo" class="mb-4"></div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name</label>
                                <input type="text" class="form-control" name="customer_name"
                                    value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone"
                                    value="<?= htmlspecialchars($_SESSION['user_phone'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Service Required</label>
                            <input type="text" class="form-control" name="service"
                                placeholder="e.g., Kitchen sink repair, Electrical outlet installation" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Preferred Date
                                </label>
                                <input type="date" class="form-control" name="date"
                                    min="<?= htmlspecialchars(date('Y-m-d', strtotime('tomorrow'))); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Preferred Time</label>
                                <select class="form-select" name="time" required>
                                    <option value="">Select Time</option>
                                    <option value="08:00:00">8:00 AM</option>
                                    <option value="09:00:00">9:00 AM</option>
                                    <option value="10:00:00">10:00 AM</option>
                                    <option value="11:00:00">11:00 AM</option>
                                    <option value="12:00:00">12:00 PM</option>
                                    <option value="13:00:00">1:00 PM</option>
                                    <option value="14:00:00">2:00 PM</option>
                                    <option value="15:00:00">3:00 PM</option>
                                    <option value="16:00:00">4:00 PM</option>
                                    <option value="17:00:00">5:00 PM</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Your Address</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Enter your full address"
                                required><?= htmlspecialchars($_SESSION['user_address'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Additional Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="2"
                                placeholder="Any specific requirements, access instructions, or additional details"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Estimated Duration</label>
                            <select class="form-select" name="duration">
                                <option value="1">1 hour</option>
                                <option value="2" selected>2 hours</option>
                                <option value="3">3 hours</option>
                                <option value="4">4+ hours</option>
                            </select>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This is a booking request. The service provider will contact you to
                            confirm availability and finalize details.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Booking Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Worker Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Worker Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="profileContent">
                        <!-- Content will be loaded dynamically -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="bookFromProfile()">
                        <i class="fas fa-calendar-plus me-1"></i>Book This Worker
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-tools me-2"></i>SewaLink</h5>
                    <p class="mb-0">Connecting you with trusted local professionals for all your service needs.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="mb-2">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-linkedin"></i></a>
                    </div>
                    <small>&copy; 2025 SewaLink. All rights reserved.</small>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Worker data for JavaScript
        const workers = <?= json_encode($workers) ?>;

        function showBookingModal(workerId) {
            const worker = workers.find(w => w.id == workerId);
            if (!worker) return;

            document.getElementById('modalWorkerId').value = workerId;

            const workerInfo = document.getElementById('workerInfo');
            workerInfo.innerHTML = `
                <div class="row">
                    <div class="col-md-2">
                        <i class="fas fa-user-circle fa-4x text-primary"></i>
                    </div>
                    <div class="col-md-10">
                        <h5>${worker.name}</h5>
                        <p class="text-muted">${worker.category_name}</p>
                        <p class="mb-1">${worker.introduction}</p>
                        <div class="d-flex align-items-center mb-2">
                            <span class="rating-stars me-2">
                                ${generateStars(worker.rating)}
                            </span>
                            <span>${worker.rating} (${worker.reviews_count} reviews)</span>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <small><i class="fas fa-dollar-sign text-success"></i> $${worker.hourly_rate}/hour</small>
                            </div>
                            <div class="col-md-6">
                                <small><i class="fas fa-map-marker-alt text-muted"></i> ${worker.location}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.querySelector('#bookingModal input[name="date"]');
            if (dateInput) {
                dateInput.min = today;
                if (!dateInput.value) dateInput.value = today;
            }

            new bootstrap.Modal(document.getElementById('bookingModal')).show();
        }

        function showProfileModal(workerId) {
            const worker = workers.find(w => w.id == workerId);
            if (!worker) return;

            // Fetch worker details from server if not already in the workers array
            fetchWorkerDetails(workerId).then(worker => {
                const profileContent = document.getElementById('profileContent');
                profileContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                            <h4>${worker.name}</h4>
                            <p class="text-muted">${worker.category_name}</p>
                            <span class="badge ${worker.available ? 'bg-success' : 'bg-secondary'} mb-3">
                                ${worker.available ? 'Available' : 'Currently Busy'}
                            </span>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <h6>About</h6>
                                <p>${worker.introduction}</p>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <h6>Experience</h6>
                                    <p><i class="fas fa-briefcase text-primary me-2"></i>${worker.experience}</p>
                                </div>
                                <div class="col-6">
                                    <h6>Location</h6>
                                    <p><i class="fas fa-map-marker-alt text-primary me-2"></i>${worker.location}</p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <h6>Rating</h6>
                                    <div class="d-flex align-items-center">
                                        <span class="rating-stars me-2">${generateStars(worker.rating)}</span>
                                        <span>${worker.rating}/5</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h6>Reviews</h6>
                                    <p>${worker.reviews_count} customer reviews</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Hourly Rate</h6>
                                <p class="text-success fs-5 mb-0">$${worker.hourly_rate}/hour</p>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Contact</h6>
                                <p><i class="fas fa-phone text-primary me-2"></i>${worker.phone}</p>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div>
                        <h6>Recent Reviews</h6>
                        <div id="reviewsContainer">
                            <div class="text-center py-3">
                                <i class="fas fa-spinner fa-spin"></i> Loading reviews...
                            </div>
                        </div>
                    </div>
                `;

                // Load reviews
                fetchWorkerReviews(workerId).then(reviews => {
                    const reviewsContainer = document.getElementById('reviewsContainer');
                    if (reviews.length > 0) {
                        let html = '<div class="row">';
                        reviews.forEach(review => {
                            html += `
                                <div class="col-md-6">
                                    <div class="card mb-2">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <strong>${review.user_name || 'Anonymous'}</strong>
                                                <span class="rating-stars">${generateStars(review.rating)}</span>
                                            </div>
                                            ${review.review_text ? `<p class="mb-0 small">${review.review_text}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        reviewsContainer.innerHTML = html;
                    } else {
                        reviewsContainer.innerHTML = '<p class="text-muted">No reviews yet</p>';
                    }
                });

                // Store current worker ID for booking
                window.currentProfileWorkerId = workerId;

                new bootstrap.Modal(document.getElementById('profileModal')).show();
            });
        }

        async function fetchWorkerDetails(workerId) {
            try {
                const response = await fetch(`api/get_worker.php?id=${workerId}`);
                if (!response.ok) throw new Error('Failed to fetch worker details');
                return await response.json();
            } catch (error) {
                console.error('Error fetching worker details:', error);
                return workers.find(w => w.id == workerId);
            }
        }

        async function fetchWorkerReviews(workerId) {
            try {
                const response = await fetch(`api/get_reviews.php?worker_id=${workerId}`);
                if (!response.ok) throw new Error('Failed to fetch reviews');
                return await response.json();
            } catch (error) {
                console.error('Error fetching reviews:', error);
                return [];
            }
        }

        function bookFromProfile() {
            if (window.currentProfileWorkerId) {
                document.getElementById('profileModal').querySelector('.btn-close').click();
                setTimeout(() => {
                    showBookingModal(window.currentProfileWorkerId);
                }, 300);
            }
        }

        function generateStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= Math.floor(rating)) {
                    stars += '<i class="fas fa-star"></i>';
                } else if (i === Math.ceil(rating) && rating % 1 !== 0) {
                    stars += '<i class="fas fa-star-half-alt"></i>';
                } else {
                    stars += '<i class="far fa-star"></i>';
                }
            }
            return stars;
        }

        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="booking_id" value="${bookingId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search functionality
        function searchServices() {
            const searchTerm = document.getElementById('serviceSearch').value.toLowerCase();
            const workerCards = document.querySelectorAll('.worker-card');

            workerCards.forEach(card => {
                const workerName = card.querySelector('.card-title').textContent.toLowerCase();
                const workerCategory = card.querySelector('.text-muted').textContent.toLowerCase();
                const workerIntro = card.querySelector('.card-text').textContent.toLowerCase();

                if (workerName.includes(searchTerm) ||
                    workerCategory.includes(searchTerm) ||
                    workerIntro.includes(searchTerm)) {
                    card.closest('.col-md-6').style.display = 'block';
                } else {
                    card.closest('.col-md-6').style.display = 'none';
                }
            });
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function () {
            // Set minimum date for booking to today
            const dateInput = document.querySelector('input[name="date"]');
            if (dateInput) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.min = today;
                if (!dateInput.value) dateInput.value = today;
            }

            // Add search event listeners
            const searchButton = document.getElementById('searchButton');
            const searchInput = document.getElementById('serviceSearch');

            if (searchButton && searchInput) {
                searchButton.addEventListener('click', searchServices);
                searchInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        searchServices();
                    }
                });
            }

            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>

</html>