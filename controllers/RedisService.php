<?php
// ------------------------------------------------------------------------------------------------
// Holds all functionality that relates TourCMS Redis.
//
// Next code is going to follow PSR coding style rules.
// http://www.php-fig.org/psr/psr-2/

# TourCMS: PHP library class for TourCMS Redis internal use.
# Follow a singleton pattern
# Version: 1.0.0
# Author: Juan Ramon Gonzalez Morales

namespace onboarding\services;

use Predis;
use Predis\Response\ServerException;

use \stdClass;
use \Exception;

class RedisService
{

// ---- CONSTANTS ----

    const ACTION_DELETE_ITEM_FROM_REDIS = 'deleteItemFromRedis';
    const ACTION_EXIST_ITEM_IN_REDIS_SET = 'existItemInRedisSet';
    const ACTION_EXIST_KEY = 'existKey';
    const ACTION_EXPIRE_AT = 'expireAt';
    const ACTION_GET_ITEM_FROM_REDIS = 'getItemFromRedis';
    const ACTION_GET_SIZE = 'getSize';
    const ACTION_STORE_ITEM_IN_REDIS = 'storeItemInRedis';

    const ERROR_PREFIX = ' ERROR: ';
    const ERROR_UNABLE_TO_LOAD_REDIS = 'Unable to load Redis';

    const LOG_OPERATION_DETAILS = 'REDIS OPERATION ERROR. DETAILS: ';

    const REDIS_EXCEPTION_PREFIX = " REDIS EXCEPTION: ";
    const REDIS_EXPIRATION_PLUS_23H = '+23 Hours';
    const REDIS_HOST = 'host';
    const REDIS_OK = 'OK';
    const REDIS_PASS = 'password';
    const REDIS_PORT = 'port';
    const REDIS_TYPE_LIST = 'list';
    const REDIS_TYPE_SET = 'set';
    const REDIS_TYPE_STRING = 'string';

// ---- VARIABLES ----

    protected $redis;

// ---- CONSTRUCT ----

    public function __construct($redisHost, $redisPort, $redisPassword)
    {

        try {

            $config = [self::REDIS_HOST => $redisHost, self::REDIS_PORT => $redisPort];
            if (!empty($redisPassword)) {
                $config[self::REDIS_PASS] = $redisPassword;
            }

            Predis\Autoloader::register();
            $this->redis = new Predis\Client($config);
            $this->redis->connect();

        } catch(Predis\Connection\ConnectionException $e) {
            throw new RedisServiceException(self::ERROR_UNABLE_TO_LOAD_REDIS.self::ERROR_PREFIX.$e->getMessage());
        }

    }

// ---- PUBLIC FUNCTIONS ----

    /**
     * Function to delete a key from Redis DB.
     *
     * @param   string     $key            Key name we want to delete.
     * @param   string     $dataType       String containing the data type should either be list, set or string.
     * @param   string     $container      Key name of the container where the desired key will be deleted. Just in case.
     * @return  int                        1 if key was deleted. 0 if not.
     */
    public function deleteItemFromRedis(string $key, string $dataType, string $container =  null)
    {

        try {

            switch ($dataType) {

                case self::REDIS_TYPE_STRING:
                    return $this->redis->del($key);
                    break;

                case self::REDIS_TYPE_LIST:
                    return $this->redis->lrem($container, 0, $key);
                    break;

                case self::REDIS_TYPE_SET:
                    return $this->redis->srem($container, $key);
                    break;

            }

        } catch (Exception | ServerException $e) {

            $operationDetails = new stdClass;
            $operationDetails->action = self::ACTION_DELETE_ITEM_FROM_REDIS;
            $operationDetails->key = $key;
            $operationDetails->container = $container;
            $operationDetails->dataType = $dataType;
            $this->saveErrorDetailsInLog($e, $operationDetails);

            throw new RedisServiceException($e->getMessage());

        }

        return 0;

    }

    /**
     * Function which checks if a value exists in a set Redis datatype.
     *
     * @param  string       $key        Key for the set datatype.
     * @param  string       $value      Looked value in the set.
     * @return int                      1 if value exists in key. 0 if not.
     */
    public function existItemInRedisSet(string $key, $value)
    {

        try {
            return $this->redis->sismember($key, $value);
        } catch (Exception | ServerException $e) {

            $operationDetails = new stdClass;
            $operationDetails->action = self::ACTION_EXIST_ITEM_IN_REDIS_SET;
            $operationDetails->key = $key;
            $operationDetails->value = $value;
            $this->saveErrorDetailsInLog($e, $operationDetails);

            throw new RedisServiceException($e->getMessage());

        }

    }

    /**
     * Function which checks if key exists in Redis database.
     *
     * @param  string   $key    Looked key in redis database.
     * @return int              1 if key exists. 0 if not.
     */
    public function existKey(string $key)
    {

        try {
            return $this->redis->exists($key);
        } catch (Exception | ServerException $e) {

            $operationDetails = new stdClass;
            $operationDetails->action = self::ACTION_EXIST_KEY;
            $operationDetails->key = $key;
            $this->saveErrorDetailsInLog($e, $operationDetails);

            throw new RedisServiceException($e->getMessage());

        }

    }

    /**
     * Sets a TTL on a Redis database key .
     *
     * @param   string        $key          string containing the item key.
     * @param   string        $timestamp    string containing the timestamp for the TTL.
     * @return  int                         1 if expiration TTL was set. 0 if not.
     */
    public function expireAt(string $key, $timestamp)
    {

        try {
            return $this->redis->expireat($key, $timestamp);
        } catch (Exception | ServerException $e) {

            $operationDetails = new stdClass;
            $operationDetails->action = self::ACTION_EXPIRE_AT;
            $operationDetails->key = $key;
            $operationDetails->timestamp = $timestamp;
            $this->saveErrorDetailsInLog($e, $operationDetails);

            throw new RedisServiceException($e->getMessage());

        }

    }

