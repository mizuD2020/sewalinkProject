<?php

class Database
{
    private static $connection = null;

    public static function getConnection()
    {
        if (self::$connection === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $dbname = $_ENV['DB_NAME'] ?? 'sewalink';
                $username = $_ENV['DB_USER'] ?? 'root';
                $password = $_ENV['DB_PASS'] ?? '';

                $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return self::$connection;
    }
}

// Base CRUD class
abstract class BaseModel
{
    protected static $table;
    protected static $primaryKey = 'id';

    public static function findById($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function findAll($orderBy = null, $limit = null, $offset = 0)
    {
        $db = Database::getConnection();
        $sql = "SELECT * FROM " . static::$table;

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function delete($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        return $stmt->execute([$id]);
    }

    public static function count($conditions = [])
    {
        $db = Database::getConnection();
        $sql = "SELECT COUNT(*) as count FROM " . static::$table;

        if (!empty($conditions)) {
            $whereClause = [];
            $params = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $whereClause);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute();
        }

        $result = $stmt->fetch();
        return $result['count'];
    }
}

// Categories Model
class Category extends BaseModel
{
    protected static $table = 'categories';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$data['name'], $data['description'] ?? null]);
        return $db->lastInsertId();
    }

    public static function update($id, $data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE categories SET name = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$data['name'], $data['description'] ?? null, $id]);
    }

    public static function findByName($name)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    public static function getWithWorkerCount()
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT c.*, COUNT(w.id) as worker_count 
                             FROM categories c 
                             LEFT JOIN workers w ON c.id = w.category_id AND w.available = 1 
                             GROUP BY c.id 
                             ORDER BY c.name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

