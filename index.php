<?php
include_once 'vendor/autoload.php';
include_once 'autoload.php';
include_once 'config.php';


Mustache_Autoloader::register();
use TourCMS\Utils\TourCMS as TourCMS;
use function PHPSTORM_META\type;
use PhpParser\Node\Expr\Cast;
use onboarding\services\RedisService;
use PhpParser\Node\Expr\Print_;


$redis = new RedisService($REDIS_HOST, $REDIS_PORT, $REDIS_PASSWORD);
$tourcms = new TourCMS($MARKETPLACE_ID, $AGENT_API_KEY, 'simplexml', $TIMEOUT);
$tourcms->set_base_url($BASE_URL);
$expirationTime = time() + 600;
// $apiKey = "6f24ac3ac4ed";
// $marketplaceId = 33495;
// $apiKey = "52fc7e3d2ef7";
// $channelId = 142;
// $params = '';
// $cacheName = 'channelList';


$reserSys = new ReservationSystem($tourcms, $redis, $expirationTime);
$templates = new Templates();
$page = $templates->getPageUrl();
$data = $templates->getData($page);

if($page == 'formCustomers'){
    $data['content']['formCustomers']['channel_id'] = $_POST['postChannelId'];
    $data['content']['formCustomers']['tour_id'] = $_POST['postTourId'];
    $data['content']['formCustomers']['component_key'] = $_POST['postComponentKey'];

}

elseif($page == 'singleTour'){    
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels;
    if(isset($_POST['postTourId'])){
        $data['content']['singleTour'] = $reserSys->getTourDetails($_POST['postChannelId'], $_POST['postTourId']);
        $data['content']['ratesAuxiliary'] = $reserSys->getRateFromSingleTour($reserSys->getTourDetails($_POST['postChannelId'], $_POST['postTourId']));
    }
    if(isset($_POST['postCheckAvailability'])){
        $data['content']['availability'] = $reserSys->checkTourAvailability($_POST['postChannelId'], $_POST['postTourId'], $_POST['postQuery']);
        $data['content']['availability']['totalAmountOfCustomers'] =$reserSys->getTotalAmountOfCustomers($_POST['postQuery']);
        // error_log(print_r($data['content']['availability'], true));
    }
}
elseif($page == 'tours'){
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels;
    if(isset($_POST['postChannelId'])){
        $reserSys->listTours($_POST['postChannelId']);
        $data['content']['tours'] = new ArrayIterator($reserSys->listTours($_POST['postChannelId']));
    }
}
elseif($page == 'channels'){
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels; 
    error_log(print_r($data, true));
}

try {
    echo $templates->render($page, $data);
} catch (Mustache_Exception_UnknownTemplateException $e) {
    // La plantilla no existe, renderizar la plantilla 404
    echo $templates->render('404', $data);
}
