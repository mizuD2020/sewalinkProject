<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview</h2>
    <div class="text-muted">
        Welcome back, <?= htmlspecialchars($_SESSION['worker_name']) ?>!
    </div>
</div>

<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-center bg-primary text-white">
            <div class="card-body">
                <i class="fas fa-calendar-check fa-2x mb-2"></i>
                <h4><?= count(array_filter($bookings, fn($b) => $b['status'] === 'pending')) ?></h4>
                <p class="mb-0">Pending Bookings</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-center bg-success text-white">
            <div class="card-body">
                <i class="fas fa-star fa-2x mb-2"></i>
                <h4><?= number_format($worker['rating'], 1) ?></h4>
                <p class="mb-0">Average Rating</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-center bg-info text-white">
            <div class="card-body">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h4>$<?= number_format($worker['hourly_rate'], 2) ?></h4>
                <p class="mb-0">Hourly Rate</p>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card text-center bg-warning text-white">
            <div class="card-body">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h4><?= $worker['available'] ? 'Available' : 'Busy' ?></h4>
                <p class="mb-0">Current Status</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-history me-2"></i>Recent Booking Requests</h5>
    </div>
    <div class="card-body">
        <?php if (empty($bookings)): ?>
            <p class="text-muted">No recent bookings.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= htmlspecialchars($booking['user_name']) ?></td>
                                <td><?= htmlspecialchars($booking['service_description']) ?></td>
                                <td><?= date('M j, Y', strtotime($booking['preferred_date'])) ?> at
                                    <?= date('g:i A', strtotime($booking['preferred_time'])) ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?page=bookings" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>