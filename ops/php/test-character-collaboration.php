<?php
// All model writes target connection-local temporary tables, never real characters.
class Session {
    public static $idu = 'player'; public static $rol = 0;
    public static function get($key) { return $key === 'idu' ? self::$idu : self::$rol; }
    public static function setArray($key,$value) {}
}
class Input { public static function isAjax() { return true; } }
class Historico { public static $events=[]; public function add(...$values) { self::$events[]=$values; } }
function t($s) { return $s; }
class _str { public static function uid() { return bin2hex(random_bytes(6)); } }
class LiteRecord {
    public static $db; public static $fail = false;
    public static function query($sql,$values=[]) {
        if (self::$fail && str_starts_with($sql,'INSERT INTO personajes (')) { throw new RuntimeException('Simulated write failure'); }
        $q=self::$db->prepare($sql); $q->execute($values); return $q;
    }
    public static function first($sql,$values=[]) { return self::query($sql,$values)->fetch(PDO::FETCH_OBJ); }
    public static function all($sql,$values=[]) { return self::query($sql,$values)->fetchAll(PDO::FETCH_OBJ); }
}
$db=LiteRecord::$db=new PDO($argv[1] ?? 'mysql:host=r2-d2;dbname=role_plus;charset=utf8mb4','root',getenv('ROLEPLUS_DB_PASSWORD') ?: '',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach (['personajes','personajes_estado','personajes_usuarios'] as $table) {
    $create=$db->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
    $db->exec(str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$create));
}
require dirname(__DIR__,2).'/private/models/personajes.php';
require dirname(__DIR__,2).'/private/models/personajes_usuarios.php';
function check($ok,$message) { if ( ! $ok) { throw new RuntimeException($message); } }
$m=new Personajes; $links=new Personajes_usuarios;
try { $m->salvar(['fichas_idu'=>'sheet','vida'=>'10']); throw new LogicException('Missing ID silently replaced'); } catch (RuntimeException $e) { check($e->getCode()===400,'Invalid ID response'); }
$data=['fichas_idu'=>'sheet','idu'=>'012345abcdef','vida'=>'10','equipo'=>'espada'];
check($m->salvar($data)==='012345abcdef','Creation ID');
Session::$idu='guardian'; Session::$rol=7;
$m->salvar(['fichas_idu'=>'sheet','idu'=>'012345abcdef','vida'=>'9','equipo'=>'stale','campos'=>'["vida"]']);
check(LiteRecord::first('SELECT usuarios_idu FROM personajes_estado WHERE idu=?',['012345abcdef'])->usuarios_idu==='player','Guardian stole ownership');
check( ! $links->uno('012345abcdef'),'Guardian acquired implicit link');
Session::$idu='player'; Session::$rol=0;
$m->salvar(['fichas_idu'=>'sheet','idu'=>'012345abcdef','vida'=>'10','equipo'=>'arco','campos'=>'["equipo"]']);
$s=$m->estado('sheet','012345abcdef'); check($s['datos']['vida']==='9' && $s['datos']['equipo']==='arco','Different fields overwritten');
$m->salvar(['fichas_idu'=>'sheet','idu'=>'012345abcdef','vida'=>'8','campos'=>'["vida"]']);
check($m->estado('sheet','012345abcdef')['datos']['vida']==='8','Last field save did not win');
LiteRecord::$fail=true;
try { $m->salvar($data); throw new LogicException('Missing failure'); } catch (RuntimeException $e) {}
LiteRecord::$fail=false;
check($m->estado('sheet','012345abcdef')['datos']['vida']==='8','Rollback lost data');
$links->vincular('sheet','012345abcdef');
Session::$idu='guardian'; Session::$rol=7;
try { $links->vincular('sheet','012345abcdef'); throw new LogicException('Stealing a bound character allowed'); } catch (RuntimeException $e) { check($e->getCode()===409,'Wrong linked response'); }
Session::$idu='player'; Session::$rol=0; $links->desvincular('012345abcdef');
Session::$idu='next-player'; $links->vincular('sheet','012345abcdef');
Session::$idu='player';
try { $m->salvar($data); throw new LogicException('Old owner allowed'); } catch (RuntimeException $e) { check($e->getCode()===403,'Wrong owner response'); }
Session::$idu='guardian'; Session::$rol=7; $m->salvar($data);
check(LiteRecord::first('SELECT usuarios_idu FROM personajes_estado WHERE idu=?',['012345abcdef'])->usuarios_idu==='next-player','Guardian stole reassigned owner');
$copy=$m->duplicar($data); check($copy!=='012345abcdef','Duplicate ID reused');
check($db->query('SELECT COUNT(*) FROM personajes')->fetchColumn()==4,'Duplicate rows created');
Session::$idu=null;
try { $m->salvar($data); throw new LogicException('Anonymous write allowed'); } catch (RuntimeException $e) { check($e->getCode()===401,'Wrong session response'); }
Session::$idu='guardian'; Session::$rol=7;
$m->eliminar('012345abcdef');
check($db->query('SELECT COUNT(*) FROM personajes')->fetchColumn()==4,'Deletion destroyed recoverable data');
check(count(Historico::$events)===1,'Deletion was not recorded');
try { $m->salvar($data); throw new LogicException('Stale tab resurrected a deleted character'); } catch (RuntimeException $e) { check($e->getCode()===410,'Wrong tombstone response'); }
try { $m->estado('sheet','012345abcdef'); throw new LogicException('Deleted character still published'); } catch (RuntimeException $e) { check($e->getCode()===404,'Wrong deleted response'); }
echo 'PASS: MySQL InnoDB, create, field merge, last save, rollback, guardian ownership, linking, transfer, duplicate, auth',PHP_EOL;
