<?php
// Exercise the profile's write ordering without touching a real user/database.
require __DIR__ . '/../../private/models/usuarios/trait_perfil.php';
function t($text) { return $text; }
class Session {
    public static $messages = [];
    public static function get($key) { return 'test-user'; }
    public static function setArray($key, $value) { self::$messages[] = $value; }
}
class Config { public static function get($key) { return ['ES' => 'Español']; } }
class _cookie { public static function add($key, $value) {} }
class _url { public static function slug($value) { return $value; } }
class Historico { public function add(...$args) {} }
class _file {
    public static $calls = [];
    public static $fail = false;
    public static function save($file, $directory, $size, $delete, $mode) {
        self::$calls[] = $mode;
        if (self::$fail) { throw new RuntimeException('Simulated processing error'); }
        return 'new-' . $file['name'];
    }
}
class ProfileDatabase {
    public static $writes = [];
    public static function query($sql, $values) { self::$writes[] = [$sql, $values]; }
    public static function getSlug($table, $value) { return $value; }
}
class ProfileUnderTest extends ProfileDatabase {
    use UsuariosPerfil;
    public static function validar($post) { return true; }
    public static function uno() {
        return (object) ['idu' => 'test-user', 'apodo' => 'tester', 'avatar' => 'old-avatar.gif',
            'fondo_cabecera' => 'old-header.gif', 'fondo_general' => 'old-background.gif'];
    }
}
function check($condition, $message) { if (!$condition) { throw new LogicException($message); } }
$post = ['idioma' => 'ES', 'apodo' => 'tester', 'eslogan' => '', 'sobre_mi' => '',
    'avatar_anterior' => 'old-avatar.gif', 'cabecera_anterior' => 'old-header.gif', 'fondo_anterior' => 'old-background.gif'];
$profile = new ProfileUnderTest();
foreach ([['fondo_general'], ['fondo_cabecera', 'fondo_general']] as $fields) {
    $_FILES = [];
    ProfileDatabase::$writes = [];
    foreach ($fields as $field) { $_FILES[$field] = ['name' => $field . '.gif', 'error' => UPLOAD_ERR_OK]; }
    check($profile->guardarPerfil($post), 'Valid upload rejected');
    $values = ProfileDatabase::$writes[1][1];
    check($values[5] === 'old-avatar.gif', 'Unchanged avatar lost');
    check($values[7] === 'new-fondo_general.gif', 'Background not saved');
    check($values[6] === (count($fields) === 2 ? 'new-fondo_cabecera.gif' : 'old-header.gif'), 'Header not preserved/saved');
}
check(count(array_unique(_file::$calls)) === 1 && _file::$calls[0] === 'original', 'GIFs still converted');
foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL] as $error) {
    ProfileDatabase::$writes = [];
    $_FILES = ['fondo_general' => ['name' => 'large.gif', 'error' => $error]];
    check(!$profile->guardarPerfil($post) && !ProfileDatabase::$writes, 'Failed upload changed profile');
}
ProfileDatabase::$writes = [];
_file::$fail = true;
$_FILES = ['fondo_general' => ['name' => 'bad.gif', 'error' => UPLOAD_ERR_OK]];
check(!$profile->guardarPerfil($post) && !ProfileDatabase::$writes, 'Processing exception changed profile');
$_FILES = [];
$post['fondo_anterior'] = '';
check($profile->guardarPerfil($post), 'Removal rejected');
check(ProfileDatabase::$writes[1][1][7] === '', 'Removed background reference retained');
echo "PASS: background only, both GIFs, upload errors, processing exception, removal\n";
