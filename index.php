<?php
include_once 'vendor/autoload.php';
include_once 'autoload.php';
include_once 'config.php';


Mustache_Autoloader::register();

use TourCMS\Utils\TourCMS as TourCMS;
use onboarding\services\RedisService;
//use function PHPSTORM_META\type;
//use PhpParser\Node\Expr\Cast;
//use PhpParser\Node\Expr\Print_;


$redis = new RedisService($REDIS_HOST, $REDIS_PORT, $REDIS_PASSWORD);
$tourcms = new TourCMSextension($MARKETPLACE_ID, $AGENT_API_KEY, 'simplexml', $TIMEOUT);
$tourcms->set_base_url($BASE_URL);
$expirationTime = time() + 600;

session_start();


$reserSys = new ReservationSystem($tourcms, $redis, $expirationTime);
$templates = new Templates();
$genService = new GeneralService();
$page = $templates->getPageUrl();
$data = $templates->getData($page, isset($_SESSION['bookingComponents']) ? countTotalAmountComponents($_SESSION['bookingComponents']): 0);


if (isset($_GET['username']) && isset($_GET['password'])) {
    try {
        $xml = $genService->getCredentialsForOperator($SCRIPT_LOGIN, $_GET);
        if ($xml->error == 'OK') {
            $_SESSION['username'] = $_GET['username'];
            $_SESSION['logged'] = true;
            $_SESSION['bookingComponents'] = [];
        }
    } catch (Exception $e) {
        $_SESSION['logged'] = false;

    }
}

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: ' . $templates->getIndex() . '/login');
}

if ($page != 'login' && (!isset($_SESSION['logged']) || $_SESSION['logged'] != true)) {
    header('Location: ' . $templates->getIndex() . '/login');
} elseif ($page == 'login' && isset($_SESSION['logged']) && $_SESSION['logged'] != true) {
    header('Location: ' . $templates->getIndex());
    $reserSys->checkIfChannelsExists();
} else {
    $reserSys->checkIfChannelsExists();
}


if ($page == 'bookingManager') {
    if (count($_SESSION['bookingComponents']) == 0) 
        $data['content']['emptyCart'] = true;

    if (isset($_POST['postCommitBooking'])) {
        $booking = $reserSys->commitBooking($_POST['postChannelId'], $_POST['postRequestedBooking'], $_POST['postRequestBookingAs']);
        $data['content']['bookingDone'] = true;
        $data['content']['bookingChannel'] = $booking->channel_id;
        $data['content']['bookingId'] = $booking->booking_id;
        removeChannelFromCart($_SESSION['bookingComponents'], $_POST['postChannelId']);
    } elseif (isset($_POST['postDeleteTemporalBooking'])) {
        try {
            $reserSys->deleteBooking($_POST['postRequestedBooking'], $_POST['postChannelId']);
            $data['content']['bookingDeleted'] = true;
        } catch (Exception $e) {
            $data['content']['bookingError'] = true;
            $data['content']['bookingErrorMessage'] = $e->getMessage();
        }
    } elseif (isset($_POST['postRequesBooking'])) {
        $booking = $reserSys->createTemporalBooking($_POST['postChannelId'], $_SESSION['bookingComponents'][$_POST['postChannelId']], $_POST['postRequestBookingAs']);
        $data['content']['resquestedBooking']['channel_id'] = $_POST['postChannelId'];
        $data['content']['resquestedBooking']['requestBookingAs'] = $_POST['postRequestBookingAs'];
        $data['content']['resquestedBooking']['resquestedBookingResponse'] = $booking;
    } elseif (isset($_POST['postRemoveComponent'])) {
        removeComponentFromCart($_SESSION['bookingComponents'], $_POST['postChannelId'], $_POST['postComponentKey']);
        $data['content']['bookingComponents'] = $_SESSION['bookingComponents'];
    } else
        $data['content']['bookingComponents'] = $_SESSION['bookingComponents'];

} elseif ($page == 'updateCustomer') {
    if (isset($_POST['postSearchCustomer'])) {
        if (isset($_POST['updateCustomer'])) {
            $reserSys->updateCustomer($_POST['postChannelId'], $_POST['postInfoCustomer']);
        }
        $data['content']['customerDetails'] = $reserSys->showCustomer($_POST['postCustomerId'], $_POST['postChannelId']);
    }

} elseif ($page == 'bookingDetails') {
    if (isset($_POST['postSearchBooking'])) {
        $paymentCompleted = 3; // value comming from api. goes from 1 to 4
        if (isset($_POST['postUpdateCancelation'])) {
            $cancelation = $reserSys->cancelBooking($_POST['postChannelId'], $_POST['postBookingId'], $_POST['postCancelationReason']);
        }
        if(isset($_POST['postCreatePayment'])){
            $payment = $reserSys->createPayment($_POST['postChannelId'], $_POST['postInfoPayment']);
            }
        $data['content']['bookingDetails'] = $reserSys->showBooking($_POST['postChannelId'], $_POST['postBookingId']);
        $data['content']['bookingDetails']['auxBooleanPaymentFulfilled'] =  $data['content']['bookingDetails']['payment_status'] == $paymentCompleted ? true : false;
        generalFormat($data, $data['content']['bookingDetails']);
    }

} elseif ($page == 'formCustomers') {
    if (isset($_POST['postShowCustomerForms'])) {
        $data['content']['formCustomers']['channel_id'] = $_POST['postChannelId'];
        $data['content']['formCustomers']['tour_id'] = $_POST['postTourId'];
        $data['content']['formCustomers']['component_key'] = $_POST['postComponentKey'];
        $data['content']['formCustomers']['totalAmountCustomers'] = $_POST['postTotalAmountCustomers'];
        for ($i = 1; $i <= $_POST['postTotalAmountCustomers']; $i++) {
            $data['content']['formCustomers']['customers'][] = $i;
    }
    } elseif (isset($_POST['postAddToCart'])) {
        $_SESSION['bookingComponents'] = buildingArrayForCart($_POST);
        $data['content']['bookingAdded'] = true;
        $data['content']['bookingChannel'] = $_POST['postChannelId'];
    }

} elseif ($page == 'singleTour') {
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels;
    if (isset($_POST['postTourId'])) {
        $data['content']['singleTour'] = $reserSys->getTourDetails($_POST['postChannelId'], $_POST['postTourId']);
        $data['content']['ratesAuxiliary'] = $reserSys->getRateFromSingleTour($reserSys->getTourDetails($_POST['postChannelId'], $_POST['postTourId']));
    }
    if (isset($_POST['postCheckAvailability'])) {
        $data['content']['availability'] = $reserSys->checkTourAvailability($_POST['postChannelId'], $_POST['postTourId'], $_POST['postQuery']);
        $data['content']['availability']['totalAmountOfCustomers'] = $reserSys->getTotalAmountOfCustomers($_POST['postQuery']);
    }

} elseif ($page == 'tours') {
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels;
    if (isset($_POST['postChannelId'])) {
        $reserSys->listTours($_POST['postChannelId']);
        $data['content']['tours'] = new ArrayIterator($reserSys->listTours($_POST['postChannelId']));
    }
} elseif ($page == 'channels') {
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels;
}

