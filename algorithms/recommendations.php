<?php
/**
 * Recommendation algorithms for SewaLink platform
 * This file implements various recommendation algorithms including cosine similarity
 * to suggest workers to users based on their preferences and history.
 */

/**
 * Calculate cosine similarity between two vectors
 * @param array $vectorA First vector
 * @param array $vectorB Second vector
 * @return float Cosine similarity value between 0 and 1
 */
function calculateCosineSimilarity($vectorA, $vectorB)
{
    // If either vector is empty, return 0
    if (empty($vectorA) || empty($vectorB)) {
        return 0;
    }

    // Calculate dot product
    $dotProduct = 0;
    foreach ($vectorA as $key => $value) {
        if (isset($vectorB[$key])) {
            $dotProduct += $value * $vectorB[$key];
        }
    }

    // Calculate magnitudes
    $magnitudeA = sqrt(
        array_sum(
            array_map(function ($x) {
                return $x * $x;
            }, $vectorA),
        ),
    );
    $magnitudeB = sqrt(
        array_sum(
            array_map(function ($x) {
                return $x * $x;
            }, $vectorB),
        ),
    );

    // Avoid division by zero
    if ($magnitudeA == 0 || $magnitudeB == 0) {
        return 0;
    }

    // Return cosine similarity
    return $dotProduct / ($magnitudeA * $magnitudeB);
}

/**
 * Get recommended workers for a user based on their booking history
 * @param PDO $pdo Database connection
 * @param int $userId User ID to get recommendations for
 * @param int $limit Maximum number of recommendations to return
 * @return array Array of recommended worker data
 */
