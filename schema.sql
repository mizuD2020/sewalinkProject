-- Local Service Finder Database Schema

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users table (for customers)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Workers table
CREATE TABLE workers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    location VARCHAR(255) NOT NULL,
    introduction TEXT,
    experience VARCHAR(50),
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    available BOOLEAN DEFAULT TRUE,
    rating DECIMAL(3,2) DEFAULT 0.00,
    reviews_count INT DEFAULT 0,
    total_bookings INT DEFAULT 0,
    response_rate DECIMAL(5,2) DEFAULT 0.00,
    profile_image VARCHAR(255),
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    INDEX idx_category_available (category_id, available),
    INDEX idx_location (location),
    INDEX idx_rating (rating DESC)
);

-- Bookings table
CREATE TABLE bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    worker_id INT NOT NULL,
    service_description TEXT NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20),
    address TEXT NOT NULL,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'declined') DEFAULT 'pending',
    estimated_duration INT, -- in minutes
    actual_start_time DATETIME,
    actual_end_time DATETIME,
    total_cost DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    INDEX idx_worker_status (worker_id, status),
    INDEX idx_user_date (user_id, preferred_date),
    INDEX idx_date_status (preferred_date, status)
);

-- Reviews table
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL UNIQUE,
    user_id INT NOT NULL,
    worker_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    response_text TEXT, -- worker's response to review
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    INDEX idx_worker_rating (worker_id, rating),
    INDEX idx_created_at (created_at DESC)
);

-- Worker availability schedules
CREATE TABLE worker_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    worker_id INT NOT NULL,
    day_of_week TINYINT NOT NULL CHECK (day_of_week >= 0 AND day_of_week <= 6), -- 0 = Sunday, 6 = Saturday
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_worker_day (worker_id, day_of_week)
);

-- Worker time-off/unavailable periods
CREATE TABLE worker_time_off (
    id INT PRIMARY KEY AUTO_INCREMENT,
    worker_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    INDEX idx_worker_dates (worker_id, start_date, end_date)
);

-- Messages/Chat between users and workers
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    sender_type ENUM('user', 'worker') NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking_created (booking_id, created_at),
    INDEX idx_unread (is_read, created_at)
);

-- Favorite workers for users
CREATE TABLE user_favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    worker_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_worker (user_id, worker_id)
);

-- Payment records
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'online', 'bank_transfer') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(255),
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_status_date (payment_status, payment_date)
);

-- Worker portfolio/gallery images
CREATE TABLE worker_portfolio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    worker_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (worker_id) REFERENCES workers(id) ON DELETE CASCADE,
    INDEX idx_worker_order (worker_id, display_order)
);

-- Admin users table
CREATE TABLE admin (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- System notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_type ENUM('user', 'worker') NOT NULL,
    recipient_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('booking', 'payment', 'review', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient_unread (recipient_type, recipient_id, is_read),
    INDEX idx_created_at (created_at DESC)
);

-- Insert default categories
INSERT INTO categories (name, description) VALUES
('Plumber', 'Plumbing services including repairs, installations, and maintenance'),
('Electrician', 'Electrical services for homes and businesses'),
('Carpenter', 'Woodwork, furniture repair, and custom carpentry'),
('House Cleaner', 'Professional house cleaning and maintenance services'),
('Gardener', 'Landscaping, garden maintenance, and outdoor services'),
('Mechanic', 'Vehicle repair and maintenance services'),
('Painter', 'Interior and exterior painting services');

-- Triggers to update worker ratings automatically
DELIMITER //

CREATE TRIGGER update_worker_rating_after_review_insert
    AFTER INSERT ON reviews
    FOR EACH ROW
BEGIN
    UPDATE workers 
    SET rating = (
        SELECT AVG(rating) FROM reviews WHERE worker_id = NEW.worker_id
    ),
    reviews_count = (
        SELECT COUNT(*) FROM reviews WHERE worker_id = NEW.worker_id
    )
    WHERE id = NEW.worker_id;
END//

CREATE TRIGGER update_worker_rating_after_review_update
    AFTER UPDATE ON reviews
    FOR EACH ROW
BEGIN
    UPDATE workers 
    SET rating = (
        SELECT AVG(rating) FROM reviews WHERE worker_id = NEW.worker_id
    ),
    reviews_count = (
        SELECT COUNT(*) FROM reviews WHERE worker_id = NEW.worker_id
    )
    WHERE id = NEW.worker_id;
END//

CREATE TRIGGER update_worker_rating_after_review_delete
    AFTER DELETE ON reviews
    FOR EACH ROW
BEGIN
    UPDATE workers 
    SET rating = COALESCE((
        SELECT AVG(rating) FROM reviews WHERE worker_id = OLD.worker_id
    ), 0.00),
    reviews_count = (
        SELECT COUNT(*) FROM reviews WHERE worker_id = OLD.worker_id
    )
    WHERE id = OLD.worker_id;
END//

-- Trigger to update total bookings count
CREATE TRIGGER update_worker_bookings_count
    AFTER INSERT ON bookings
    FOR EACH ROW
BEGIN
    UPDATE workers 
    SET total_bookings = (
        SELECT COUNT(*) FROM bookings 
        WHERE worker_id = NEW.worker_id AND status IN ('confirmed', 'completed')
    )
    WHERE id = NEW.worker_id;
END//

DELIMITER ;

-- Create indexes for better performance
CREATE INDEX idx_workers_category_rating ON workers(category_id, rating DESC, available);
CREATE INDEX idx_bookings_worker_date ON bookings(worker_id, preferred_date, status);
CREATE INDEX idx_reviews_worker_created ON reviews(worker_id, created_at DESC);
CREATE INDEX idx_messages_booking_created ON messages(booking_id, created_at);
CREATE INDEX idx_notifications_recipient ON notifications(recipient_type, recipient_id, is_read, created_at DESC);