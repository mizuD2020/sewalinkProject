<h2><i class="fas fa-calendar-alt me-2"></i>Booking Requests</h2>

<?php if (isset($_SESSION['booking_response'])): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <i class="fas fa-info-circle me-2"></i>Booking request <?= $_SESSION['booking_response'] ?>!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['booking_response']); ?>
<?php endif; ?>

<div class="row">
    <?php foreach ($bookings as $booking): ?>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Booking Request #<?= $booking['id'] ?></h6>
                    <span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : 'warning' ?>">
                        <?= ucfirst($booking['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <p><strong>Customer:</strong> <?= htmlspecialchars($booking['user_name']) ?></p>
                    <p><strong>Service:</strong> <?= htmlspecialchars($booking['service_description']) ?></p>
                    <p><strong>Date & Time:</strong> <?= date('M j, Y', strtotime($booking['preferred_date'])) ?> at
                        <?= date('g:i A', strtotime($booking['preferred_time'])) ?>
                    </p>
                    <p><strong>Address:</strong> <?= htmlspecialchars($booking['address']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($booking['customer_phone']) ?></p>
                    <?php if (!empty($booking['notes'])): ?>
                        <p><strong>Notes:</strong> <?= htmlspecialchars($booking['notes']) ?></p>
                    <?php endif; ?>

                    <?php if ($booking['status'] === 'pending'): ?>
                        <div class="d-flex gap-2">
                            <form method="POST" class="flex-fill">
                                <input type="hidden" name="action" value="respond_booking">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <input type="hidden" name="response" value="accepted">
                                <button type="submit" class="btn btn-success btn-sm w-100">
                                    <i class="fas fa-check me-1"></i>Accept
                                </button>
                            </form>
                            <form method="POST" class="flex-fill">
                                <input type="hidden" name="action" value="respond_booking">
                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                <input type="hidden" name="response" value="declined">
                                <button type="submit" class="btn btn-danger btn-sm w-100">
                                    <i class="fas fa-times me-1"></i>Decline
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>