<?php
session_start();
require_once 'config/db_config.php';
require_once 'classes/DatabaseService.php';

$worker_id = $_SESSION['worker_id'] ?? null;
if (!$worker_id) {
    header('Location: ../logins/worker_login.php');
    exit();
}

$dbService = new DatabaseService($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_availability':
                $available = ($_POST['availability'] ?? 'off') === 'on' ? 1 : 0;
                if ($dbService->updateWorkerAvailability($worker_id, $available)) {
                    $_SESSION['availability_status'] = $available ? 'on' : 'off';
                }
                break;

            case 'update_profile':
                $category_id = null;
                foreach ($dbService->getCategories() as $category) {
                    if ($category['name'] === $_POST['category']) {
                        $category_id = $category['id'];
                        break;
                    }
                }

                if ($category_id) {
                    $profileData = [
                        'name' => $_POST['name'],
                        'phone' => $_POST['phone'],
                        'category_id' => $category_id,
                        'location' => $_POST['location'],
                        'introduction' => $_POST['introduction'],
                        'experience' => $_POST['experience'],
                        'hourly_rate' => $_POST['hourly_rate']
                    ];

                    if ($dbService->updateWorkerProfile($worker_id, $profileData)) {
                        $_SESSION['profile_updated'] = true;
                    }
                }
                break;

            case 'respond_booking':
                $booking_id = $_POST['booking_id'] ?? 0;
                $response = $_POST['response'] ?? '';

                if ($booking_id && in_array($response, ['accepted', 'declined'])) {
                    $status = $response === 'accepted' ? 'confirmed' : 'declined';
                    if ($dbService->updateBookingStatus($booking_id, $status)) {
                        $_SESSION['booking_response'] = $response;
                    }
                }
                break;
        }
    }
    header("Location: {$_SERVER['PHP_SELF']}");
    exit();
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';

// Get worker data
$worker = $dbService->getWorker($worker_id);
if (!$worker) {
    session_destroy();
    header('Location: ../logins/worker_login.php');
    exit();
}

// Update session with current worker data
$_SESSION['worker_name'] = $worker['name'];
$_SESSION['worker_category_name'] = $worker['category_name'];

// Get other data
$bookings = $dbService->getWorkerBookings($worker_id);
$categories = $dbService->getCategories();
$worker_stats = $dbService->getWorkerStats($worker_id);

require 'templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php require 'templates/sidebar.php'; ?>

        <div class="col-md-9 col-lg-10">
            <div class="p-4">
                <?php
                switch ($page) {
                    case 'dashboard':
                        require 'templates/dashboard.php';
                        break;
                    case 'availability':
                        require 'templates/availability.php';
                        break;
                    case 'bookings':
                        require 'templates/bookings.php';
                        break;
                    case 'profile':
                        require 'templates/profile.php';
                        break;
                    default:
                        require 'templates/dashboard.php';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php require 'templates/footer.php'; ?>