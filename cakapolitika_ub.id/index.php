<?php
/**
 * CAKAPOLITIK_UB.ID — front controller
 * All non-file requests are routed here by .htaccess.
 */

define('APP_START', microtime(true));
define('ROOT_PATH', __DIR__);

require ROOT_PATH . '/app/Core/Bootstrap.php';

use App\Core\Bootstrap;
use App\Core\Router;

$config = Bootstrap::init(ROOT_PATH . '/config/config.php');

$router = new Router();
require ROOT_PATH . '/app/routes.php';

$router->dispatch();
