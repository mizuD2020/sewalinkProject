<?php
// mock_worker_data.php - Script to generate mock worker data with Nepali names

// Database connection
require_once 'db_config.php';

// Nepali first names
$nepaliFirstNames = [
    'Aarav',
    'Arjun',
    'Ayush',
    'Bijay',
    'Binod',
    'Deepak',
    'Dinesh',
    'Gaurav',
    'Hari',
    'Kiran',
    'Krishna',
    'Manish',
    'Nabin',
    'Niraj',
    'Prabesh',
    'Prakash',
    'Prashant',
    'Raj',
    'Rajesh',
    'Ram',
    'Ravi',
    'Rohan',
    'Sabin',
    'Sachin',
    'Sanjay',
    'Santosh',
    'Saroj',
    'Shiva',
    'Suman',
    'Sunil',
    'Suraj',
    'Sushant',
    'Umesh',
    'Yuvraj',
    'Aarati',
    'Anita',
    'Anjali',
    'Anu',
    'Archana',
    'Binita',
    'Deepa',
    'Dipika',
    'Gita',
    'Kabita',
    'Kamala',
    'Laxmi',
    'Manisha',
    'Maya',
    'Neha',
    'Nirmala',
    'Pooja',
    'Pratima',
    'Radha',
    'Rekha',
    'Sabina',
    'Sarita',
    'Sharmila',
    'Shrijana',
    'Sita',
    'Sunita',
    'Sushmita',
];

// Nepali last names
$nepaliLastNames = [
    'Adhikari',
    'Aryal',
    'Basnet',
    'Bhatta',
    'Bhattarai',
    'Bista',
    'Chhetri',
    'Dahal',
    'Dhakal',
    'Gautam',
    'Ghimire',
    'Gurung',
    'Karki',
    'K.C.',
    'Khadka',
    'Koirala',
    'Lama',
    'Limbu',
    'Magar',
    'Maharjan',
    'Neupane',
    'Paudel',
    'Pradhan',
    'Rai',
    'Rana',
    'Regmi',
    'Sharma',
    'Shrestha',
    'Subedi',
    'Tamang',
    'Thapa',
    'Yadav',
];

// Locations in Nepal
$nepaliLocations = [
    'Kathmandu',
    'Pokhara',
    'Lalitpur',
    'Bhaktapur',
    'Biratnagar',
    'Birgunj',
    'Butwal',
    'Dharan',
    'Janakpur',
    'Hetauda',
    'Nepalgunj',
    'Itahari',
    'Dhangadhi',
    'Bharatpur',
    'Kirtipur',
    'Tansen',
    'Ghorahi',
    'Tulsipur',
];

// Experience levels
$experienceLevels = ['1-2 years', '2-3 years', '3-5 years', '5-7 years', '7-10 years', '10+ years'];

// Introduction templates (romanized Nepali with consistent placeholder count)
$introTemplates = [
    // Templates with 4 placeholders: %s = name, %s = location, %s = category, %s = experience
    'Mero naam %s ho ra ma %s kshetrama %s ko anubhav bhayeko ek kushal %s hu. Malai gunasthariya kaam garna ra grahakharu lai santushta parna man parcha.',

    'Namaste! Ma %s, %s ma baschu ra %s ma bisheshagyata rakhchu. Masanga %s ko anubhav cha ra malai mero kam prati junun cha.',

    'Mero naam %s ho. Ma ek %s hu ra %s ma baschu. Maile %s samaya dekhi yas kshetrama kaam gariraheko chu ra grahakharu lai utkrishta sewa pradan garna pratibadhha chu.',

    'Dhanyabad tapai ko profile herna ko lagi! Ma %s, %s ma basne ek %s hu. Mero %s ko anubhav cha ra ma ramro customer sewa ra mero kaam ko gunasthar ma biswas garchu.',

    'Ma %s, ek %s hu jasle %s kshetrama basera kaam garcha. Mero %s ko anubhav cha ra ma harpal naya chij sikirakheko chu. Tapai ko kaam lai ma ramro ra samaya ma pura garne wada garchu.',

    'Namaste, mero naam %s ho. Ma %s ma baschu ra %s ko rupma %s ko anubhav cha. Ma sadhai mero grahaklai samaya mai ra budget bhitra kaam sakera khusi parna chahanchhu.',

    'Ma %s, %s bata aaeko ek %s hu. %s ko anubhav sanga, ma kunai pani chunauti ko saamna garna tayar chu. Mero kaam ko bare ma kei prashna cha bhane, malai sampark garna nadwichkiunu hola.',
];

