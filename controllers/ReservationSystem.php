<?php

use TourCMS\Utils\TourCMS as TourCMS;
use function PHPSTORM_META\type;
use PhpParser\Node\Expr\Cast;
use onboarding\services\RedisService;
use PhpParser\Node\Expr\Print_;

class ReservationSystem
{
    static $cacheName;
    static $marketPlaceIdForOperator;
    static $timeOut;
    static $baseUrl;

    private $TourCMSAgent;
    private $redisService;
    private $expirationTime;

    public function __construct(TourCMS $TourCMSAgent, RedisService $redisService, $expirationTime)
    {
        self::$cacheName = 'cacheName';
        self::$marketPlaceIdForOperator = 0;
        self::$timeOut = 0;
        self::$baseUrl = 'http://api.tourcms.local';
        $this->TourCMSAgent = $TourCMSAgent;
        $this->expirationTime = $expirationTime;
        $this->redisService = $redisService;
    }



    // Método para listar canales
    public function listChannels()
    {
        if (!$this->redisService->existKey(self::$cacheName)) {
            $channels = $this->TourCMSAgent->list_channels();
            $formatedChannels = $this->formatInfoChannelJSONFromXML($channels);
            $this->cacheChannels($formatedChannels);
            return $formatedChannels;
        } else {
            return json_decode($this->redisService->getItemFromRedis(self::$cacheName, RedisService::REDIS_TYPE_STRING), true);
        }
    }

    // Método para listar tours de un canal específico
    public function listTours($channelId)
    {
        $operator = $this->createOperator($channelId);
        $cacheKey = 'TOURS_' . $channelId;
        if (!$this->redisService->existKey($cacheKey)) {
            $tours = $this->TourCMSAgent->list_tours($channelId);
            error_log(print_r($tours, true));
            $formatedtours = $this->formatInfoTourJSONFromXML($tours);
            $this->cacheTours($formatedtours, $cacheKey);
            return $formatedtours;
        } else {
            return json_decode($this->redisService->getItemFromRedis($cacheKey, RedisService::REDIS_TYPE_STRING), true);
        }
    }

    // Método para obtener detalles de un tour específico
    public function getTourDetails($channelId, $tourId)
    {
        $cacheKey = 'SINGLE_TOUR_' . $channelId . '_' . $tourId;
        if (!$this->redisService->existKey($cacheKey)) {
            $tourDetails = $this->TourCMSAgent->show_tour($tourId, $channelId);
            $this->cacheTourDetails($tourDetails, $cacheKey);
            return $tourDetails;
        } else {
            return json_decode($this->redisService->getItemFromRedis($cacheKey, RedisService::REDIS_TYPE_STRING), true);
        }
    }

    // Métodos privados para manejar el almacenamiento en caché
    private function cacheChannels($channels)
    {
        $this->redisService->storeItemInRedis(self::$cacheName, json_encode($channels), RedisService::REDIS_TYPE_STRING);
        $this->redisService->expireAt(self::$cacheName, $this->expirationTime);
        foreach ($channels as $channel) {
            $this->redisService->storeItemInRedis('API_KEY_' . $channel['channel_id'], $channel['channel_key'], RedisService::REDIS_TYPE_STRING);
            $this->redisService->expireAt('API_KEY_' . $channel['channel_id'], $this->expirationTime);
        }
    }

    private function formatInfoChannelJSONFromXML($channelsXML)
    {
        foreach ($channelsXML->channel as $singleChannel) {
            $jsonInfoCanal = [
                'channel_id' => (string)$singleChannel->channel_id,
                'channel_name' => (string)$singleChannel->channel_name,
                'channel_logo' => (string)$singleChannel->logo_url,
                'channel_desc' => (string)$singleChannel->long_desc,
                'channel_key' => (string)$singleChannel->internal_api_key
            ];
            $listaChannels[(string)$singleChannel->channel_id] = $jsonInfoCanal;
        }
        return $listaChannels;
    }

    private function cacheTours($tours, $cacheKey)
    {
        $this->redisService->storeItemInRedis($cacheKey, json_encode($tours), RedisService::REDIS_TYPE_STRING);
        $this->redisService->expireAt($cacheKey, $this->expirationTime);
    }

