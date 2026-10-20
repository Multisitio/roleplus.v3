<?php
namespace PHPMailer\PHPMailer {
    class Exception extends \Exception {}
    #[\AllowDynamicProperties]
    class PHPMailer {
        const CHARSET_UTF8 = 'UTF-8';
        const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
        static $last;
        public $method;
        function __construct($exceptions) { self::$last = $this; }
        static function validateAddress($address) { return filter_var($address, FILTER_VALIDATE_EMAIL); }
        function isSMTP() { $this->method = 'smtp'; }
        function isMail() { $this->method = 'mail'; }
        function __call($method, $args) {}
        function send() { return true; }
    }
}
namespace {
    $folder = sys_get_temp_dir() . '/vigilante-mail-' . bin2hex(random_bytes(8));
    mkdir($folder); file_put_contents($folder . '/autoload.php', '<?php');
    define('VENDOR_PATH', $folder . '/');
    try {
        require $argv[1];
        $_SERVER['HTTP_HOST'] = 'roleplus.app';
        $address = bin2hex(random_bytes(6)) . '@example.com';
        if (!_mail::send($address, 'Prueba', 'Contenido', ['IsText'=>true,'LocalSMTP'=>true]) ||
            \PHPMailer\PHPMailer\PHPMailer::$last->method !== 'smtp' ||
            \PHPMailer\PHPMailer\PHPMailer::$last->Host !== '127.0.0.1' ||
            \PHPMailer\PHPMailer\PHPMailer::$last->SMTPAuth !== false) { throw new \RuntimeException(); }
        _mail::sendText($address, 'Prueba', 'Contenido');
        if (\PHPMailer\PHPMailer\PHPMailer::$last->method !== 'mail') { throw new \RuntimeException(); }
        echo "Correo aislado usa SMTP local; correo habitual conserva su transporte.\n";
    } finally {
        unlink($folder . '/autoload.php'); rmdir($folder);
    }
}
