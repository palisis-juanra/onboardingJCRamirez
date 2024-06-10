<?php

use TourCMS\Utils\TourCMS as TourCMS;
use onboarding\services\RedisService;
//use function PHPSTORM_META\type;
//use PhpParser\Node\Expr\Cast;
//use PhpParser\Node\Expr\Print_;

class ReservationSystem
{
    static $cacheName;
    static $marketPlaceIdForOperator;
    static $timeOut;
    static $baseUrl;

    private $TourCMSAgent;
    private $redisService;
    private $expirationTime;

    public function __construct(TourCMSextension $TourCMSAgent, RedisService $redisService, $expirationTime)
    {
        self::$cacheName = 'cacheName';
        self::$marketPlaceIdForOperator = 0;
        self::$timeOut = 0;
        self::$baseUrl = 'http://api.tourcms.local';
        $this->TourCMSAgent = $TourCMSAgent;
        $this->expirationTime = $expirationTime;
        $this->redisService = $redisService;
    }



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

    public function listTours($channelId)
    {
        $cacheKey = 'TOURS_' . $channelId;
        if (!$this->redisService->existKey($cacheKey)) {
            $operator = $this->createOperator($channelId);
            $tours = $operator->list_tours($channelId);
            $formatedtours = $this->formatInfoTourJSONFromXML($tours);
            $this->cacheGeneralJSON($formatedtours, $cacheKey);
            return $formatedtours;
        } else {
            return json_decode($this->redisService->getItemFromRedis($cacheKey, RedisService::REDIS_TYPE_STRING), true);
        }
    }

    public function getTourDetails($channelId, $tourId)
    {
        $cacheKey = 'SINGLE_TOUR_' . $channelId . '_' . $tourId;
        if (!$this->redisService->existKey($cacheKey)) {
            $operator = $this->createOperator($channelId);
            $tourDetails = $operator->show_tour($tourId, $channelId);
            $formatedTourDetails = json_encode($tourDetails,);
            $this->cacheGeneralJSON($tourDetails, $cacheKey);
            return json_decode($formatedTourDetails);
        } else {
            return json_decode($this->redisService->getItemFromRedis($cacheKey, RedisService::REDIS_TYPE_STRING), true);
        }
    }

    // Private methods to handle caching
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

    private function cacheGeneralJSON($tours, $cacheKey)
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

    public function getRateFromSingleTour($singleTour)
    {
        $ratesAux = $singleTour['tour']['new_booking']['people_selection']['rate'];
        $rates = [];
        if (isset($ratesAux['rate_id'])) {
            $rates[] = ['rate_id' => $ratesAux['rate_id'], 'label_1' => $ratesAux['label_1'], 'label_2' => $ratesAux['label_2']];
        } else {
            foreach ($ratesAux as $rate) {
                $rates[] = ['rate_id' => $rate['rate_id'], 'label_1' => $rate['label_1'], 'label_2' => $rate['label_2']];
            }
        }

        return $rates;
    }

    public function getTotalAmountOfCustomers($query)
    {
        $total = 0;
        foreach ($query as $key => $value) {
            if ($key != 'date') {
                $total += (int)$value;
            }
        }
        return $total;
    }

    private function createOperator($channelId)
    {
        $TourCMSOperator = new TourCMSextension(self::$marketPlaceIdForOperator, $this->redisService->getItemFromRedis('API_KEY_' . $channelId, RedisService::REDIS_TYPE_STRING), 'simplexml', self::$timeOut);
        $TourCMSOperator->set_base_url(self::$baseUrl);
        return $TourCMSOperator;
    }

    public function checkTourAvailability($channelId, $tourId, $specs)
    {
        $tourCMSOperator = $this->createOperator($channelId);
        $availabilityXML = $tourCMSOperator->check_tour_availability(http_build_query($specs), $tourId, $channelId);
        $availability = json_decode(json_encode($availabilityXML), true);
        return $availability;
    }

