<?php
use onboarding\services\RedisService;

class GeneralService
{
    static $cacheName;

    private $redis;
    private $expirationTime;

    public function __construct($redis, $expirationTime)
    {
        self::$cacheName = 'agentApiKey';
        $this->redis = $redis;
        $this->expirationTime = $expirationTime;
    }


    public function getXMLFromValidation($url, $paramsFromGet)
    {

        $url .= '?' . http_build_query($paramsFromGet);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        // Handle any errors
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        //Loads the XML response into a SimpleXMLElement object
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new Exception('Failed loading XML');
        }

        return $xml;
    }

    public function getXMLForAgent($url, $paramsForPost)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($paramsForPost));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        // Handle any errors
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        //Loads the XML response into a SimpleXMLElement object
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new Exception('Failed loading XML');
        }

        return $xml;
    }

    public function cacheApiKeyAgent($apiKey)
    {
        $this->redis->storeItemInRedis(self::$cacheName, $apiKey, RedisService::REDIS_TYPE_STRING);
        $this->redis->expireAt(self::$cacheName, $this->expirationTime);
    }

    public function getApiKeyAgent()
    {
        return $this->redis->getItemFromRedis(self::$cacheName, RedisService::REDIS_TYPE_STRING);
    }

    public function checkApiKeyExists()
    {
        return $this->redis->existKey(self::$cacheName);
    }

    public function setCacheName($cacheName)
    {
        self::$cacheName = $cacheName;
    }
    
    public function getCacheName()
    {
        return self::$cacheName;
    }
}