// Function to generate a random Nepali phone number (starts with 98)
function generateNepaliPhone()
{
    return '98' .
        mt_rand(0, 9) .
        mt_rand(0, 9) .
        mt_rand(0, 9) .
        mt_rand(0, 9) .
        mt_rand(0, 9) .
        mt_rand(0, 9) .
        mt_rand(0, 9);
}

// Function to generate a random email from name
function generateEmail($name)
{
    $name = strtolower(str_replace(' ', '', $name));
    $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
    $domain = $domains[array_rand($domains)];
    return $name . mt_rand(1, 999) . '@' . $domain;
}

// Function to generate introduction
function generateIntroduction($name, $categoryName, $experience, $location)
{
    global $introTemplates;
    $template = $introTemplates[array_rand($introTemplates)];
    return sprintf($template, $name, $location, $categoryName, $experience);
}

// Function to generate a realistic random rating between 3.0 and 5.0
function generateRating()
{
    // Generate a rating between 3.0 and 5.0 with one decimal place
    // Workers typically have decent ratings, so we start from 3.0
    return round(mt_rand(30, 50) / 10, 1);
}

// Get categories from the database
try {
    $stmt = $pdo->query('SELECT id, name FROM categories');
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching categories: ' . $e->getMessage());
}

// Number of workers to generate
$numWorkers = 50;

// Generate and insert workers
echo "Generating $numWorkers workers with Nepali names...\n";

for ($i = 0; $i < $numWorkers; $i++) {
    $firstName = $nepaliFirstNames[array_rand($nepaliFirstNames)];
    $lastName = $nepaliLastNames[array_rand($nepaliLastNames)];
    $name = $firstName . ' ' . $lastName;

    $category = $categories[array_rand($categories)];
    $categoryId = $category['id'];
    $categoryName = $category['name'];

    $location = $nepaliLocations[array_rand($nepaliLocations)];
    $experience = $experienceLevels[array_rand($experienceLevels)];
    $email = generateEmail($name);
    $phone = generateNepaliPhone();
    $introduction = generateIntroduction($name, $categoryName, $experience, $location);
    $hourlyRate = mt_rand(500, 2000); // In Nepali Rupees (NPR)
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT); // Default password for mock data
    $rating = generateRating(); // Generate a random rating
    $reviewsCount = mt_rand(0, 50); // Random number of reviews
    $totalBookings = mt_rand($reviewsCount, $reviewsCount + 30); // Total bookings is at least as many as reviews
    $responseRate = mt_rand(70, 100); // Response rate percentage

    try {
        $stmt = $pdo->prepare("
            INSERT INTO workers (
                name, email, phone, password_hash, category_id, location,
                introduction, experience, hourly_rate, available, rating,
                reviews_count, total_bookings, response_rate
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $name,
            $email,
            $phone,
            $passwordHash,
            $categoryId,
            $location,
            $introduction,
            $experience,
            $hourlyRate,
            $rating,
            $reviewsCount,
            $totalBookings,
            $responseRate,
        ]);

        echo "Created worker: $name ($categoryName) from $location with rating $rating\n";
    } catch (PDOException $e) {
        echo "Error creating worker $name: " . $e->getMessage() . "\n";
    }
}

echo "Done! Created $numWorkers workers with Nepali names and introductions.\n";
?>