function getRecommendedWorkers($pdo, $userId, $limit = 3)
{
    if (!$userId) {
        return [];
    }

    $userVector = buildUserPreferenceVector($pdo, $userId);

    if (empty($userVector)) {
        return getPopularWorkers($pdo, $limit);
    }

    // Get all available workers
    $stmt = $pdo->query("
        SELECT w.*, c.name AS category_name
        FROM workers w
        JOIN categories c ON w.category_id = c.id
        WHERE w.available = 1
    ");
    $allWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate similarity scores for each worker
    $workerScores = [];
    foreach ($allWorkers as $worker) {
        $workerVector = buildWorkerVector($worker);
        $similarity = calculateCosineSimilarity($userVector, $workerVector);

        // Add additional weighting factors
        $ratingBoost = ($worker['rating'] / 5) * 0.3; // 30% weight to rating
        $totalScore = $similarity + $ratingBoost;

        $workerScores[$worker['id']] = [
            'worker' => $worker,
            'score' => $totalScore,
        ];
    }

    // Sort by score (descending)
    usort($workerScores, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // Extract just the worker data
    $recommendations = [];
    foreach (array_slice($workerScores, 0, $limit) as $item) {
        $recommendations[] = $item['worker'];
    }

    return $recommendations;
}

/**
 * Get popular workers as fallback recommendations
 * @param PDO $pdo Database connection
 * @param int $limit Maximum number of workers to return
 * @return array Array of popular worker data
 */
function getPopularWorkers($pdo, $limit)
{
    $sql = "SELECT w.*, c.name AS category_name
    FROM workers w
    JOIN categories c ON w.category_id = c.id
    WHERE w.available = 1
    ORDER BY w.rating DESC, w.reviews_count DESC
    LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    // Log the number of rows returned
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $results;
}

/**
 * Build a feature vector representing user preferences
 * @param PDO $pdo Database connection
 * @param int $userId The user ID
 * @return array Feature vector
 */
function buildUserPreferenceVector($pdo, $userId)
{
    $vector = [];

    // Get user's booking history
    $stmt = $pdo->prepare("
        SELECT w.category_id,
               AVG(w.hourly_rate) as hourly_rate,
               w.location,
               COUNT(*) as booking_count,
               AVG(COALESCE(r.rating, 0)) as avg_rating
        FROM bookings b
        JOIN workers w ON b.worker_id = w.id
        LEFT JOIN reviews r ON r.booking_id = b.id
        WHERE b.user_id = ? AND b.status IN ('completed', 'confirmed')
        GROUP BY w.category_id, w.location
    ");
    $stmt->execute([$userId]);
    $bookingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($bookingHistory)) {
        return $vector;
    }

    // Calculate total bookings
    $totalBookings = array_sum(array_column($bookingHistory, 'booking_count'));

    // Add category preferences
    foreach ($bookingHistory as $history) {
        // Category preference
        $categoryKey = 'category_' . $history['category_id'];
        $vector[$categoryKey] =
            ($history['booking_count'] / $totalBookings) * (1 + 0.2 * $history['avg_rating']); // Rating boosts category preference

        // Location preference
        $locationKey = 'location_' . md5($history['location']);
        $vector[$locationKey] = $history['booking_count'] / $totalBookings;

        // Price range preference (bucketized)
        $hourlyRate = floatval($history['hourly_rate']);
        $priceRange = getPriceRangeBucket($hourlyRate);
        $priceKey = 'price_' . $priceRange;
        if (!isset($vector[$priceKey])) {
            $vector[$priceKey] = 0;
        }
        $vector[$priceKey] += $history['booking_count'] / $totalBookings;
    }

    return $vector;
}

/**
 * Build a feature vector for a worker
 * @param array $worker Worker data
 * @return array Feature vector
 */
function buildWorkerVector($worker)
{
    $vector = [];

    // Category
    $categoryKey = 'category_' . $worker['category_id'];
    $vector[$categoryKey] = 1.0;

    // Location
    $locationKey = 'location_' . md5($worker['location']);
    $vector[$locationKey] = 1.0;

    // Price range
    $priceRange = getPriceRangeBucket($worker['hourly_rate']);
    $priceKey = 'price_' . $priceRange;
    $vector[$priceKey] = 1.0;

    return $vector;
}

/**
 * Get a price range bucket for a given hourly rate
 * @param float $hourlyRate The hourly rate
 * @return string Price range bucket identifier
 */
function getPriceRangeBucket($hourlyRate)
{
    if ($hourlyRate < 500) {
        return 'low';
    }
    if ($hourlyRate < 1000) {
        return 'medium';
    }
    if ($hourlyRate < 1500) {
        return 'high';
    }
    return 'premium';
}

/**
 * Get recommendations based on similar users (collaborative filtering)
 * @param PDO $pdo Database connection
 * @param int $userId User ID to get recommendations for
 * @param int $limit Maximum number of recommendations to return
 * @return array Array of recommended worker data
 */
function getCollaborativeRecommendations($pdo, $userId, $limit = 3)
{
    // This is a simple collaborative filtering implementation
    // Get users with similar booking patterns
    $stmt = $pdo->prepare("
        SELECT u2.id as similar_user_id
        FROM bookings b1
        JOIN bookings b2 ON b1.worker_id = b2.worker_id
        JOIN users u2 ON b2.user_id = u2.id
        WHERE b1.user_id = ? AND b2.user_id != ?
        GROUP BY u2.id
        ORDER BY COUNT(*) DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    $similarUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($similarUsers)) {
        return [];
    }

    // Get workers booked by similar users but not by this user
    $placeholders = implode(',', array_fill(0, count($similarUsers), '?'));
    $params = array_merge($similarUsers, [$userId]);

    $stmt = $pdo->prepare("
        SELECT w.*, c.name AS category_name, COUNT(*) as booking_count
        FROM bookings b
        JOIN workers w ON b.worker_id = w.id
        JOIN categories c ON w.category_id = c.id
        WHERE b.user_id IN ({$placeholders})
        AND w.id NOT IN (
            SELECT worker_id FROM bookings WHERE user_id = ?
        )
        AND w.available = 1
        GROUP BY w.id
        ORDER BY booking_count DESC, w.rating DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
