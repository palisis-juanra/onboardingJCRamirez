<?php
class Templates
{
    private $m;
    private $nav;
    private $channels;

    public function __construct()
    {
        $MUSTACHE_OPTIONS =  ['extension' => '.html'];
        $MUSTACHE_TEMPLATES_DIR = dirname(__DIR__) . '/views';
        $MUSTACHE_PARTIALS_DIR = dirname(__DIR__) . '/views/partials';

        $this->m = (new \Mustache_Engine(
            [
                'loader' => new \Mustache_Loader_FilesystemLoader($MUSTACHE_TEMPLATES_DIR, $MUSTACHE_OPTIONS),
                'partials_loader' => new \Mustache_Loader_FilesystemLoader($MUSTACHE_PARTIALS_DIR, $MUSTACHE_OPTIONS),
            ]
        ));
        $this->nav = new Nav();
    }

    public function render($template, $data)
    {
        // $template = @file_get_contents('views' . $template . '.html');
        // if ($template === false) {
        //     $template = file_get_contents('views/404.html');
        // }
        return $this->m->render($template, $data);
    }

    public function getPageUrl()
    {
        $url = explode('?', $_SERVER['REQUEST_URI']);
        $url = explode('/', $url[0]);
        $page = $url[count($url) - 1];
        return ($page == 'index.php') ? 'channels' : $page;
    }

    private function getIndex()
    {
        $final = '';
        $url = explode('?', $_SERVER['REQUEST_URI']);
        $url = explode('/', $url[0]);
        for ($i = 1; $i < count($url); $i++) {
            if ($url[$i] == 'index.php') {
                $final .= '/' . $url[$i];
                break;
            }
            $final .= '/' . $url[$i];
        }
        return ($final);
    }

    public function getData($page)
    {

        $url = $this->getIndex();
        $data['nav']['header'] = $this->nav->header($url);
        $data['nav']['footer'] = $this->nav->footer($url);
        switch ($page) {
            case 'channels':
                $data['content'] = [
                    'title' => 'List of Channels',
                ];
                break;
            case 'tours':
                $data['content'] = [
                    'title' => 'Select a Tour',
                    'heading' => 'Select a Tour',
                    'body' => 'This is the tours page'
                ];
                break;
            case 'singleTour':
                $data['content'] = [
                    'title' => 'Tour Details',
                    'heading' => 'Tour Details',
                    'body' => 'This is the single tour page'
                ];
                break;
            case 'formCustomers':
                $data['content'] = [
                    'title' => 'Customer Form',
                    'heading' => 'Customer Form',
                    'body' => 'This is the customer form page'
                ];
                break;
            case 'bookingDetails':
                $data['content'] = [
                    'title' => 'Booking Details',
                    'heading' => 'Booking Details',
                    'body' => 'This is the booking details page'
                ];
                break;
            case 'updateCustomer':
                $data['content'] = [
                    'title' => 'Update Customer',
                    'heading' => 'Update Customer',
                    'body' => 'This is the update customer page'
                ];
                break;
            case 'about':
                $data['content'] = [
                    'title' => 'About',
                    'heading' => 'About',
                    'body' => 'About'
                ];
                break;
            default:
                $data['content'] = [
                    'title' => '404',
                    'heading' => '404',
                    'body' => 'Page not found!'
                ];
                break;
        }
        return $data;
    }
}