    public function createTemporalBooking($channel_id, $componentKey, $arrayCustomers, $bookingAs = 'agent')
    {
        // We make sure to have the list of channels and their API keys cached, since otherwise we wouldn't be able to create the Operator
        $this->listChannels();

        if ($bookingAs == 'agent') {
            $tourcmsAmbiguous = $this->TourCMSAgent;
            $bookingKey = null;
        } elseif ($bookingAs == 'operator') {
            $tourcmsAmbiguous = $this->createOperator($channel_id);
            // Here you can use the operator api_key by using $this->requestBookingKeyForOperator but since the API supports the use 'NO_AGENT' as a booking key, we'll use it
            $bookingKey = 'NO_AGENT';
        } else {
            $tourcmsAmbiguous = $this->createOperator($channel_id);
            $bookingKey = $this->requestBookingKeyForOperatorAsAgent($tourcmsAmbiguous, $channel_id);
        }

        $booking = new SimpleXMLElement('<booking />');

        // Append the total customers, we'll add their details on below
        $booking->addChild('total_customers', count($arrayCustomers));

        // If we're calling the API as a Tour Operator we need to add a Booking Key
        // otherwise skip this
        // See "Getting a new booking key" for info
        if ($bookingKey != null) {
            $booking->addChild('booking_key', $bookingKey);
        }

        // Append a container for the components to be booked
        $components = $booking->addChild('components');

        // Add a component node for each item to add to the booking
        $component = $components->addChild('component');

        // "Component key" obtained via call to "Check availability"
        $component->addChild('component_key', $componentKey);

        // Append a container for the customer recrds
        $customers = $booking->addChild('customers');

        // Optionally append the customer details
        // Either add their details (as here)
        // OR an existing customer_id
        // OR leave blank and TourCMS will create a blank customer
        foreach ($arrayCustomers as $key => $customer) {
            $customerNode = $customers->addChild('customer');
            foreach ($customer as $key => $value) {
                $customerNode->addChild(trim($key, "'"), $value);
            }
        }

        // Query the TourCMS API, creating the booking

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
        curl_setopt($ch, CURLOPT_NOBODY, true); // We don't need the body of the response

        // Execute the request
        curl_exec($ch);

        // Get the final URL after following the redirects
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        curl_close($ch);

        return $finalUrl;
    }

    private function getQueryParam($url, $param)
    {
        // Parse the final URL and get the query string part
        $parsed_url = parse_url($url);

        // Check if the URL has a query string
        if (isset($parsed_url['query'])) {
            // Parse the query string into an array
            parse_str($parsed_url['query'], $query_params);

            // Check if the variable we are looking for is defined in the parameter array
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

    private function requestBookingKeyForOperatorAsAgent($tourcmsOperator, $channel_id)
    {
        $params = "agent_marketplace_id=" . $this->TourCMSAgent->getMarketplaceId();
        $result = $tourcmsOperator->search_agents($params, $channel_id);
        return json_decode(json_encode($result), true)['agent']['booking_key'];
    }

    public function commitBooking($channelId, $bookingId, $bookingAs = 'agent')
    {
        if ($bookingAs == 'agent') {
            $tourcmsAmbiguous = $this->TourCMSAgent;
        } elseif ($bookingAs == 'operator') {
            $tourcmsAmbiguous = $this->createOperator($channelId);
        } else {
            $tourcmsAmbiguous = $this->createOperator($channelId);
        }

        $booking = new SimpleXMLElement('<booking />');
        $booking->addChild('booking_id', $bookingId);

        $result = $tourcmsAmbiguous->commit_new_booking($booking, $channelId);
        return $result->booking;
    }

    public function showBooking($channelId, $bookingId)
    {
        $this->listChannels();
        $operator = $this->createOperator($channelId);
        $cacheKey = 'BOOKING_' . $channelId . '_' . $bookingId;
        if (!$this->redisService->existKey($cacheKey)) {
            $operator = $this->createOperator($channelId);
            $bookingDetails = $operator->show_booking($bookingId, $channelId);
            $formatedbookingDetails = json_encode($bookingDetails,);
            $this->cacheGeneralJSON($bookingDetails, $cacheKey);
            return json_decode(html_entity_decode($formatedbookingDetails), true);
        } else {
            return json_decode(htmlspecialchars_decode($this->redisService->getItemFromRedis($cacheKey, RedisService::REDIS_TYPE_STRING)), true);
        }
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
