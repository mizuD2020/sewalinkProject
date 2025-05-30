<?php
session_start();

// Database connection
require_once '../db_config.php';

// Check if admin is logged in
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: admin_login.php');
//     exit();
// }

// Admin service class
class AdminService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Worker management
    public function getWorkers($page = 1, $per_page = 10, $search = '')
    {
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT w.*, c.name AS category_name 
            FROM workers w
            JOIN categories c ON w.category_id = c.id
            WHERE w.name LIKE ? OR w.email LIKE ? OR c.name LIKE ?
            ORDER BY w.id DESC
            LIMIT ? OFFSET ?";

        $search_term = "%$search%";
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getWorkerCount($search = '')
    {
        $sql = "SELECT COUNT(*) as count
                FROM workers w
                JOIN categories c ON w.category_id = c.id
                WHERE w.name LIKE ? OR w.email LIKE ? OR c.name LIKE ?";

        $search_term = "%$search%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$search_term, $search_term, $search_term]);
        return $stmt->fetchColumn();
    }

    public function getWorker($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT w.*, c.name AS category_name 
            FROM workers w
            JOIN categories c ON w.category_id = c.id
            WHERE w.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateWorker($id, $data)
    {
        $stmt = $this->pdo->prepare("
            UPDATE workers SET 
                name = ?, email = ?, phone = ?, category_id = ?, 
                location = ?, introduction = ?, experience = ?, 
                hourly_rate = ?, available = ?, verified = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['category_id'],
            $data['location'],
            $data['introduction'],
            $data['experience'],
            $data['hourly_rate'],
            $data['available'],
            $data['verified'],
            $id
        ]);
    }

    public function deleteWorker($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM workers WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // User management
    public function getUsers($page = 1, $per_page = 10, $search = '')
    {
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT * FROM users 
            WHERE name LIKE ? OR email LIKE ?
            ORDER BY id DESC
            LIMIT ? OFFSET ?";

        $stmt = $this->pdo->prepare($sql);
        $search_term = "%$search%";

        // Bind parameters with proper types
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $per_page, PDO::PARAM_INT);  // Explicit integer type
        $stmt->bindValue(4, $offset, PDO::PARAM_INT);    // Explicit integer type

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getUserCount($search = '')
    {
        $sql = "SELECT COUNT(*) as count FROM users 
                WHERE name LIKE ? OR email LIKE ?";

        $search_term = "%$search%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$search_term, $search_term]);
        return $stmt->fetchColumn();
    }

    public function getUser($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateUser($id, $data)
    {
        $stmt = $this->pdo->prepare("
            UPDATE users SET 
                name = ?, email = ?, phone = ?, address = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $id
        ]);
    }

    public function deleteUser($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Booking management
    public function getBookings($page = 1, $per_page = 10, $search = '', $status = '')
    {
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT b.*, u.name AS user_name, w.name AS worker_name, c.name AS category_name
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN workers w ON b.worker_id = w.id
            JOIN categories c ON w.category_id = c.id
            WHERE (u.name LIKE ? OR w.name LIKE ? OR b.service_description LIKE ?)
            " . ($status ? "AND b.status = ?" : "") . "
            ORDER BY b.preferred_date DESC, b.preferred_time DESC
            LIMIT " . (int) $per_page . " OFFSET " . (int) $offset;

        $search_term = "%$search%";
        $params = [$search_term, $search_term, $search_term];
        if ($status) {
            $params[] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookingCount($search = '', $status = '')
    {
        $sql = "SELECT COUNT(*) as count
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN workers w ON b.worker_id = w.id
                WHERE (u.name LIKE ? OR w.name LIKE ? OR b.service_description LIKE ?)
                " . ($status ? "AND b.status = ?" : "");

        $search_term = "%$search%";
        $params = [$search_term, $search_term, $search_term];
        if ($status)
            $params[] = $status;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function getBooking($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.name AS user_name, w.name AS worker_name, c.name AS category_name
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN workers w ON b.worker_id = w.id
            JOIN categories c ON w.category_id = c.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateBookingStatus($id, $status)
    {
        $stmt = $this->pdo->prepare("
            UPDATE bookings SET status = ? WHERE id = ?
        ");
        return $stmt->execute([$status, $id]);
    }

    public function deleteBooking($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM bookings WHERE id = ?");
        return $stmt->execute([$id]);
    }

    //  Category management
    public function getCategories($page = 1, $per_page = 10, $search = '')
    {
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT * FROM categories 
            WHERE name LIKE ? OR description LIKE ?
            ORDER BY name
            LIMIT " . (int) $per_page . " OFFSET " . (int) $offset;

        $search_term = "%$search%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$search_term, $search_term]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getCategoryCount($search = '')
    {
        $sql = "SELECT COUNT(*) as count FROM categories 
                WHERE name LIKE ? OR description LIKE ?";

        $search_term = "%$search%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$search_term, $search_term]);
        return $stmt->fetchColumn();
    }

    public function getCategory($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createCategory($data)
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO categories (name, description) 
            VALUES (?, ?)
        ");
        return $stmt->execute([$data['name'], $data['description']]);
    }

    public function updateCategory($id, $data)
    {
        $stmt = $this->pdo->prepare("
            UPDATE categories SET 
                name = ?, description = ?
            WHERE id = ?
        ");
        return $stmt->execute([$data['name'], $data['description'], $id]);
    }

    public function deleteCategory($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Review management
    public function getReviews($page = 1, $per_page = 10, $search = '')
    {
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT r.*, u.name AS user_name, w.name AS worker_name
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN workers w ON r.worker_id = w.id
            WHERE u.name LIKE ? OR w.name LIKE ? OR r.review_text LIKE ?
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?";

        $search_term = "%$search%";
        $stmt = $this->pdo->prepare($sql);

        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $per_page, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReviewCount($search = '')
    {
        $sql = "SELECT COUNT(*) as count
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                JOIN workers w ON r.worker_id = w.id
                WHERE u.name LIKE ? OR w.name LIKE ? OR r.review_text LIKE ?";

        $search_term = "%$search%";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$search_term, $search_term, $search_term]);
        return $stmt->fetchColumn();
    }

    public function getReview($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.name AS user_name, w.name AS worker_name
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN workers w ON r.worker_id = w.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteReview($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM reviews WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Dashboard statistics
    public function getDashboardStats()
    {
        $stats = [];

        // Total workers
        $stmt = $this->pdo->query("SELECT COUNT(*) as total_workers FROM workers");
        $stats['total_workers'] = $stmt->fetchColumn();

        // Total users
        $stmt = $this->pdo->query("SELECT COUNT(*) as total_users FROM users");
        $stats['total_users'] = $stmt->fetchColumn();

        // Total bookings
        $stmt = $this->pdo->query("SELECT COUNT(*) as total_bookings FROM bookings");
        $stats['total_bookings'] = $stmt->fetchColumn();

        // Total completed bookings
        $stmt = $this->pdo->query("SELECT COUNT(*) as completed_bookings FROM bookings WHERE status = 'completed'");
        $stats['completed_bookings'] = $stmt->fetchColumn();

        // Recent bookings
        $stmt = $this->pdo->query("
            SELECT b.*, u.name AS user_name, w.name AS worker_name
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN workers w ON b.worker_id = w.id
            ORDER BY b.created_at DESC
            LIMIT 5
        ");
        $stats['recent_bookings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent reviews
        $stmt = $this->pdo->query("
            SELECT r.*, u.name AS user_name, w.name AS worker_name
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            JOIN workers w ON r.worker_id = w.id
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $stats['recent_reviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
}

$adminService = new AdminService($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_worker':
                if (isset($_POST['worker_id'])) {
                    $data = [
                        'name' => $_POST['name'],
                        'email' => $_POST['email'],
                        'phone' => $_POST['phone'],
                        'category_id' => $_POST['category_id'],
                        'location' => $_POST['location'],
                        'introduction' => $_POST['introduction'],
                        'experience' => $_POST['experience'],
                        'hourly_rate' => $_POST['hourly_rate'],
                        'available' => isset($_POST['available']) ? 1 : 0,
                        'verified' => isset($_POST['verified']) ? 1 : 0
                    ];

                    if ($adminService->updateWorker($_POST['worker_id'], $data)) {
                        $_SESSION['success_message'] = 'Worker updated successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to update worker';
                    }
                }
                break;

            case 'delete_worker':
                if (isset($_POST['worker_id'])) {
                    if ($adminService->deleteWorker($_POST['worker_id'])) {
                        $_SESSION['success_message'] = 'Worker deleted successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to delete worker';
                    }
                }
                break;

            case 'update_user':
                if (isset($_POST['user_id'])) {
                    $data = [
                        'name' => $_POST['name'],
                        'email' => $_POST['email'],
                        'phone' => $_POST['phone'],
                        'address' => $_POST['address']
                    ];

                    if ($adminService->updateUser($_POST['user_id'], $data)) {
                        $_SESSION['success_message'] = 'User updated successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to update user';
                    }
                }
                break;

            case 'delete_user':
                if (isset($_POST['user_id'])) {
                    if ($adminService->deleteUser($_POST['user_id'])) {
                        $_SESSION['success_message'] = 'User deleted successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to delete user';
                    }
                }
                break;

            case 'update_booking_status':
                if (isset($_POST['booking_id']) && isset($_POST['status'])) {
                    if ($adminService->updateBookingStatus($_POST['booking_id'], $_POST['status'])) {
                        $_SESSION['success_message'] = 'Booking status updated successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to update booking status';
                    }
                }
                break;

            case 'delete_booking':
                if (isset($_POST['booking_id'])) {
                    if ($adminService->deleteBooking($_POST['booking_id'])) {
                        $_SESSION['success_message'] = 'Booking deleted successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to delete booking';
                    }
                }
                break;

            case 'create_category':
                $data = [
                    'name' => $_POST['name'],
                    'description' => $_POST['description']
                ];

                if ($adminService->createCategory($data)) {
                    $_SESSION['success_message'] = 'Category created successfully';
                } else {
                    $_SESSION['error_message'] = 'Failed to create category';
                }
                break;

            case 'update_category':
                if (isset($_POST['category_id'])) {
                    $data = [
                        'name' => $_POST['name'],
                        'description' => $_POST['description']
                    ];

                    if ($adminService->updateCategory($_POST['category_id'], $data)) {
                        $_SESSION['success_message'] = 'Category updated successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to update category';
                    }
                }
                break;

            case 'delete_category':
                if (isset($_POST['category_id'])) {
                    if ($adminService->deleteCategory($_POST['category_id'])) {
                        $_SESSION['success_message'] = 'Category deleted successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to delete category';
                    }
                }
                break;

            case 'delete_review':
                if (isset($_POST['review_id'])) {
                    if ($adminService->deleteReview($_POST['review_id'])) {
                        $_SESSION['success_message'] = 'Review deleted successfully';
                    } else {
                        $_SESSION['error_message'] = 'Failed to delete review';
                    }
                }
                break;
        }

        header("Location: {$_SERVER['REQUEST_URI']}");
        exit();
    }
}

// Get current page and parameters
$page = $_GET['page'] ?? 'dashboard';
$subpage = $_GET['subpage'] ?? 'list';
$id = $_GET['id'] ?? null;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$current_page = $_GET['p'] ?? 1;
$per_page = 10;

// Get categories for dropdowns
$categories = $adminService->getCategories(1, 1000);

// Get data based on current page
$data = [];
$item = null;
$total_count = 0;

if ($page === 'workers') {
    if ($subpage === 'edit' && $id) {
        $item = $adminService->getWorker($id);
    } else {
        $data = $adminService->getWorkers($current_page, $per_page, $search);
        $total_count = $adminService->getWorkerCount($search);
    }
} elseif ($page === 'users') {
    if ($subpage === 'edit' && $id) {
        $item = $adminService->getUser($id);
    } else {
        $data = $adminService->getUsers($current_page, $per_page, $search);
        $total_count = $adminService->getUserCount($search);
    }
} elseif ($page === 'bookings') {
    if ($subpage === 'view' && $id) {
        $item = $adminService->getBooking($id);
    } else {
        $data = $adminService->getBookings($current_page, $per_page, $search, $status);
        $total_count = $adminService->getBookingCount($search, $status);
    }
} elseif ($page === 'categories') {
    if ($subpage === 'edit' && $id) {
        $item = $adminService->getCategory($id);
    } else {
        $data = $adminService->getCategories($current_page, $per_page, $search);
        $total_count = $adminService->getCategoryCount($search);
    }
} elseif ($page === 'reviews') {
    if ($subpage === 'view' && $id) {
        $item = $adminService->getReview($id);
    } else {
        $data = $adminService->getReviews($current_page, $per_page, $search);
        $total_count = $adminService->getReviewCount($search);
    }
} elseif ($page === 'dashboard') {
    $data = $adminService->getDashboardStats();
}

// Calculate pagination
$total_pages = ceil($total_count / $per_page);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Local Service Finder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 5px;
            margin: 2px 0;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: box-shadow 0.15s ease-in-out;
        }

        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .stats-card {
            border-left: 4px solid;
        }

        .stats-card.primary {
            border-left-color: #3498db;
        }

        .stats-card.success {
            border-left-color: #2ecc71;
        }

        .stats-card.warning {
            border-left-color: #f39c12;
        }

        .stats-card.danger {
            border-left-color: #e74c3c;
        }

        .rating-stars {
            color: #f39c12;
        }

        .badge-pending {
            background-color: #f39c12;
        }

        .badge-confirmed {
            background-color: #2ecc71;
        }

        .badge-completed {
            background-color: #3498db;
        }

        .badge-cancelled {
            background-color: #e74c3c;
        }

        .badge-declined {
            background-color: #7f8c8d;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
        }
    </style>
</head>

<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <div class="avatar mx-auto mb-2">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <h5 class="text-white mb-0">Admin Panel</h5>
                        <small
                            class="text-white-50"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator') ?></small>
                    </div>
                    <nav class="nav flex-column">
                        <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link <?= $page === 'workers' ? 'active' : '' ?>" href="?page=workers">
                            <i class="fas fa-tools"></i> Workers
                        </a>
                        <a class="nav-link <?= $page === 'users' ? 'active' : '' ?>" href="?page=users">
                            <i class="fas fa-users"></i> Users
                        </a>
                        <a class="nav-link <?= $page === 'bookings' ? 'active' : '' ?>" href="?page=bookings">
                            <i class="fas fa-calendar-alt"></i> Bookings
                        </a>
                        <a class="nav-link <?= $page === 'categories' ? 'active' : '' ?>" href="?page=categories">
                            <i class="fas fa-list"></i> Categories
                        </a>
                        <a class="nav-link <?= $page === 'reviews' ? 'active' : '' ?>" href="?page=reviews">
                            <i class="fas fa-star"></i> Reviews
                        </a>
                        <hr class="text-white-50 my-2">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <?php
                        $page_titles = [
                            'dashboard' => 'Dashboard',
                            'workers' => 'Workers',
                            'users' => 'Users',
                            'bookings' => 'Bookings',
                            'categories' => 'Categories',
                            'reviews' => 'Reviews'
                        ];
                        echo $page_titles[$page] ?? 'Dashboard';

                        if ($subpage === 'edit')
                            echo ' / Edit';
                        elseif ($subpage === 'view')
                            echo ' / View';
                        elseif ($subpage === 'create')
                            echo ' / Create New';
                        ?>
                    </h2>

                    <?php if ($page !== 'dashboard' && $subpage === 'list'): ?>
                        <div class="d-flex">
                            <form class="me-2" method="GET">
                                <input type="hidden" name="page" value="<?= $page ?>">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" placeholder="Search..."
                                        value="<?= htmlspecialchars($search) ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </form>
                            <?php if ($page === 'bookings'): ?>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                        data-bs-toggle="dropdown">
                                        <?= $status ? ucfirst($status) : 'All Statuses' ?>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="?page=bookings">All Statuses</a></li>
                                        <li><a class="dropdown-item" href="?page=bookings&status=pending">Pending</a></li>
                                        <li><a class="dropdown-item" href="?page=bookings&status=confirmed">Confirmed</a></li>
                                        <li><a class="dropdown-item" href="?page=bookings&status=completed">Completed</a></li>
                                        <li><a class="dropdown-item" href="?page=bookings&status=cancelled">Cancelled</a></li>
                                        <li><a class="dropdown-item" href="?page=bookings&status=declined">Declined</a></li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Page Content -->
                <?php if ($page === 'dashboard'): ?>
                    <!-- Dashboard Overview -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card primary h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Workers</h5>
                                            <h2 class="mb-0"><?= number_format($data['total_workers']) ?></h2>
                                        </div>
                                        <div class="avatar">
                                            <i class="fas fa-tools"></i>
                                        </div>
                                    </div>
                                    <a href="?page=workers" class="stretched-link"></a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card success h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Users</h5>
                                            <h2 class="mb-0"><?= number_format($data['total_users']) ?></h2>
                                        </div>
                                        <div class="avatar">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                    <a href="?page=users" class="stretched-link"></a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card warning h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Bookings</h5>
                                            <h2 class="mb-0"><?= number_format($data['total_bookings']) ?></h2>
                                        </div>
                                        <div class="avatar">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                    </div>
                                    <a href="?page=bookings" class="stretched-link"></a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card stats-card danger h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h5 class="card-title">Completed</h5>
                                            <h2 class="mb-0"><?= number_format($data['completed_bookings']) ?></h2>
                                        </div>
                                        <div class="avatar">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                    </div>
                                    <a href="?page=bookings&status=completed" class="stretched-link"></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Bookings</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($data['recent_bookings'])): ?>
                                        <p class="text-muted">No recent bookings</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>User</th>
                                                        <th>Worker</th>
                                                        <th>Status</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($data['recent_bookings'] as $booking): ?>
                                                        <tr onclick="window.location='?page=bookings&subpage=view&id=<?= $booking['id'] ?>'"
                                                            style="cursor: pointer;">
                                                            <td>#<?= $booking['id'] ?></td>
                                                            <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                                            <td><?= htmlspecialchars($booking['worker_name']) ?></td>
                                                            <td>
                                                                <span class="badge badge-<?= strtolower($booking['status']) ?>">
                                                                    <?= ucfirst($booking['status']) ?>
                                                                </span>
                                                            </td>
                                                            <td><?= date('M j, Y', strtotime($booking['preferred_date'])) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer text-end">
                                    <a href="?page=bookings" class="btn btn-sm btn-primary">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Reviews</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($data['recent_reviews'])): ?>
                                        <p class="text-muted">No recent reviews</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>User</th>
                                                        <th>Worker</th>
                                                        <th>Rating</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($data['recent_reviews'] as $review): ?>
                                                        <tr onclick="window.location='?page=reviews&subpage=view&id=<?= $review['id'] ?>'"
                                                            style="cursor: pointer;">
                                                            <td>#<?= $review['id'] ?></td>
                                                            <td><?= htmlspecialchars($review['user_name']) ?></td>
                                                            <td><?= htmlspecialchars($review['worker_name']) ?></td>
                                                            <td>
                                                                <span class="rating-stars">
                                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                        <i
                                                                            class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                                                    <?php endfor; ?>
                                                                </span>
                                                            </td>
                                                            <td><?= date('M j, Y', strtotime($review['created_at'])) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer text-end">
                                    <a href="?page=reviews" class="btn btn-sm btn-primary">View All</a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php elseif ($page === 'workers' && $subpage === 'list'): ?>
                    <!-- Workers List -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Workers List</h5>
                            <a href="?page=workers&subpage=create" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i> Add Worker
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data)): ?>
                                <p class="text-muted">No workers found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Location</th>
                                                <th>Rate</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $worker): ?>
                                                <tr>
                                                    <td>#<?= $worker['id'] ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar me-2">
                                                                <i class="fas fa-user"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?= htmlspecialchars($worker['name']) ?></strong>
                                                                <div class="text-muted small">
                                                                    <?= htmlspecialchars($worker['email']) ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($worker['category_name']) ?></td>
                                                    <td><?= htmlspecialchars($worker['location']) ?></td>
                                                    <td>$<?= number_format($worker['hourly_rate'], 2) ?></td>
                                                    <td>
                                                        <?php if ($worker['available']): ?>
                                                            <span class="badge bg-success">Available</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Busy</span>
                                                        <?php endif; ?>
                                                        <?php if ($worker['verified']): ?>
                                                            <span class="badge bg-primary">Verified</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="?page=workers&subpage=edit&id=<?= $worker['id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete('worker', <?= $worker['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=workers&p=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                    <a class="page-link"
                                                        href="?page=workers&p=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=workers&p=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($page === 'workers' && ($subpage === 'edit' || $subpage === 'create')): ?>
                    <!-- Worker Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= $subpage === 'create' ? 'Add New Worker' : 'Edit Worker' ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action"
                                    value="<?= $subpage === 'create' ? 'create_worker' : 'update_worker' ?>">
                                <?php if ($subpage === 'edit'): ?>
                                    <input type="hidden" name="worker_id" value="<?= $item['id'] ?>">
                                <?php endif; ?>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="name"
                                            value="<?= htmlspecialchars($item['name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email"
                                            value="<?= htmlspecialchars($item['email'] ?? '') ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone"
                                            value="<?= htmlspecialchars($item['phone'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category_id" required>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?= $category['id'] ?>" <?= ($item['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($category['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" name="location"
                                            value="<?= htmlspecialchars($item['location'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Hourly Rate ($)</label>
                                        <input type="number" step="0.01" class="form-control" name="hourly_rate"
                                            value="<?= htmlspecialchars($item['hourly_rate'] ?? '') ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Introduction</label>
                                    <textarea class="form-control" name="introduction" rows="3"
                                        required><?= htmlspecialchars($item['introduction'] ?? '') ?></textarea>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Experience</label>
                                        <input type="text" class="form-control" name="experience"
                                            value="<?= htmlspecialchars($item['experience'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check mt-4 pt-2">
                                            <input class="form-check-input" type="checkbox" name="available" id="available"
                                                <?= ($item['available'] ?? 1) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="available">
                                                Available for work
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="verified" id="verified"
                                                <?= ($item['verified'] ?? 0) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="verified">
                                                Verified worker
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="?page=workers" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($page === 'users' && $subpage === 'list'): ?>
                    <!-- Users List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Users List</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data)): ?>
                                <p class="text-muted">No users found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $user): ?>
                                                <tr>
                                                    <td>#<?= $user['id'] ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar me-2">
                                                                <i class="fas fa-user"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?= htmlspecialchars($user['name']) ?></strong>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                    <td><?= htmlspecialchars($user['phone']) ?></td>
                                                    <td>
                                                        <a href="?page=users&subpage=edit&id=<?= $user['id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete('user', <?= $user['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=users&p=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                    <a class="page-link"
                                                        href="?page=users&p=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=users&p=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($page === 'users' && $subpage === 'edit'): ?>
                    <!-- User Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Edit User</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?= $item['id'] ?>">

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="name"
                                            value="<?= htmlspecialchars($item['name']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email"
                                            value="<?= htmlspecialchars($item['email']) ?>" required>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone"
                                            value="<?= htmlspecialchars($item['phone']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address"
                                            rows="1"><?= htmlspecialchars($item['address']) ?></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="?page=users" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($page === 'bookings' && $subpage === 'list'): ?>
                    <!-- Bookings List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Bookings List</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data)): ?>
                                <p class="text-muted">No bookings found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Worker</th>
                                                <th>Service</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $booking): ?>
                                                <tr onclick="window.location='?page=bookings&subpage=view&id=<?= $booking['id'] ?>'"
                                                    style="cursor: pointer;">
                                                    <td>#<?= $booking['id'] ?></td>
                                                    <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                                    <td><?= htmlspecialchars($booking['worker_name']) ?></td>
                                                    <td><?= htmlspecialchars(substr($booking['service_description'], 0, 30)) ?><?= strlen($booking['service_description']) > 30 ? '...' : '' ?>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($booking['preferred_date'])) ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= strtolower($booking['status']) ?>">
                                                            <?= ucfirst($booking['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="?page=bookings&subpage=view&id=<?= $booking['id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="event.stopPropagation(); confirmDelete('booking', <?= $booking['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=bookings&p=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"
                                                    aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                    <a class="page-link"
                                                        href="?page=bookings&p=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=bookings&p=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"
                                                    aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($page === 'bookings' && $subpage === 'view'): ?>
                    <!-- Booking Details -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Booking #<?= $item['id'] ?></h5>
                            <span class="badge badge-<?= strtolower($item['status']) ?>">
                                <?= ucfirst($item['status']) ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>User Information</h6>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($item['user_name']) ?></p>
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($item['customer_phone']) ?></p>
                                    <p><strong>Address:</strong> <?= htmlspecialchars($item['address']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Worker Information</h6>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($item['worker_name']) ?></p>
                                    <p><strong>Category:</strong> <?= htmlspecialchars($item['category_name']) ?></p>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Service Details</h6>
                                    <p><strong>Service:</strong> <?= htmlspecialchars($item['service_description']) ?></p>
                                    <?php if (!empty($item['notes'])): ?>
                                        <p><strong>Notes:</strong> <?= htmlspecialchars($item['notes']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6>Timing</h6>
                                    <p><strong>Date:</strong> <?= date('M j, Y', strtotime($item['preferred_date'])) ?></p>
                                    <p><strong>Time:</strong> <?= date('g:i A', strtotime($item['preferred_time'])) ?></p>
                                    <?php if ($item['estimated_duration']): ?>
                                        <p><strong>Estimated Duration:</strong> <?= $item['estimated_duration'] ?> hours</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($item['status'] === 'completed'): ?>
                                <div class="alert alert-info">
                                    <h6>Completion Details</h6>
                                    <?php if ($item['actual_start_time']): ?>
                                        <p><strong>Started:</strong>
                                            <?= date('M j, Y g:i A', strtotime($item['actual_start_time'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($item['actual_end_time']): ?>
                                        <p><strong>Completed:</strong>
                                            <?= date('M j, Y g:i A', strtotime($item['actual_end_time'])) ?></p>
                                    <?php endif; ?>
                                    <?php if ($item['total_cost']): ?>
                                        <p><strong>Total Cost:</strong> $<?= number_format($item['total_cost'], 2) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex justify-content-between">
                                <a href="?page=bookings" class="btn btn-secondary">Back to List</a>
                                <?php if ($item['status'] !== 'completed' && $item['status'] !== 'cancelled'): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Update Status
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_booking_status">
                                                    <input type="hidden" name="booking_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" class="dropdown-item">Confirm</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_booking_status">
                                                    <input type="hidden" name="booking_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="dropdown-item">Mark as Completed</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_booking_status">
                                                    <input type="hidden" name="booking_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="dropdown-item">Cancel</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update_booking_status">
                                                    <input type="hidden" name="booking_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="status" value="declined">
                                                    <button type="submit" class="dropdown-item">Decline</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($page === 'categories' && $subpage === 'list'): ?>
                    <!-- Categories List -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Service Categories</h5>
                            <a href="?page=categories&subpage=create" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i> Add Category
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data)): ?>
                                <p class="text-muted">No categories found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $category): ?>
                                                <tr>
                                                    <td>#<?= $category['id'] ?></td>
                                                    <td><?= htmlspecialchars($category['name']) ?></td>
                                                    <td><?= htmlspecialchars(substr($category['description'], 0, 50)) ?><?= strlen($category['description']) > 50 ? '...' : '' ?>
                                                    </td>
                                                    <td>
                                                        <a href="?page=categories&subpage=edit&id=<?= $category['id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete('category', <?= $category['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=categories&p=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                    <a class="page-link"
                                                        href="?page=categories&p=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=categories&p=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($page === 'categories' && ($subpage === 'edit' || $subpage === 'create')): ?>
                    <!-- Category Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= $subpage === 'create' ? 'Add New Category' : 'Edit Category' ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action"
                                    value="<?= $subpage === 'create' ? 'create_category' : 'update_category' ?>">
                                <?php if ($subpage === 'edit'): ?>
                                    <input type="hidden" name="category_id" value="<?= $item['id'] ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label">Category Name</label>
                                    <input type="text" class="form-control" name="name"
                                        value="<?= htmlspecialchars($item['name'] ?? '') ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description"
                                        rows="3"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <a href="?page=categories" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($page === 'reviews' && $subpage === 'list'): ?>
                    <!-- Reviews List -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Service Reviews</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data)): ?>
                                <p class="text-muted">No reviews found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Worker</th>
                                                <th>Rating</th>
                                                <th>Review</th>
                                                <th>Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($data as $review): ?>
                                                <tr onclick="window.location='?page=reviews&subpage=view&id=<?= $review['id'] ?>'"
                                                    style="cursor: pointer;">
                                                    <td>#<?= $review['id'] ?></td>
                                                    <td><?= htmlspecialchars($review['user_name']) ?></td>
                                                    <td><?= htmlspecialchars($review['worker_name']) ?></td>
                                                    <td>
                                                        <span class="rating-stars">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="fas fa-star<?= $i <= $review['rating'] ? '' : '-o' ?>"></i>
                                                            <?php endfor; ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars(substr($review['review_text'], 0, 30)) ?><?= strlen($review['review_text']) > 30 ? '...' : '' ?>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($review['created_at'])) ?></td>
                                                    <td>
                                                        <a href="?page=reviews&subpage=view&id=<?= $review['id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button class="btn btn-sm btn-outline-danger"
                                                            onclick="event.stopPropagation(); confirmDelete('review', <?= $review['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=reviews&p=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                                <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                                                    <a class="page-link"
                                                        href="?page=reviews&p=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link"
                                                    href="?page=reviews&p=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>"
                                                    aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($page === 'reviews' && $subpage === 'view'): ?>
                    <!-- Review Details -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Review #<?= $item['id'] ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>User Information</h6>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($item['user_name']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Worker Information</h6>
                                    <p><strong>Name:</strong> <?= htmlspecialchars($item['worker_name']) ?></p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6>Rating</h6>
                                <div class="rating-stars fs-4">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?= $i <= $item['rating'] ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                    <span class="ms-2">(<?= $item['rating'] ?>/5)</span>
                                </div>
                            </div>

                            <?php if (!empty($item['review_text'])): ?>
                                <div class="mb-4">
                                    <h6>Review</h6>
                                    <div class="p-3 bg-light rounded">
                                        <?= nl2br(htmlspecialchars($item['review_text'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($item['response_text'])): ?>
                                <div class="mb-4">
                                    <h6>Worker Response</h6>
                                    <div class="p-3 bg-light rounded">
                                        <?= nl2br(htmlspecialchars($item['response_text'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <!-- 
                            <div class="mb-4">
                                <p><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($item['created_at'])) ?></p>
                                <?php if ($item['recommend']): ?>
                                    <p><strong>Recommend:</strong> <i class="fas fa-check text-success"></i> Yes</p>
                                <?php else: ?>
                                    <p><strong>Recommend:</strong> <i class="fas fa-times text-danger"></i> No</p>
                                <?php endif; ?>
                            </div> -->

                            <div class="d-flex justify-content-between">
                                <a href="?page=reviews" class="btn btn-secondary">Back to List</a>
                                <button class="btn btn-danger" onclick="confirmDelete('review', <?= $item['id'] ?>)">
                                    <i class="fas fa-trash me-1"></i> Delete Review
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="">
                        <input type="hidden" name="worker_id" value="">
                        <input type="hidden" name="user_id" value="">
                        <input type="hidden" name="booking_id" value="">
                        <input type="hidden" name="category_id" value="">
                        <input type="hidden" name="review_id" value="">
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Confirm delete function
        function confirmDelete(type, id) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const form = document.getElementById('deleteForm');

            // Set form action and ID based on type
            form.action = '';
            form.querySelector('input[name="action"]').value = 'delete_' + type;

            // Reset all ID fields
            form.querySelector('input[name="worker_id"]').value = '';
            form.querySelector('input[name="user_id"]').value = '';
            form.querySelector('input[name="booking_id"]').value = '';
            form.querySelector('input[name="category_id"]').value = '';
            form.querySelector('input[name="review_id"]').value = '';

            // Set the correct ID field
            switch (type) {
                case 'worker':
                    form.querySelector('input[name="worker_id"]').value = id;
                    break;
                case 'user':
                    form.querySelector('input[name="user_id"]').value = id;
                    break;
                case 'booking':
                    form.querySelector('input[name="booking_id"]').value = id;
                    break;
                case 'category':
                    form.querySelector('input[name="category_id"]').value = id;
                    break;
                case 'review':
                    form.querySelector('input[name="review_id"]').value = id;
                    break;
            }

            modal.show();
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Set minimum date for booking date fields to today
        document.addEventListener('DOMContentLoaded', function () {
            const today = new Date().toISOString().split('T')[0];
            const dateInputs = document.querySelectorAll('input[type="date"]');

            dateInputs.forEach(input => {
                if (!input.value) {
                    input.value = today;
                }
                input.min = today;
            });
        });
    </script>
</body>

</html>