<?php
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["imageData"], $_POST["latitude"], $_POST["longitude"])) {
    
    $imageData = $_POST["imageData"];
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];

    $deviceInfo = getDevice();
    $weatherInfo = getWeather($latitude, $longitude); 

    // Fetch individual data
    $address = getAddress($latitude, $longitude)['address'];
    $placeId = getAddress($latitude, $longitude)['placeId'];
    $boundingBox = getAddress($latitude, $longitude)['boundingBox'];
    $weather = getWeather($latitude, $longitude)['weather'];
    $temp = getWeather($latitude, $longitude)['temp'];

    $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $imageData = base64_decode($imageData);
    $image = imagecreatefromstring($imageData);

    $font_color = imagecolorallocate($image, 255, 255, 255);
    $x_text = 10; 
    $y_text = 30; 

    $font_file = 'assets/arial.ttf';
    $font_size = 10;

    // Wrap the address to 35 characters per line
    $wrapped_address = wordwrap($address, 35, "\n", true); 
    $wrapped_address_lines = explode("\n", $wrapped_address);

    // Render the text on the image
    imagettftext($image, $font_size, 0, $x_text, $y_text, $font_color, $font_file, "Lat: $latitude, Long: $longitude");

    $y_text += 16;
    imagettftext($image, $font_size, 0, $x_text, $y_text, $font_color, $font_file, "Weather: $weather, Temperature: $temp °C");

    foreach ($wrapped_address_lines as $line_number => $line_text) {
        $y_text += 16;
        imagettftext($image, $font_size, 0, $x_text, $y_text, $font_color, $font_file, $line_text);
    }

    $logo_img = imagecreatefrompng('assets/logo.png');
    $logo_imgX = imagesx($image) - imagesx($logo_img) - 10;
    $logo_imgY = imagesy($image) - imagesy($logo_img) - 10;

    // Render the icon on the image
    imagecopy($image, $logo_img, $logo_imgX, $logo_imgY, 0, 0, imagesx($logo_img), imagesy($logo_img));

    $filedirectory = __DIR__ . '/gallery/';
    $filename = time() . ".jpeg";
    imagejpeg($image, $filedirectory . $filename, 100);

    // Save in the database 
    insertDB($conn, $latitude, $longitude, $filename,  $address, $placeId, $boundingBox, $weatherInfo, $deviceInfo);

    // Output the geotagged image file to the browser
    header('Content-Type: image/jpeg');
    readfile($filedirectory . $filename);

    // Free up memory by destroying the image resource
    imagedestroy($image);
    imagedestroy($logo_img);
}

function insertDb($conn, $latitude, $longitude, $filename, $address, $placeId, $boundingBox, $weatherInfo, $deviceInfo){

    // Create an associative array with the variables
    $jsondata = array(
        'captured_img' => $filename,
        'lat' => $latitude,
        'long' => $longitude,
        'address' => $address,
        'placeId' => $placeId,
        'boundingBox' => $boundingBox,
        'weatherInfo' => $weatherInfo,
        'deviceInfo' => $deviceInfo
    );

    // Convert the array to a JSON object
    $data = json_encode($jsondata);

    $sql = "INSERT INTO pwa_geotag_db (geolocation) VALUES (?);";
    $stmt = mysqli_stmt_init($conn);
    mysqli_stmt_prepare($stmt, $sql);
    mysqli_stmt_bind_param($stmt, "s", $data);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function getWeather($latitude, $longitude){
    
    $apiUrl = "https://api.openweathermap.org/data/2.5/weather?lat=$latitude&lon=$longitude&appid=YOUR_API_KEY";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    $weather = $data['weather'][0]['main'];
    $tempKelvin = $data['main']['temp'];
    $temp = number_format($tempKelvin - 273.15, 2);

    return [
        "weather" => $weather,
        "temp" => $temp
    ];
}

function getAddress($latitude, $longitude){

    $apiUrl = "https://geocode.maps.co/reverse?lat={$latitude}&lon={$longitude}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    
    $address = $data['display_name'];
    $placeId = $data['place_id'];
    $boundingBox = $data['boundingbox'];
    
    return [
        "address" => $address,
        "placeId" => $placeId,
        "boundingBox" => $boundingBox
    ];
}

function getDevice(){

    $browser = $_SERVER['HTTP_USER_AGENT'];
    $ip = getIp();

    $deviceArr = array(
        'browser' => $browser,
        'ip' => $ip
    );

    return $deviceArr;
}

function getIp(){
    //whether ip is from share internet
    if (!empty($_SERVER['HTTP_CLIENT_IP'])){
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    }
    //whether ip is from proxy
    elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    //whether ip is from remote address
    else{
        $ip_address = $_SERVER['REMOTE_ADDR'];
    }
    return $ip_address;
}
?>