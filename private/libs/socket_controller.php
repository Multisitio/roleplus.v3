<?php
require_once CORE_PATH . 'kumbia/controller.php';
/**
 */
class SocketController extends Controller
{
    #
    final protected function initialize()
    {
        # CORS
        /*header('Access-Control-Allow-Credentials: true');
        header("Access-Control-Allow-Headers: *");
        header("Access-Control-Allow-Methods: *");
        header("Access-Control-Allow-Origin: *");
        header("Allow: DELETE, GET, HEAD, OPTIONS, POST, PUT");*/
    }

    #
    final protected function finalize()
    {

    }
}
