<?php
include_once 'vendor/autoload.php';
include_once 'config.php';
// require 'Mustache/Autoloader.php';
// Mustache_Autoloader::register();


use TourCMS\Utils\TourCMS as TourCMS;
use function PHPSTORM_META\type;
use PhpParser\Node\Expr\Cast;
use onboarding\services\RedisService;
use PhpParser\Node\Expr\Print_;

$marketplaceIdForOperator = 0;
// $apiKey = "6f24ac3ac4ed";

// $marketplaceId = 33495;
// $apiKey = "52fc7e3d2ef7";

$re = new RedisService($REDIS_HOST, $REDIS_PORT, $REDIS_PASSWORD);

$marketplaceId = 12345;
$agentApiKey = "ccadca970eea";

$timeout = 0;

// $channelId = 142;

// $params = '';

$baseUrl = 'http://api.tourcms.local';

$cacheName = 'channelList';

// instancia de la clase TourCMS para marketplace agent
$tourcms = new TourCMS($marketplaceId, $agentApiKey, 'simplexml', $timeout);

$tourcms->set_base_url($baseUrl);

$showChannels = [];

$htmlTemplate = '';

$expirationTime = time() + 600;


function checkStorageInCache($re, $cacheName)
{
    if ($re->existKey($cacheName)) {
        return true;
    } else {
        return false;
    }
};
function firstLoadCache($re, $cacheName, $expirationTime, $tourcms)
{
    $showChannels = formatInfoChannelJSONFromXML($tourcms->list_channels());
    $re->storeItemInRedis($cacheName, json_encode($showChannels), RedisService::REDIS_TYPE_STRING);
    $re->expireAt($cacheName, $expirationTime);
    foreach ($showChannels as $channel) {
        $re->storeItemInRedis('API_KEY_' . $channel['channelId'], $channel['channelKey'], RedisService::REDIS_TYPE_STRING);
        $re->expireAt('API_KEY_' . $channel['channelId'], $expirationTime);
    }
    return $showChannels;
};

