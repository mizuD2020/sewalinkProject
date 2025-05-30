<h2><i class="fas fa-user me-2"></i>Manage Profile</h2>

<?php if (isset($_SESSION['profile_updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>Profile updated successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['profile_updated']); ?>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name"
                                value="<?= htmlspecialchars($worker['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone"
                                value="<?= htmlspecialchars($worker['phone']) ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Service Category</label>
                            <select class="form-select" name="category" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['name']) ?>"
                                        <?= $worker['category_name'] === $category['name'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location"
                                value="<?= htmlspecialchars($worker['location']) ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Introduction</label>
                        <textarea class="form-control" name="introduction" rows="3"
                            required><?= htmlspecialchars($worker['introduction']) ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Years of Experience</label>
                            <input type="text" class="form-control" name="experience"
                                value="<?= htmlspecialchars($worker['experience']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Hourly Rate ($)</label>
                            <input type="number" step="0.01" class="form-control" name="hourly_rate"
                                value="<?= htmlspecialchars($worker['hourly_rate']) ?>" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Profile Stats</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Current Rating:</strong>
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?= $i <= floor($worker['rating']) ? '' : '-o' ?>"></i>
                        <?php endfor; ?>
                        <span class="ms-1"><?= number_format($worker['rating'], 1) ?></span>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Total Reviews:</strong> <?= $worker['reviews_count'] ?>
                </div>
                <div class="mb-3">
                    <strong>Years of Experience:</strong> <?= htmlspecialchars($worker['experience']) ?>
                </div>
                <div class="mb-3">
                    <strong>Total Bookings:</strong> <?= $worker_stats['total_bookings'] ?>
                </div>
                <div class="mb-3">
                    <strong>Response Rate:</strong> <?= $worker_stats['response_rate'] ?>%
                </div>
            </div>
        </div>
    </div>
</div>