    /**
     * Function to get an item either list, set or string from redis based on data type passed.
     *
     * @param   string    $key          Item key.
     * @param   string    $dataType     Data type. This is list, set or string.
     * @return  string|array            Depending on data types. String or Array.
     * If data type is string will return string if key exists. NULL if key no exist.
     * If data type is set/list will return an array.
     */
    public function getItemFromRedis($key, $dataType)
    {

        try {

            switch ($dataType) {

                case self::REDIS_TYPE_STRING:
                    return $this->redis->get($key);
                    break;

                case self::REDIS_TYPE_LIST:
                    return $this->redis->lrange($key,0,-1);
                    break;

                case self::REDIS_TYPE_SET:
                    return $this->redis->smembers($key);
                    break;
            }

        } catch (Exception | ServerException $e) {

            $operationDetails = new stdClass;
            $operationDetails->action = self::ACTION_GET_ITEM_FROM_REDIS;
            $operationDetails->key = $key;
            $operationDetails->dataType = $dataType;
            $this->saveErrorDetailsInLog($e, $operationDetails);

            throw new RedisServiceException($e->getMessage());

        }

        return '';

    }

    /**
     * Function to get size of an item.
     *
     * @param   string    $key          Item key.
     * @param   string    $dataType     Data type. This is list, set or string.
     * @return  int                     Size of data type key searched. 0 if key no exist.
     */
    public function getSize($key, $dataType)
    {

        try {

            switch ($dataType) {

                case self::REDIS_TYPE_STRING:
                    return $this->redis->strlen($key);
                    break;

                case self::REDIS_TYPE_LIST:
                    return $this->redis->llen($key);
                    break;

                case self::REDIS_TYPE_SET:
                    return $this->redis->scard($key);
                    break;

            }

        } catch (Exception | ServerException $e) {
            $operationDetails = new stdClass;
            $operationDetails->action = self::ACTION_GET_SIZE;
            $operationDetails->key = $key;
            $operationDetails->dataType = $dataType;
            $this->saveErrorDetailsInLog($e, $operationDetails);

            throw new RedisServiceException($e->getMessage());

        }

        return 0;

    }

    /**
     * Function to store an item into as string, a set or list in redis based on data type passed.
     *
     * @param    string      $key       String containing the item key.
     * @param    string      $value     String containing the item value.
     * @param    string      $dataType  Containing data type this is either list or set or string.
     * @return   string/int             Depending on data type.
     * If data type is string will return "OK".
     * If data type is list will return the index where the new value is added.
     * If data type is set will return 1 if added 0 if not added.
     */
    public function storeItemInRedis($key, $value, $dataType)
    {

        try {

            switch ($dataType) {

                case self::REDIS_TYPE_STRING:
                    return $this->redis->set($key,$value);
                    break;

                case self::REDIS_TYPE_LIST:
                    return $this->redis->rpush($key,$value);
                    break;

                case self::REDIS_TYPE_SET:
                    return $this->redis->sadd($key, $value);
                    break;

            }

        } catch (Exception | ServerException $e) {

            $operationDetails = new stdClass;
            $operationDetails->action = self::ACTION_STORE_ITEM_IN_REDIS;
            $operationDetails->key = $key;
            $operationDetails->value = $value;
            $operationDetails->dataType = $dataType;
            $this->saveErrorDetailsInLog($e, $operationDetails);

            throw new RedisServiceException($e->getMessage());
        }

        return 0;

    }

    /**
    * Function to increment a counter in redis
     * @param  $key countaining the counter must be an integer
    */
    public function increment($key)
    {
        return $this->redis->incr($key);
    }

// ---- INTERNAL FUNCTIONS ----

    /**
     * Function to get operation details as a string.
     *
     * @param   stdClass    $response   Object with operation details.
     * @return  string                  String with operation details for log purposes.
     */
    protected function getOperationDetailsAsString($response)
    {

        $result = self::LOG_OPERATION_DETAILS;
        if (!empty($response->action)) $result .= "action: $response->action - ";
        if (!empty($response->key)) $result .= "key: $response->key - ";
        if (!empty($response->container)) $result .= "container: $response->container - ";
        if (!empty($response->dataType)) $result .= "dataType: $response->dataType - ";
        if (!empty($response->value)) $result .= "value: $response->value - ";
        if (!empty($response->timestamp)) $result .= "timestamp: $response->timestamp - ";
        return $result;

    }

    /**
     * Function to get stack trace from an Exception.
     *
     * @param  Exception    $ex     Exception Produced.
     * @return String               Return the stack trace of an exception.
     */
    protected function getTraceFromException($ex)
    {

        if(empty($ex)) return "";
        return  $ex->getMessage() . ' ' . $ex->getFile() . ' ' . $ex->getLine();

    }

    /**
     * Function to save Error details in logException details in logs.
     *
     * @param   Exception   $ex         Exception produced.
     * @param   stdClass    $response   Object with operation details.
     */
    protected function saveErrorDetailsInLog($ex, $response)
    {

        $operationErrorDetails = $this->getOperationDetailsAsString($response);
        error_log($operationErrorDetails . self::REDIS_EXCEPTION_PREFIX. $this->getTraceFromException($ex));

    }

}

// ---- RedisServiceException CLASS ----

class RedisServiceException extends Exception
{

    public function __construct($message, $code = 0)
    {
        if(empty($message))
            $message = 'EMPTY_EXCEPTION_MESSAGE';

        parent::__construct($message, $code);
    }

}