try {
    echo $templates->render($page, $data);
} catch (Mustache_Exception_UnknownTemplateException $e) {
    echo $templates->render('404', $data);
}

function generalFormat(&$data, $bookingInfo)
{
    $data['content']['customersFromBooking'] = formatCustomers($bookingInfo);
    $data['content']['componentsFromBooking'] = formatComponents($bookingInfo);
}

function formatCustomers($bookingInfo)
{
    $customersIndexArray = ['customer_id', 'customer_name', 'title', 'firstname', 'middlename', 'surname', 'customer_email', 'customer_tel_home', 'nationality_text'];
    $customers = [];
    if (isset($bookingInfo['customers']['customer']['customer_id'])) {
        foreach ($customersIndexArray as $key) {
            $customers[0][$key] = $bookingInfo['customers']['customer'][$key];
        }
    } else {
        foreach ($bookingInfo['customers']['customer'] as $customerFromBooking) {
            $customerAux = [];
            foreach ($customersIndexArray as $key) {
                $customerAux[$key] = $customerFromBooking[$key];
            }
            $customers[] = $customerAux;
        }
    }
    return $customers;
}

function formatComponents($bookingInfo)
{
    $componentsIndexArray = ['component_id', 'product_id', 'date_id', 'date_type', 'product_code', 'start_date', 'end_date', 'rate_breakdown', 'sale_price', 'sale_currency'];
    $components = [];
    if (isset($bookingInfo['components']['component']['component_id'])) {
        foreach ($componentsIndexArray as $key) {
            $components[0][$key] = $bookingInfo['components']['component'][$key];
        }
    } else {
        foreach ($bookingInfo['components']['component'] as $componentFromBooking) {
            $componentAux = [];
            foreach ($componentsIndexArray as $key) {
                $componentAux[$key] = $componentFromBooking[$key];
            }
            $components[] = $componentAux;
        }
    }
    return $components;
}

function countTotalAmountComponents($bookingComponentsPerChannel)
{
    $totalAmountComponents = 0;
    foreach ($bookingComponentsPerChannel as $channel) {
            $totalAmountComponents += count($channel['dataPerChannel']);
    }
    return $totalAmountComponents ?? 0;
}

function buildingArrayForCart($post){
    $array = empty($_SESSION['bookingComponents'])? new ArrayIterator(): $_SESSION['bookingComponents'];
    $array[$post['postChannelId']]['dataPerChannel'] = empty($array[$post['postChannelId']]['dataPerChannel'])? new ArrayIterator(): $array[$post['postChannelId']]['dataPerChannel'];
    $array[$post['postChannelId']]['dataPerChannel'][] = $post;
    $array[$post['postChannelId']]['channelId'] = $post['postChannelId'];
    error_log(print_r($array, true));
    return $array;
}

function removeComponentFromCart(&$array, $channelId, $componentId){
    error_log(print_r($_SESSION['bookingComponents'], true));
    foreach ($array as $key => $channel) { 
        foreach ($channel['dataPerChannel'] as $key2 => $component) { 
            if($component['postComponentKey'] == $componentId && $channel['channelId'] == $channelId){
                unset($array[$key]['dataPerChannel'][$key2]);
            }
            break;
        }
    }
}

function removeChannelFromCart(&$array, $channelId){
    foreach ($array as $key => $channel) { 
        if($channel['channelId'] == $channelId){
            unset($array[$key]);
        }
    }
}