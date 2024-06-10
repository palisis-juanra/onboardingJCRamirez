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



if ($page == 'bookingDetails') {
    if(isset($_POST['postSearchBooking'])){
        $data['content']['bookingDetails'] = $reserSys->showBooking($_POST['postChannelId'], $_POST['postBookingId']);
    }

} elseif ($page == 'formCustomers') {
    if(isset($_POST['postCommitBooking'])){
        $booking = $reserSys->commitBooking($_POST['postChannelId'], $_POST['postRequestedBooking'], $_POST['postRequestBookingAs']);
        error_log(print_r($booking, true));
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
        error_log(print_r($data, true));
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