// Users Model
class User extends BaseModel
{
    protected static $table = 'users';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO users (name, email, phone, password_hash, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['address'] ?? null
        ]);
        return $db->lastInsertId();
    }

    public static function update($id, $data)
    {
        $db = Database::getConnection();
        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['email'])) {
            $fields[] = "email = ?";
            $params[] = $data['email'];
        }
        if (isset($data['phone'])) {
            $fields[] = "phone = ?";
            $params[] = $data['phone'];
        }
        if (isset($data['address'])) {
            $fields[] = "address = ?";
            $params[] = $data['address'];
        }
        if (isset($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function findByEmail($email)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function verifyPassword($email, $password)
    {
        $user = self::findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        return false;
    }
}

// Workers Model
class Worker extends BaseModel
{
    protected static $table = 'workers';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO workers (name, email, phone, password_hash, category_id, location, introduction, experience, hourly_rate, available, profile_image, verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['category_id'],
            $data['location'],
            $data['introduction'] ?? null,
            $data['experience'] ?? null,
            $data['hourly_rate'] ?? 0.00,
            $data['available'] ?? true,
            $data['profile_image'] ?? null,
            $data['verified'] ?? false
        ]);
        return $db->lastInsertId();
    }

    public static function update($id, $data)
    {
        $db = Database::getConnection();
        $fields = [];
        $params = [];

        $allowedFields = ['name', 'email', 'phone', 'category_id', 'location', 'introduction', 'experience', 'hourly_rate', 'available', 'profile_image', 'verified'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (isset($data['password'])) {
            $fields[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE workers SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function findByEmail($email)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('select categories.name as categories_name, workers.* from workers join categories on categories.id = workers.category_id where email = ?');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public static function getWorkers()
    {
        $db = Database::getConnection();
        $stmt = $db->prepare('select id, name, category_id, phone, location, introduction, experience, rating, reviews_count, available, hourly_rate FROM workers');
        $stmt->execute();
        return $stmt->fetch();
    }

    public static function findByCategory($categoryId, $available = true, $limit = null)
    {
        $db = Database::getConnection();
        $sql = "SELECT w.*, c.name as category_name FROM workers w 
                JOIN categories c ON w.category_id = c.id 
                WHERE w.category_id = ?";
        $params = [$categoryId];

        if ($available) {
            $sql .= " AND w.available = 1";
        }

        $sql .= " ORDER BY w.rating DESC, w.reviews_count DESC";

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findAvailable($location = null, $limit = null)
    {
        $db = Database::getConnection();
        $sql = "SELECT w.*, c.name as category_name FROM workers w 
                JOIN categories c ON w.category_id = c.id 
                WHERE w.available = 1";
        $params = [];

        if ($location) {
            $sql .= " AND w.location LIKE ?";
            $params[] = "%{$location}%";
        }

        $sql .= " ORDER BY w.rating DESC, w.reviews_count DESC";

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function searchWorkers($query, $categoryId = null, $location = null, $minRating = null)
    {
        $db = Database::getConnection();
        $sql = "SELECT w.*, c.name as category_name FROM workers w 
                JOIN categories c ON w.category_id = c.id 
                WHERE w.available = 1 AND (w.name LIKE ? OR w.introduction LIKE ?)";
        $params = ["%{$query}%", "%{$query}%"];

        if ($categoryId) {
            $sql .= " AND w.category_id = ?";
            $params[] = $categoryId;
        }

        if ($location) {
            $sql .= " AND w.location LIKE ?";
            $params[] = "%{$location}%";
        }

        if ($minRating) {
            $sql .= " AND w.rating >= ?";
            $params[] = $minRating;
        }

        $sql .= " ORDER BY w.rating DESC, w.reviews_count DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function verifyPassword($email, $password)
    {
        $worker = self::findByEmail($email);
        if ($worker && password_verify($password, $worker['password_hash'])) {
            return $worker;
        }
        return false;
    }

    public static function toggleAvailability($id, $available)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE workers SET available = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$available, $id]);
    }

    public static function getTopRated($limit = 10)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT w.*, c.name as category_name 
                             FROM workers w 
                             JOIN categories c ON w.category_id = c.id 
                             WHERE w.available = 1 AND w.rating > 0 
                             ORDER BY w.rating DESC, w.reviews_count DESC 
                             LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}

// Bookings Model
class Booking extends BaseModel
{
    protected static $table = 'bookings';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO bookings (user_id, worker_id, service_description, preferred_date, preferred_time, customer_name, customer_phone, address, notes, status, estimated_duration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['user_id'],
            $data['worker_id'],
            $data['service_description'],
            $data['preferred_date'],
            $data['preferred_time'],
            $data['customer_name'],
            $data['customer_phone'] ?? null,
            $data['address'],
            $data['notes'] ?? null,
            $data['status'] ?? 'pending',
            $data['estimated_duration'] ?? null
        ]);
        return $db->lastInsertId();
    }

    public static function update($id, $data)
    {
        $db = Database::getConnection();
        $fields = [];
        $params = [];

        $allowedFields = ['service_description', 'preferred_date', 'preferred_time', 'customer_name', 'customer_phone', 'address', 'notes', 'status', 'estimated_duration', 'actual_start_time', 'actual_end_time', 'total_cost'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE bookings SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function findByWorker($workerId, $status = null, $limit = null)
    {
        $db = Database::getConnection();
        $sql = "SELECT b.*, u.name as user_name FROM bookings b 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE b.worker_id = ?";
        $params = [$workerId];

        if ($status) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY b.preferred_date DESC, b.preferred_time DESC";

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function findByUser($userId, $status = null, $limit = null)
    {
        $db = Database::getConnection();
        $sql = "SELECT b.*, w.name as worker_name, c.name as category_name FROM bookings b 
                JOIN workers w ON b.worker_id = w.id 
                JOIN categories c ON w.category_id = c.id 
                WHERE b.user_id = ?";
        $params = [$userId];

        if ($status) {
            $sql .= " AND b.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY b.preferred_date DESC, b.preferred_time DESC";

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function updateStatus($id, $status)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE bookings SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public static function findUpcoming($workerId, $days = 7)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT b.*, u.name as user_name FROM bookings b 
                             LEFT JOIN users u ON b.user_id = u.id 
                             WHERE b.worker_id = ? AND b.status IN ('confirmed', 'pending') 
                             AND b.preferred_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) 
                             ORDER BY b.preferred_date, b.preferred_time");
        $stmt->execute([$workerId, $days]);
        return $stmt->fetchAll();
    }
}

// Reviews Model
class Review extends BaseModel
{
    protected static $table = 'reviews';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO reviews (booking_id, user_id, worker_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['booking_id'],
            $data['user_id'],
            $data['worker_id'],
            $data['rating'],
            $data['review_text'] ?? null
        ]);
        return $db->lastInsertId();
    }

    public static function update($id, $data)
    {
        $db = Database::getConnection();
        $fields = [];
        $params = [];

        $allowedFields = ['rating', 'review_text', 'response_text'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $params[] = $id;

        $sql = "UPDATE reviews SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function findByWorker($workerId, $limit = null, $offset = 0)
    {
        $db = Database::getConnection();
        $sql = "SELECT r.*, u.name as reviewer_name FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.worker_id = ? 
                ORDER BY r.created_at DESC";

        if ($limit) {
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$workerId]);
        return $stmt->fetchAll();
    }

    public static function addResponse($id, $responseText)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE reviews SET response_text = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$responseText, $id]);
    }

    public static function getRecentReviews($limit = 10)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT r.*, u.name as reviewer_name, w.name as worker_name, c.name as category_name 
                             FROM reviews r 
                             JOIN users u ON r.user_id = u.id 
                             JOIN workers w ON r.worker_id = w.id 
                             JOIN categories c ON w.category_id = c.id 
                             ORDER BY r.created_at DESC 
                             LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}

