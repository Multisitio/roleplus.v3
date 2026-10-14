<?php
// No DB, network, credentials or real accounts are used in this dependency test.
$app = dirname(__DIR__, 2);
if (is_dir($app . '/private/models')) { $app .= '/private'; }
define('APP_PATH', $app . '/');
// Synthetic values exist only for this run and are never sent to a provider.
$GLOBALS['fixtureOAuth'] = ['client_id'=>bin2hex(random_bytes(12)), 'client_secret'=>bin2hex(random_bytes(16))];
$GLOBALS['fixtureUid'] = bin2hex(random_bytes(12));
$fixture = sys_get_temp_dir() . '/roleplus-vendor-test-' . bin2hex(random_bytes(6)) . '/';
mkdir($fixture);
define('VENDOR_PATH', $fixture);
file_put_contents($fixture . 'autoload.php', <<<'PHP'
<?php
namespace Google;
$GLOBALS['vendor_loads'] = ($GLOBALS['vendor_loads'] ?? 0) + 1;
class Client {
    public static $tokens = [];
    public function setClientId($id) { if ($id !== $GLOBALS['fixtureOAuth']['client_id']) { throw new \RuntimeException('Client ID'); } }
    public function setClientSecret($secret) { if ($secret !== $GLOBALS['fixtureOAuth']['client_secret']) { throw new \RuntimeException('Client secret'); } }
    public function verifyIdToken($token) {
        self::$tokens[] = $token;
        return $token === 'valid' ? ['email'=>'fixture@example.test', 'given_name'=>'Fixture'] : false;
    }
}
PHP
);
class LiteRecord {
    public static $queries = 0;
    public static function first($sql, $values) { self::$queries++; return false; }
}
class Config { public static function get($key) { return json_encode(['web'=>$GLOBALS['fixtureOAuth']]); } }
class Session { public static $toasts = []; public static function setArray($key, $value) { self::$toasts[] = $value; } }
class _mail { public static function send(...$args) {} }
class _str { public static function uid() { return $GLOBALS['fixtureUid']; } }
function t($text) { return $text; }
function check($condition, $message) { if ( ! $condition) { throw new RuntimeException($message); } }
try {
    require APP_PATH . 'models/usuarios.php';
    $user = new Usuarios();
    $_COOKIE = [];
    $user->entrarSiGalleta();
    check(empty($GLOBALS['vendor_loads']), 'Visita sin cookie carga Composer');
    check(LiteRecord::$queries === 0, 'Visita sin cookie consulta usuarios');
    $_COOKIE = ['llave'=>['sesion_clave'=>'invalid', 'sesion_valor'=>'invalid']];
    $user->entrarSiGalleta();
    check(empty($GLOBALS['vendor_loads']) && LiteRecord::$queries === 1, 'La cookie no sigue su validacion independiente');
    class GoogleFlowProbe {
        use UsuariosGoogle;
        public static function identificarse($data, $mode=0) { return ['login', $data, $mode]; }
        public static function registrarse($data, $mode=0) { return ['register', $data, $mode]; }
    }
    $probe = new GoogleFlowProbe();
    $_POST = ['credential'=>'valid'];
    $login = $probe->identificarseConGoogle();
    check(($GLOBALS['vendor_loads'] ?? 0) === 1, 'No se carga el SDK para Google');
    check($login === ['login', ['email'=>'fixture@example.test'], 2], 'Login Google alterado');
    $registration = $probe->registrarseConGoogle();
    check($registration === ['register', ['email'=>'fixture@example.test', 'apodo'=>'Fixture', 'clave'=>$GLOBALS['fixtureUid'], 'terminos'=>1], 2], 'Registro Google alterado');
    $_POST = ['credential'=>'invalid'];
    check($probe->identificarseConGoogle() === null, 'Token invalido admitido en login');
    check($probe->registrarseConGoogle() === null, 'Token invalido admitido en registro');
    check(count(Session::$toasts) === 2 && Google\Client::$tokens === ['valid','valid','invalid','invalid'], 'No se verifican todos los tokens');
    check($GLOBALS['vendor_loads'] === 1, 'Composer cargado mas de una vez');
} finally {
    unlink($fixture . 'autoload.php');
    rmdir($fixture);
    unset($GLOBALS['fixtureOAuth'], $GLOBALS['fixtureUid']);
}
echo "OK: visitas sin Composer; cookie validada; Google login/registro y rechazo de tokens conservados\n";
