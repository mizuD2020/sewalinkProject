<div class="col-md-3 col-lg-2 px-0">
    <div class="sidebar p-3">
        <div class="text-center mb-4">
            <i class="fas fa-user-circle fa-3x text-white mb-2"></i>
            <h5 class="text-white"><?= htmlspecialchars($_SESSION['worker_name']) ?></h5>
            <small class="text-white-50"><?= htmlspecialchars($_SESSION['worker_category_name']) ?></small>
            <div class="mt-2">
                <span class="badge <?= $worker['available'] ? 'bg-success' : 'bg-secondary' ?>">
                    <?= $worker['available'] ? 'Available' : 'Busy' ?>
                </span>
            </div>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
            </a>
            <a class="nav-link <?= $page === 'availability' ? 'active' : '' ?>" href="?page=availability">
                <i class="fas fa-calendar-check me-2"></i>Availability
            </a>
            <a class="nav-link <?= $page === 'bookings' ? 'active' : '' ?>" href="?page=bookings">
                <i class="fas fa-calendar-alt me-2"></i>Booking Requests
            </a>
            <a class="nav-link <?= $page === 'profile' ? 'active' : '' ?>" href="?page=profile">
                <i class="fas fa-user me-2"></i>Manage Profile
            </a>
            <hr class="text-white-50">
            <a class="nav-link text-danger" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>
</div>