// Worker Schedules Model
class WorkerSchedule extends BaseModel
{
    protected static $table = 'worker_schedules';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO worker_schedules (worker_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['worker_id'],
            $data['day_of_week'],
            $data['start_time'],
            $data['end_time'],
            $data['is_available'] ?? true
        ]);
        return $db->lastInsertId();
    }

    public static function update($id, $data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE worker_schedules SET start_time = ?, end_time = ?, is_available = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([
            $data['start_time'],
            $data['end_time'],
            $data['is_available'] ?? true,
            $id
        ]);
    }

    public static function findByWorker($workerId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM worker_schedules WHERE worker_id = ? ORDER BY day_of_week");
        $stmt->execute([$workerId]);
        return $stmt->fetchAll();
    }

    public static function setSchedule($workerId, $schedules)
    {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Delete existing schedules
            $stmt = $db->prepare("DELETE FROM worker_schedules WHERE worker_id = ?");
            $stmt->execute([$workerId]);

            // Insert new schedules
            foreach ($schedules as $schedule) {
                if (isset($schedule['day_of_week'])) {
                    self::create([
                        'worker_id' => $workerId,
                        'day_of_week' => $schedule['day_of_week'],
                        'start_time' => $schedule['start_time'],
                        'end_time' => $schedule['end_time'],
                        'is_available' => $schedule['is_available'] ?? true
                    ]);
                }
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollback();
            return false;
        }
    }
}

// Worker Time Off Model
class WorkerTimeOff extends BaseModel
{
    protected static $table = 'worker_time_off';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO worker_time_off (worker_id, start_date, end_date, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['worker_id'],
            $data['start_date'],
            $data['end_date'],
            $data['reason'] ?? null
        ]);
        return $db->lastInsertId();
    }

    public static function findByWorker($workerId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM worker_time_off WHERE worker_id = ? AND end_date >= CURDATE() ORDER BY start_date");
        $stmt->execute([$workerId]);
        return $stmt->fetchAll();
    }

    public static function isWorkerAvailable($workerId, $date)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM worker_time_off WHERE worker_id = ? AND ? BETWEEN start_date AND end_date");
        $stmt->execute([$workerId, $date]);
        $result = $stmt->fetch();
        return $result['count'] == 0;
    }
}

