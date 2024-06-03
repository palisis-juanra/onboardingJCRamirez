<?php
function autoload($class) {
    // $class = str_replace('\\', '/', $class);
    require_once __DIR__ . '/controllers/' . $class . '.php';

}
spl_autoload_register('autoload');