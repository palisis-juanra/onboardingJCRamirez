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


$reserSys = new ReservationSystem($tourcms, $redis, $expirationTime);
$templates = new Templates();
$page = $templates->getPageUrl();
$data = $templates->getData($page);

$reserSys->checkIfChannelsExists();
if ($page == 'updateCustomer') {
    if (isset($_POST['postSearchCustomer'])) {
        if (isset($_POST['updateCustomer'])) {
            $reserSys->updateCustomer($_POST['postChannelId'], $_POST['postInfoCustomer']);
        }
        $data['content']['customerDetails'] = $reserSys->showCustomer($_POST['postCustomerId'], $_POST['postChannelId']);
    }
} elseif ($page == 'bookingDetails') {
    if (isset($_POST['postUpdateCancelation'])) {
        $cancelation = $reserSys->cancelBooking($_POST['postChannelId'], $_POST['postBookingId'], $_POST['postCancelationReason']);
        $data['content']['bookingDetails'] = $reserSys->forceShowBookingUpdate($_POST['postChannelId'], $_POST['postBookingId']);
        generalFormat($data, $data['content']['bookingDetails']);
    } elseif (isset($_POST['postSearchBooking'])) {
        $data['content']['bookingDetails'] = $reserSys->showBooking($_POST['postChannelId'], $_POST['postBookingId']);
        generalFormat($data, $data['content']['bookingDetails']);
    }
} elseif ($page == 'formCustomers') {
    if (isset($_POST['postCommitBooking'])) {
        $booking = $reserSys->commitBooking($_POST['postChannelId'], $_POST['postRequestedBooking'], $_POST['postRequestBookingAs']);
        $data['content']['bookingDone'] = true;
        $data['content']['bookingChannel'] = $booking->channel_id;
        $data['content']['bookingId'] = $booking->booking_id;
    } elseif (isset($_POST['postRequesBooking'])) {
        $booking = $reserSys->createTemporalBooking($_POST['postChannelId'], $_POST['postComponentKey'], $_POST['postCustomersArray'], $_POST['postRequestBookingAs']);
        $data['content']['resquestedBooking']['channel_id'] = $_POST['postChannelId'];
        $data['content']['resquestedBooking']['requestBookingAs'] = $_POST['postRequestBookingAs'];
        $data['content']['resquestedBooking']['resquestedBookingResponse'] = $booking;
    } else {
        $data['content']['formCustomers']['channel_id'] = $_POST['postChannelId'];
        $data['content']['formCustomers']['tour_id'] = $_POST['postTourId'];
        $data['content']['formCustomers']['component_key'] = $_POST['postComponentKey'];
        $data['content']['formCustomers']['totalAmountCustomers'] = $_POST['postTotalAmountCustomers'];
        for ($i = 1; $i <= $_POST['postTotalAmountCustomers']; $i++) {
            $data['content']['formCustomers']['customers'][] = $i;
        }
        $data['content']['formCustomers']['requestBookingAs'] = $_POST['postBookingAs'];
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
        $data['content']['availability']['bookingAs'] = $_POST['postBookingAs'];
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
    $customersIndexArray = ['customer_id', 'firstname', 'middlename', 'surname', 'customer_email', 'customer_tel_home', 'nationality_text'];
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