function procedurePrintChannels($showChannels)
{
    echo ('<div>
    <div class="container text-center">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4">');

    foreach ($showChannels as $channel) {
        echo ('<div class="card" style="width: 18rem;">
            <img src=' . $channel['channelLogo'] . ' class="card-img-top" alt="...">
            <div class="card-body">
                <h5 class="card-title">Nombre: ' . $channel['channelName'] . '></h5>
                <p class="card-text">Id:' . $channel['channelId'] . '</p>
                <p class="card-text">Description ' . $channel['channelDesc'] . '</p>
                <form action="index.php" method="post">
                    <input type="hidden" id="postChannelId" name="postChannelId" value=' . $channel['channelId'] . ' />
                    <button type=submit class="btn btn-primary">Visit Tours</button>
                </form>
            </div>
        </div>');
    }

    echo ('</div>
     </div>');
};

function procedurePrintTours($showTours)
{
    echo ('<div>
    <div class="container text-center">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4">');
    foreach ($showTours as $tour) {
        echo ('<div class="card" style="width: 18rem;">
            <div class="card-body">
                <h5 class="card-title">Nombre: ' . $tour['tourName'] . '></h5>
                <p class="card-text">Id:' . $tour['tourId'] . '</p>
                <p class="card-text">Code ' . $tour['tourCode'] . '</p>
                <form action="index.php" method="post">
                <input type="hidden" id="postTourId" name="postTourId" value=' . $tour['tourId'] . ' />
                <input type="hidden" id="postChannelId" name="postChannelId" value=' . $tour['channelId'] . ' />
                <button type=submit class="btn btn-primary">More Info</button>
            </form>

            </div>
        </div>');
    }
    echo ('</div>
        </div>');
};

function procedurePrintSingleTour($singleTour)
{
    if (is_string($singleTour)) {
        $singleTour = json_decode($singleTour, true);
    }
    echo ('<div class="container text-center">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4"><div class="card" style="width: 18rem;">
            <div class="card-body">
                <h5 class="card-title">Channel ID: ' . $singleTour['tour']['channel_id'] . '></h5>
                <p class="card-text">Tour ID:' . $singleTour['tour']['tour_id'] . '</p>
                <p class="card-text">Tour Name ' .  $singleTour['tour']['tour_name'] . '</p>
                <p class="card-text">Tour Code ' .  $singleTour['tour']['tour_code'] . '</p>
                <p class="card-text">Language Spoken ' .  $singleTour['tour']['languages_spoken'] . '</p>
                <p class="card-text">Geocode Start ' .  $singleTour['tour']['geocode_start'] . '</p>
                <p class="card-text">Geocode End ' .  $singleTour['tour']['geocode_end'] . '</p>
                <form action="index.php" method="post">
                <label for="">Check avaliability:</label>
                <input type="hidden" id="postTourId" name="postTourId" value=' . $singleTour['tour']['tour_id'] . ' />
                <input type="hidden" id="postChannelId" name="postChannelId" value=' . $singleTour['tour']['channel_id'] . ' />
                <input type="date" id="postCheckTourDate" name="postCheckTourDate" required>
                <input type="submit">
            </form>
              
            </div>
        </div> </div>  
    </div>');
};


function procedurePrintBooking($checkTourXML)
{
    // $checkTourXML = json_decode(json_encode($checkTourXML), true);
    $auxTourId = (int)$checkTourXML->tour_id;
    $auxChannelId = (int)$checkTourXML->channel_id;
    // if(is_array($checkTourXML['available_components'])){
    //     $aux = $checkTourXML['available_components']['component'];
    // }
    // else{
    //     $aux = $checkTourXML['available_components'];
    // }

    if (isset($checkTourXML->available_components->component)) {
        foreach ($checkTourXML->available_components->component as $component) {
            echo ('<div class="container text-center">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4"><div class="card" style="width: 18rem;">
                    <div class="card-body">');
            echo (
                '<p class="card-text">Starting Date: ' .  $component['start_date'] . '</p>
                        <p class="card-text">Ending Date: ' .  $component['end_date'] . '</p>
                        <p class="card-text">Price: ' .  $component['total_price'] . $component['sale_currency'] . '</p>'
            );
            echo ('
            <form action="index.php" method="post">
                    <input type="hidden" id="postTourId" name="postTourId" value=' . $auxTourId . ' />
                    <input type="hidden" id="postChannelId" name="postChannelId" value=' . $auxChannelId . ' />
                    <input type="hidden" id="postTemporalBooking" name="postTemporalBooking" value=' . (string)$component->component_key . ' />
                    <input type="radio" id="operator" name="postRequestBookingAs" value="operator">
                    <label for="operator">Operator</label><br>
                    <input type="radio" id="agent" name="postRequestBookingAs" value="agent" checked="checked">
                    <label for="agent">Agent</label><br>
                    <label for= operatorAsAgent>Operator as Agent</label>
                    <input type="radio" id="operatorAsAgent" name="postRequestBookingAs" value="operatorAsAgent"><br><br>
                    <input type=submit value="Create Temporal Booking">
                </form>
                    </div>
                </div> </div>  
            </div>');
        };
    }
    
}


function procedurePrintCommit($temporalBooking)
{
    $temporalBooking = json_decode(json_encode($temporalBooking), true);
    print_r($temporalBooking);
    echo ('<div class="container text-center">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-4"><div class="card" style="width: 18rem;">
                <div class="card-body">');
    echo ('
        <form action="index.php" method="post">
                <input type="hidden" id="postTourId" name="postTourId" value=' . $temporalBooking['components']['component']['product_id'] . ' />
                <input type="hidden" id="postChannelId" name="postChannelId" value=' . $temporalBooking['channel_id'] . ' />
                <input type="hidden" id="postCommitBookingId" name="postCommitBookingId" value=' . $temporalBooking['booking_id'] . ' />
                <input type="hidden" id="operator" name="postRequestBookingAs" value='.$_POST['postRequestBookingAs'].'>
                <input type=submit value="Commit Booking">
            </form>
                </div>
            </div> </div>  
        </div>');
}


function createTemporalBooking($tourcmsAmbiguous, $channel, $component_key, $booking_key = null)
{
    $booking = new SimpleXMLElement('<booking />');

    // Append the total customers, we'll add their details on below
    $booking->addChild('total_customers', '1');

    // If we're calling the API as a Tour Operator we need to add a Booking Key
    // otherwise skip this
    // See "Getting a new booking key" for info
    if($booking_key != null){
        $booking->addChild('booking_key', $booking_key);

    }

    // Append a container for the components to be booked
    $components = $booking->addChild('components');

    // Add a component node for each item to add to the booking
    $component = $components->addChild('component');

    // "Component key" obtained via call to "Check availability"
    $component->addChild('component_key', $component_key);

    // Append a container for the customer recrds
    $customers = $booking->addChild('customers');

    // Optionally append the customer details
    // Either add their details (as here)
    // OR an existing customer_id
    // OR leave blank and TourCMS will create a blank customer
    $customer = $customers->addChild('customer');
    $customer->addChild('firstname', 'juan carlos');
    $customer->addChild('surname', 'rg');
    $customer->addChild('email', 'Email');
    $customer->addChild('gender', 'm');

    // Query the TourCMS API, creating the booking

    error_log("Creating booking: ". $booking->asXML());
    error_log(("CHANNEL: ". $channel));

    $result = $tourcmsAmbiguous->start_new_booking($booking, $channel);

    $bkg = $result->booking;
    return $bkg;
}

function requestBookingKey($tourcms, $channel)
{
    $url_data  = new SimpleXMLElement('<booking />');
    $url_data ->addChild('response_url', 'http://localhost/onboarding/tourcms-back/index.php');
    $result = $tourcms->get_booking_redirect_url($url_data, $channel);
    $redirect_url = $result->url->redirect_url;
    $redirected = getFinalUrl($redirect_url);
    $booking_key = getQueryParam($redirected, 'booking_key');
    print_r($result);

    return $booking_key;
}

function searchAgent($tourcms, $marketplaceId, $channel){
    $params = "agent_marketplace_id=".$marketplaceId;

    $result = $tourcms->search_agents($params, $channel);


    return json_decode(json_encode($result), true)['agent']['booking_key'];
}
////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////

function getFinalUrl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true); // No necesitamos el cuerpo de la respuesta

    // Ejecutar la solicitud
    curl_exec($ch);

    // Obtener la URL final después de seguir los redireccionamientos
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    curl_close($ch);

    return $finalUrl;
}

function getQueryParam($url, $param) {
    // Analizar la URL final y obtener la parte de la query string
    $parsed_url = parse_url($url);
    
    // Verificar si la URL tiene una query string
    if (isset($parsed_url['query'])) {
        // Analizar la query string en un array
        parse_str($parsed_url['query'], $query_params);
        
        // Verificar si la variable que buscamos está definida en el array de parámetros
        if (isset($query_params[$param])) {
            return $query_params[$param];
        }
    }
    return null;
}

////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////

function commitBooking($tourcms, $channel, $booking_id)
{
    $booking = new SimpleXMLElement('<booking />');
    $booking->addChild('booking_id', $booking_id);

    $result = $tourcms->commit_new_booking($booking, $channel);
    return $result;
}

function formatInfoChannelJSONFromXML($channelsXML)
{
    $listaChannels = new ArrayObject();
    foreach ($channelsXML->channel as $singleChannel) {
        $jsonInfoCanal = [
            'channelId' => (string)$singleChannel->channel_id,
            'channelName' => (string)$singleChannel->channel_name,
            'channelLogo' => (string)$singleChannel->logo_url,
            'channelDesc' => (string)$singleChannel->long_desc,
            'channelKey' => (string)$singleChannel->internal_api_key
        ];
        $listaChannels[(string)$singleChannel->channel_id] = $jsonInfoCanal;
    }
    return $listaChannels;
};

function formatInfoTourJSONFromXML($toursXML)
{
    $listaTours = new ArrayObject();
    foreach ($toursXML->tour as $singleTour) {
        $jsonInfoTour = [
            'channelId' => (string)$singleTour->channel_id,
            'accountId' => (string)$singleTour->account_id,
            'tourId' => (string)$singleTour->tour_id,
            'tourName' => (string)$singleTour->tour_name,
            'tourCode' => (string)$singleTour->tour_code,
        ];
        $listaTours[(string)$singleTour->tour_id] = $jsonInfoTour;
    }
    return $listaTours;
};


function formatSingleTourJSONFromXML($singleTourXML)
{
    $listaInfoTour = [
        'channelId' => (string)$singleTourXML->channel_id,
        'accountId' => (string)$singleTourXML->account_id,
        'tourId' => (string)$singleTourXML->tour_id,
        'tourName' => (string)$singleTourXML->tour_name,
        'tourCode' => (string)$singleTourXML->tour_code,
    ];
    return $listaInfoTour;
};

if (isset($_POST['postTourId']) || isset($_SESSION['postTourId'])) {
    $htmlTemplate = 'singleTour';
    $tourcmsOperator = new TourCMS($marketplaceIdForOperator, $re->getItemFromRedis('API_KEY_' . $_POST['postChannelId'], RedisService::REDIS_TYPE_STRING), 'simplexml');
    $tourcmsOperator->set_base_url($baseUrl);
    if (!$re->existKey($cacheName)) {
        $showChannels = firstLoadCache($re, $cacheName, $expirationTime, $tourcms);
    }
    if ($re->existKey('SINGLE_TOUR_' . $_POST['postChannelId'] . '_' . $_POST['postTourId'])) {
        $singleTour = json_decode($re->getItemFromRedis('SINGLE_TOUR_' . $_POST['postChannelId'] . '_' . $_POST['postTourId'], RedisService::REDIS_TYPE_STRING), true);
    } else { 
        $singleTour = json_encode($tourcmsOperator->show_tour($_POST['postTourId'], $_POST['postChannelId']));
        $re->storeItemInRedis('SINGLE_TOUR_' . $_POST['postChannelId'] . '_' . $_POST['postTourId'], $singleTour, RedisService::REDIS_TYPE_STRING);
        $re->expireAt('SINGLE_TOUR_' . $_POST['postChannelId'] . '_' . $_POST['postTourId'], $expirationTime);
    }

    if (isset($_POST['postCheckTourDate']) ) {
        $htmlTemplate = 'checkTourDate';
        print "TOUR ES: " . (int)$_POST['postTourId'] . " CHANNEL ES: " . (int)$_POST['postChannelId'] . " DATE ES: " . $_POST['postCheckTourDate'] . "<br>";
        $checkTourXML = $tourcmsOperator->check_tour_availability('date='.$_POST['postCheckTourDate'] . '&r1=1', (int)$_POST['postTourId'], (int)$_POST['postChannelId']);
    }
    if (isset($_POST['postTemporalBooking']) ) {
        $htmlTemplate = 'temporalBooking';
        if($_POST['postRequestBookingAs']=='agent'){
            $temporalBooking = createTemporalBooking($tourcms, $_POST['postChannelId'], $_POST['postTemporalBooking']);
        }
        elseif($_POST['postRequestBookingAs']=='operator'){
            if (!$re->existKey('BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'])) {
                $booking_key = requestBookingKey($tourcmsOperator, $_POST['postChannelId']);
                $re->storeItemInRedis('BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'], $booking_key, RedisService::REDIS_TYPE_STRING);
                $re->expireAt('BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'], time()+24 * 60 * 60);
            }
            else{
                $booking_key = $re->getItemFromRedis('BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'], RedisService::REDIS_TYPE_STRING);
            }

            $temporalBooking = createTemporalBooking($tourcmsOperator, $_POST['postChannelId'], $_POST['postTemporalBooking'], $booking_key);
        }
        else{
            if (!$re->existKey('AGENT_'.$marketplaceId.'BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'])) {
                $agentBookingKey = searchAgent($tourcmsOperator, $marketplaceId, $_POST['postChannelId']);
                $re->storeItemInRedis('AGENT_'.$marketplaceId .'_BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'], $agentBookingKey, RedisService::REDIS_TYPE_STRING);
                $re->expireAt('AGENT_'.$marketplaceId .'_BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'], time()+24 * 60 * 60);
                }
            else{
                $agentBookingKey = $re->getItemFromRedis('AGENT_'.$marketplaceId.'BOOKING_KEY_' . $_POST['postChannelId']. '_'.$_POST['postTourId'], RedisService::REDIS_TYPE_STRING);
            }
            
            $temporalBooking = createTemporalBooking($tourcmsOperator, $_POST['postChannelId'], $_POST['postTemporalBooking'], $agentBookingKey);
        }
    }

    if (isset($_POST['postCommitBookingId'])) {
        $htmlTemplate = 'commitBooking';
        if($_POST['postRequestBookingAs']=='agent'){
            $commitBooking = commitBooking($tourcms, $_POST['postChannelId'], $_POST['postCommitBookingId']);
        }
        else{
            $tourcmsOperator = new TourCMS($marketplaceIdForOperator, $re->getItemFromRedis('API_KEY_' . $_POST['postChannelId'], RedisService::REDIS_TYPE_STRING), 'simplexml');
            $tourcmsOperator->set_base_url($baseUrl);
            $commitBooking = commitBooking($tourcmsOperator, $_POST['postChannelId'], $_POST['postCommitBookingId']);
        }

    }

    
} elseif (isset($_POST['postChannelId'])) {
    $htmlTemplate = 'tours';
    if (!$re->existKey($cacheName)) {
        $showChannels = firstLoadCache($re, $cacheName, $expirationTime, $tourcms);
    } else {
        $showChannels = json_decode($re->getItemFromRedis($cacheName, RedisService::REDIS_TYPE_STRING), true);
    }
    /// instancia de la clase TourCMS para operator
    $tourcmsOperator = new TourCMS($marketplaceIdForOperator, $re->getItemFromRedis('API_KEY_' . $_POST['postChannelId'], RedisService::REDIS_TYPE_STRING), 'simplexml');
    $tourcmsOperator->set_base_url($baseUrl);
    if ($re->existKey('TOURS_' . $_POST['postChannelId'])) {
        $showTours = json_decode($re->getItemFromRedis('TOURS_' . $_POST['postChannelId'], RedisService::REDIS_TYPE_STRING), true);
    } else {
        $showTours = formatInfoTourJSONFromXML($tourcmsOperator->list_tours($_POST['postChannelId']));
        $re->storeItemInRedis('TOURS_' . $_POST['postChannelId'], json_encode($showTours), RedisService::REDIS_TYPE_STRING);
        $re->expireAt('TOURS_' . $_POST['postChannelId'], $expirationTime);
    }


} else {
    $htmlTemplate = 'channels';
    if ($re->existKey($cacheName)) {
        $showChannels = json_decode($re->getItemFromRedis($cacheName, RedisService::REDIS_TYPE_STRING), true);
    } else {
        $showChannels = firstLoadCache($re, $cacheName, $expirationTime, $tourcms);
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Favicons -->
<link rel="apple-touch-icon" href="/docs/5.2/assets/img/favicons/apple-touch-icon.png" sizes="180x180">
<link rel="icon" href="/docs/5.2/assets/img/favicons/favicon-32x32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/docs/5.2/assets/img/favicons/favicon-16x16.png" sizes="16x16" type="image/png">
<link rel="manifest" href="/docs/5.2/assets/img/favicons/manifest.json">
<link rel="mask-icon" href="/docs/5.2/assets/img/favicons/safari-pinned-tab.svg" color="#712cf9">
<link rel="icon" href="/docs/5.2/assets/img/favicons/favicon.ico">

    <title>Prueba TourCMS</title>
</head>
<body>
    <div class="container">
    <header class="d-flex flex-wrap justify-content-center py-3 mb-4 border-bottom">
      <a href="" class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-dark text-decoration-none">
        <img class="bi me-2" width="40" height="40" src=https://media.licdn.com/dms/image/D4E0BAQGjUrt29N2Wfw/company-logo_200_200/0/1701772945232/palisis_ag_logo?e=2147483647&v=beta&t=6zPWDs4ZHt4AoRb6gJZmaNRbhvkivy-YAgD6hLp2LYs ></img>
        <span class="fs-4">Palsis</span>
      </a>

      <ul class="nav nav-pills">
        <li class="nav-item"><a href="#" class="nav-link" >Home</a></li>
        <li class="nav-item"><a href="#" class="nav-link">Features</a></li>
        <li class="nav-item"><a href="#" class="nav-link">Pricing</a></li>
        <li class="nav-item"><a href="#" class="nav-link">FAQs</a></li>
        <li class="nav-item"><a href="#" class="nav-link">About</a></li>
      </ul>
    </header>
</div>
    <?php
    if ($htmlTemplate == 'channels') {
        procedurePrintChannels($showChannels);
    } elseif ($htmlTemplate == 'tours') {
        procedurePrintTours($showTours);
    } elseif ($htmlTemplate == 'singleTour') {
        procedurePrintSingleTour($singleTour);
    } elseif ($htmlTemplate == 'checkTourDate') {
        procedurePrintSingleTour($singleTour);
        procedurePrintBooking($checkTourXML);
    } elseif ($htmlTemplate == 'temporalBooking') {
        procedurePrintSingleTour($singleTour);
        procedurePrintCommit($temporalBooking);
    }
    else {
        print_r($commitBooking);
    }
    ?>

<div class="container">
<footer class="d-flex flex-wrap justify-content-between align-items-center py-3 my-4 border-top">
    <p class="col-md-4 mb-0 text-muted">&copy; 2024 Palisis TourCMS</p>

    <ul class="nav col-md-4 justify-content-end">
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">Homeee</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">Featureees</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">Priciiing</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">FAQs</a></li>
      <li class="nav-item"><a href="#" class="nav-link px-2 text-muted">About</a></li>
    </ul>
  </footer>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>

</html>