    private function formatInfoTourJSONFromXML($toursXML)
    {  
        $listaTours = [];
        foreach ($toursXML->tour as $singleTour) {
            $jsonInfoTour = [
                'channel_id' => (string)$singleTour->channel_id,
                'account_id' => (string)$singleTour->account_id,
                'tour_id' => (string)$singleTour->tour_id,
                'tour_name' => (string)$singleTour->tour_name,
                'tour_code' => (string)$singleTour->tour_code,
            ];
            $listaTours[(string)$singleTour->tour_id] = $jsonInfoTour;
        }
        return $listaTours;
    }

    
    private function cacheTourDetails($tourDetails, $cacheKey)
    {
        $formattedTourDetails = json_decode($tourDetails, true);
        $this->redisService->storeItemInRedis($cacheKey, json_encode($formattedTourDetails), RedisService::REDIS_TYPE_STRING);
        $this->redisService->expireAt($cacheKey, $this->expirationTime);
    }

    private function formatSingleTourJSONFromXML($singleTourXML)
    {
        $listaInfoTour = [
            'channel_id' => (string)$singleTourXML->channel_id,
            'account_id' => (string)$singleTourXML->account_id,
            'tour_id' => (string)$singleTourXML->tour_id,
            'tour_name' => (string)$singleTourXML->tour_name,
            'tour_code' => (string)$singleTourXML->tour_code,
        ];
        return $listaInfoTour;
    }

    private function createOperator($channelId)
    {
        $TourCMSOperator = new TourCMS(self::$marketPlaceIdForOperator, $channelId, 'simplexml', self::$timeOut);
        $TourCMSOperator->set_base_url(self::$baseUrl);
        return $TourCMSOperator;
    }

    // Método para comprobar disponibilidad de un tour en una fecha específica
    public function checkTourAvailability($channelId, $tourId, $specs)
    {
        $tourCMSOperator = $this->createOperator($channelId);
        $availabilityXML = $tourCMSOperator->check_tour_availability(http_build_query($specs), $tourId, $channelId);
        $availability = json_decode(json_encode(simplexml_load_string($availabilityXML)), true);
        return $availability;
    }

    // Método para crear una reserva temporal
    public function createTemporalBooking($tourcmsAmbiguous, $channel_id, $component_key, $booking_key = null)
    {
        $booking = new SimpleXMLElement('<booking />');

        // Append the total customers, we'll add their details on below
        $booking->addChild('total_customers', '1');

        // If we're calling the API as a Tour Operator we need to add a Booking Key
        // otherwise skip this
        // See "Getting a new booking key" for info
        if ($booking_key != null) {
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

        error_log("Creating booking: " . $booking->asXML());
        error_log(("CHANNEL: " . $channel_id));

        $result = $tourcmsAmbiguous->start_new_booking($booking, $channel_id);

        $bkg = $result->booking;
        return $bkg;
    }

    private function getFinalUrl($url)
    {
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

    private function getQueryParam($url, $param)
    {
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

    private function requestBookingKeyForOperator($tourcmsOperator, $channel)
    {
        $url_data  = new SimpleXMLElement('<booking />');
        $url_data->addChild('response_url', 'http://localhost/onboarding/tourcms-back/index.php');
        $result = $tourcmsOperator->get_booking_redirect_url($url_data, $channel);
        $redirect_url = $result->url->redirect_url;
        $redirected = $this->getFinalUrl($redirect_url);
        $booking_key = $this->getQueryParam($redirected, 'booking_key');

        return $booking_key;
    }

    // Método para obtener una clave de reserva (booking key)
    private function requestBookingKeyForOperatorAsAgent($tourcmsOperator, $marketplaceId, $channel)
    {
        $params = "agent_marketplace_id=" . $marketplaceId;
        $result = $tourcmsOperator->search_agents($params, $channel);
        return json_decode(json_encode($result), true)['agent']['booking_key'];
    }

    // Método para confirmar una reserva
    private function commitBooking($tourcmsAmbiguous, $channel, $booking_id)
    {
        $booking = new SimpleXMLElement('<booking />');
        $booking->addChild('booking_id', $booking_id);

        $result = $tourcmsAmbiguous->commit_new_booking($booking, $channel);
        return $result;
    }
    public function setCacheName($cacheName)
    {
        self::$cacheName = $cacheName;
    }
    public function getCacheName()
    {
        return self::$cacheName;
    }
    public function setMarketplaceIdForOperator($marketPlaceIdForOperator)
    {
        self::$marketPlaceIdForOperator = $marketPlaceIdForOperator;
    }
    public function getMarketplaceIdForOperator()
    {
        return self::$marketPlaceIdForOperator;
    }
    public function setTimeOut($timeOut)
    {
        self::$timeOut = $timeOut;
    }
    public function getTimeOut()
    {
        return self::$timeOut;
    }
    public function setBaseUrl($baseUrl)
    {
        self::$baseUrl = $baseUrl;
    }
    public function getBaseUrl()
    {
        return self::$baseUrl;
    }
}
