<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure PHP handles everything in UTF-8 encoding
//mb_internal_encoding("UTF-8");
//mb_http_input("UTF-8");
//mb_http_output("UTF-8");

require_once 'includes/db.php';  // Ensure database connection is included

// Geocoding function to get lat, lng from address
function geocode_address($address) {
    $address = urlencode($address);  // Ensure the address is URL encoded

    // Check if address was properly encoded and is not empty
    if (empty($address)) {
        return [null, null]; // Return null coordinates if the address is empty
    }

    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . $address . "&key=" . $GOOGLE_MAPS_API_KEY;
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data['status'] == 'OK') {
        $lat = $data['results'][0]['geometry']['location']['lat'];
        $lng = $data['results'][0]['geometry']['location']['lng'];
        return [$lat, $lng];
    } else {
        return [null, null];  // Return null if geocoding fails
    }
}

// Function to check if a store already exists in the database
function store_exists($name, $address) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stores WHERE name = ? AND address = ?");
    $stmt->execute([$name, $address]);
    return $stmt->fetchColumn() > 0;  // Return true if the store exists
}

// Process the CSV file and insert data into the database
function process_csv($csv) {
    global $pdo;

	$first = true;

    foreach ($csv as $row) {
		if ($first) {
			$first = false;
			continue;
		}
		
        // Debugging: Print the whole row
//        echo '<pre>';
//        print_r($row); // This will display the raw CSV data for each row
//        echo '</pre>';

		if (count($row) < 6)
			continue;
        $name = $row[3];  // Store name
        $address = $row[4];  // Store address
        $tel = $row[5];  // Store telephone (can be skipped for now)

        // Check if the store already exists
        if (store_exists($name, $address)) {
            echo "Store already exists: $name at $address <br>\n";
            continue;  // Skip this row if store already exists
        }

        // Geocode the address to get latitude and longitude
        list($lat, $lng) = geocode_address($address);
		if ($lat == null) {
            echo "Unable to get latitude and longitude: $name at $address <br>\n";
            continue;
		}

        // Insert store into the 'stores' table
        $stmt = $pdo->prepare("INSERT INTO stores (name, address, lat, lng) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $address, $lat, $lng]);

        // Insert a default rating for the store (1 if not provided)
        $rating = isset($row[6]) ? (int) $row[6] : 1;  // Ensure default rating is 1 if not provided
        $rating = max(1, min(3, $rating));  // Ensure rating is between 1 and 3

        $storeId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO ratings (user_id, store_id, rating) VALUES (?, ?, ?)");
        $stmt->execute([1, $storeId, $rating]);  // Assume user ID = 1 (admin)
    
        echo "Added: $name at $address <br>\n";
	}
}
?>