<?php

use TourCMS\Utils\TourCMS as TourCMS;


class TourCMSextension extends TourCMS
{

    public function __construct($mp, $k, $res = "raw", $to = 0)
    {
        parent::__construct($mp, $k, $res, $to);
    }

    public function getMarketplaceId()
    {
        return $this->marketp_id;
    }
}