// Messages Model
class Message extends BaseModel
{
    protected static $table = 'messages';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO messages (booking_id, sender_type, sender_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['booking_id'],
            $data['sender_type'],
            $data['sender_id'],
            $data['message']
        ]);
        return $db->lastInsertId();
    }

    public static function findByBooking($bookingId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM messages WHERE booking_id = ? ORDER BY created_at ASC");
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }

    public static function markAsRead($id)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE messages SET is_read = TRUE WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function getUnreadCount($recipientType, $recipientId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM messages m 
                             JOIN bookings b ON m.booking_id = b.id 
                             WHERE m.is_read = FALSE AND m.sender_type != ? 
                             AND (
                                 (? = 'user' AND b.user_id = ?) OR 
                                 (? = 'worker' AND b.worker_id = ?)
                             )");
        $stmt->execute([$recipientType, $recipientType, $recipientId, $recipientType, $recipientId]);
        $result = $stmt->fetch();
        return $result['count'];
    }
}

// User Favorites Model
class UserFavorite extends BaseModel
{
    protected static $table = 'user_favorites';

    public static function create($data)
    {
        $db = Database::getConnection();
        try {
            $stmt = $db->prepare("INSERT INTO user_favorites (user_id, worker_id) VALUES (?, ?)");
            $stmt->execute([$data['user_id'], $data['worker_id']]);
            return $db->lastInsertId();
        } catch (PDOException $e) {
            // Handle duplicate entry
            return false;
        }
    }

    public static function remove($userId, $workerId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM user_favorites WHERE user_id = ? AND worker_id = ?");
        return $stmt->execute([$userId, $workerId]);
    }

    public static function findByUser($userId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT f.*, w.name as worker_name, w.location, w.rating, c.name as category_name 
                             FROM user_favorites f 
                             JOIN workers w ON f.worker_id = w.id 
                             JOIN categories c ON w.category_id = c.id 
                             WHERE f.user_id = ? 
                             ORDER BY f.created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function isFavorite($userId, $workerId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_favorites WHERE user_id = ? AND worker_id = ?");
        $stmt->execute([$userId, $workerId]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }
}

// Payments Model
class Payment extends BaseModel
{
    protected static $table = 'payments';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_status, transaction_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['booking_id'],
            $data['amount'],
            $data['payment_method'],
            $data['payment_status'] ?? 'pending',
            $data['transaction_id'] ?? null
        ]);
        return $db->lastInsertId();
    }

    public static function updateStatus($id, $status, $transactionId = null)
    {
        $db = Database::getConnection();
        $sql = "UPDATE payments SET payment_status = ?, updated_at = CURRENT_TIMESTAMP";
        $params = [$status];

        if ($status === 'completed') {
            $sql .= ", payment_date = CURRENT_TIMESTAMP";
        }

        if ($transactionId) {
            $sql .= ", transaction_id = ?";
            $params[] = $transactionId;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function findByBooking($bookingId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC");
        $stmt->execute([$bookingId]);
        return $stmt->fetchAll();
    }
}

// Worker Portfolio Model
class WorkerPortfolio extends BaseModel
{
    protected static $table = 'worker_portfolio';

    public static function create($data)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO worker_portfolio (worker_id, image_path, description, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['worker_id'],
            $data['image_path'],
            $data['description'] ?? null,
            $data['display_order'] ?? 0
        ]);
        return $db->lastInsertId();
    }

    public static function findByWorker($workerId)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM worker_portfolio WHERE worker_id = ? ORDER BY display_order, created_at");
        $stmt->execute([$workerId]);
        return $stmt->fetchAll();
    }

    public static function updateOrder($id, $order)
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE worker_portfolio SET display_order = ? WHERE id = ?");
        return $stmt->execute([$order, $id]);
    }
}

