<?php
class DatabaseService
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function getWorker($worker_id)
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

    public function getWorkerBookings($worker_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.*, u.name AS user_name 
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            WHERE b.worker_id = ? AND b.status IN ('pending', 'confirmed')
            ORDER BY b.preferred_date, b.preferred_time
        ");
        $stmt->execute([$worker_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories()
    {
        $stmt = $this->pdo->query("SELECT id, name FROM categories ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateWorkerAvailability($worker_id, $available)
    {
        $stmt = $this->pdo->prepare("UPDATE workers SET available = ? WHERE id = ?");
        return $stmt->execute([$available, $worker_id]);
    }

    public function updateBookingStatus($booking_id, $status)
    {
        $stmt = $this->pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $booking_id]);
    }

    public function updateWorkerProfile($worker_id, $data)
    {
        $stmt = $this->pdo->prepare("
            UPDATE workers SET 
                name = ?, 
                phone = ?, 
                category_id = ?, 
                location = ?, 
                introduction = ?, 
                experience = ?, 
                hourly_rate = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['name'],
            $data['phone'],
            $data['category_id'],
            $data['location'],
            $data['introduction'],
            $data['experience'],
            $data['hourly_rate'],
            $worker_id
        ]);
    }

    public function getWorkerStats($worker_id)
    {
        $stats = [];

        // Total bookings
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_bookings 
            FROM bookings 
            WHERE worker_id = ? AND status IN ('confirmed', 'completed')
        ");
        $stmt->execute([$worker_id]);
        $stats['total_bookings'] = $stmt->fetchColumn();

        // Response rate
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status != 'pending' THEN 1 ELSE 0 END) as responded
            FROM bookings 
            WHERE worker_id = ?
        ");
        $stmt->execute([$worker_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['response_rate'] = ($result['total_requests'] > 0)
            ? round(($result['responded'] / $result['total_requests']) * 100, 2)
            : 0;

        return $stats;
    }
}
?>