<?php
class Nav{

    public function header($relativeUrl = ''){
        $header = [
            'links' => [
                ['url' => $relativeUrl, 'text' => 'List of Channels', 'active' => 'active'],
                ['url' => $relativeUrl.'/tours', 'text' => 'Tours', 'active' => 'active'],
                ['url' => $relativeUrl.'/about', 'text' => 'About', 'active' => 'active']
            ],
            'logo' => 'https://media.licdn.com/dms/image/D4E0BAQGjUrt29N2Wfw/company-logo_200_200/0/1701772945232/palisis_ag_logo?e=2147483647&v=beta&t=6zPWDs4ZHt4AoRb6gJZmaNRbhvkivy-YAgD6hLp2LYs',
            'home' => $relativeUrl,
         ];
         return $header;
    }

    public function footer($relativeUrl = ''){
        $footer = [
            'links' => [
                ['url' => $relativeUrl, 'text' => 'List of Channels'],
                ['url' => $relativeUrl.'/tours', 'text' => 'Tours'],
                ['url' => $relativeUrl.'/about', 'text' => 'About']
            ]
         ];
            return $footer;
    }

}