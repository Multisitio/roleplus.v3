<?php
# No indexar en Google y otros buscadores para proto.
#header('X-Robots-Tag: noindex');

# La web funciona en el mismo origen. No publicar CORS globalmente: los
# endpoints que lo necesiten deben declarar una lista blanca propia.

const APP_CHARSET = 'UTF-8';
const APP_PATH = 'C:/xampp/htdocs/roleplus.app/private/';
const CMD_PATH = '/usr/local/bin/';
const CORE_PATH = 'C:/xampp/htdocs/KumbiaPHP/core/';
const DOMAIN = 'http:///roleplus.v3/';
const PRODUCTION = false;
const PUB_PATH = 'C:/xampp/htdocs/roleplus.app/public_html/';
const PUBLIC_PATH = '/';
const VENDOR_PATH = 'C:/xampp/htdocs/vendor/';

$url = $_SERVER['PATH_INFO'] ?? '/';

require CORE_PATH.'kumbia/bootstrap.php';
