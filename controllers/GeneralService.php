<?php

class GeneralService
{
    function getXMLFromValidation($url, $paramsFromGet)
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
}
