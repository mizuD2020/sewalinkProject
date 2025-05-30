<h2><i class="fas fa-calendar-check me-2"></i>Manage Availability</h2>

<?php if (isset($_SESSION['availability_status'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>Availability status updated!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['availability_status']); ?>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Current Status</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="toggle_availability">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="availability" value="on"
                            id="availabilityToggle" <?= $worker['available'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="availabilityToggle">
                            I'm available for new bookings
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Status Information</h5>
            </div>
            <div class="card-body">
                <p><strong>Current Status:</strong>
                    <span class="badge bg-<?= $worker['available'] ? 'success' : 'secondary' ?>">
                        <?= $worker['available'] ? 'Available' : 'Busy' ?>
                    </span>
                </p>
                <p class="text-muted">
                    When you're available, customers can see and book your services.
                    When you're busy, your profile will be marked as unavailable.
                </p>
            </div>
        </div>
    </div>
</div>