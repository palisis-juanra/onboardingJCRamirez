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

$re = new RedisService($REDIS_HOST, $REDIS_PORT, $REDIS_PASSWORD);
$marketplaceIdForOperator = 0;
// $apiKey = "6f24ac3ac4ed";
// $marketplaceId = 33495;
// $apiKey = "52fc7e3d2ef7";
$marketplaceId = 12345;
$agentApiKey = "ccadca970eea";
$timeout = 0;
// $channelId = 142;
// $params = '';
$baseUrl = 'http://api.tourcms.local';
// $cacheName = 'channelList';
$expirationTime = time() + 600;
$tourcms = new TourCMS($marketplaceId, $agentApiKey, 'simplexml', $timeout);
$tourcms->set_base_url($baseUrl);


$reserSys = new ReservationSystem($tourcms, $re, $expirationTime);
$templates = new Templates();
$page = $templates->getPageUrl();
$data = $templates->getData($page);

if($page == 'home'){
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels; 
}
if($page == 'tours'){
    $channels = new ArrayIterator($reserSys->listChannels());
    $data['content']['channels'] = $channels;
    if(isset($_POST['postChannelId'])){
        $reserSys->listTours($_POST['postChannelId']);
        // $data['content']['tours_'.$_POST['postChannelId']] = new ArrayIterator($reserSys->listTours($_POST['postChannelId']));
        // error_log(print_r($data, true));
    }

    // $data['content']['tours'] = new ArrayIterator($reserSys->listTours($channelId, $params));
}

try {
    echo $templates->render($page, $data);
} catch (Mustache_Exception_UnknownTemplateException $e) {
    // La plantilla no existe, renderizar la plantilla 404
    echo $templates->render('404', $data);
}
