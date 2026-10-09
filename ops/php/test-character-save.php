<?php
// In-memory database: never reads or writes a user's characters.
class Session {
    public static $idu = 'test-user';
    public static function get($key) { return $key === 'idu' ? self::$idu : 0; }
    public static function setArray($key, $value) {}
}
function t($value) { return $value; }
class _str { public static function uid() { return bin2hex(random_bytes(6)); } }
class LiteRecord {
    public static $db;
    public static $fail = false;
    public static function query($sql, $values = []) {
        if (self::$fail && str_starts_with($sql, 'INSERT')) throw new RuntimeException('Database failure');
        $stmt = self::$db->prepare($sql === 'START TRANSACTION' ? 'BEGIN' : $sql);
        $stmt->execute($values);
        return $stmt;
    }
    public static function first($sql, $values = []) { return self::query($sql, $values)->fetch(PDO::FETCH_OBJ); }
}
require dirname(__DIR__, 2) . '/private/models/personajes.php';
LiteRecord::$db = new PDO('sqlite::memory:');
LiteRecord::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
LiteRecord::$db->exec('CREATE TABLE personajes (usuarios_idu TEXT, fichas_idu TEXT, idu TEXT, variable_nombre TEXT, variable_valor TEXT)');
function check($condition, $message) { if ( ! $condition) throw new RuntimeException($message); }
$model = new Personajes;
$data = ['fichas_idu' => 'sheet', 'idu' => 'stable-id', 'nombre' => 'First'];
check($model->salvar($data) === 'stable-id', 'Creation ID');
$data['nombre'] = 'Updated';
$model->salvar($data);
check(LiteRecord::query('SELECT COUNT(*) FROM personajes')->fetchColumn() == 1, 'Update duplicated the character');
check(LiteRecord::query('SELECT variable_valor FROM personajes')->fetchColumn() === 'Updated', 'Update missing');
LiteRecord::$fail = true;
try { $model->salvar($data); throw new LogicException('Expected database failure'); } catch (RuntimeException $e) {}
LiteRecord::$fail = false;
check(LiteRecord::query('SELECT variable_valor FROM personajes')->fetchColumn() === 'Updated', 'Failed save lost data');
Session::$idu = 'other-user';
try { $model->salvar($data); throw new LogicException('Expected forbidden'); } catch (RuntimeException $e) { check($e->getCode() === 403, 'Wrong ownership response'); }
Session::$idu = null;
try { $model->salvar($data); throw new LogicException('Expected unauthenticated'); } catch (RuntimeException $e) { check($e->getCode() === 401, 'Wrong authentication response'); }
Session::$idu = 'test-user';
$copy = $model->duplicar($data);
check($copy !== 'stable-id', 'Duplicate reused ID');
check(LiteRecord::query('SELECT COUNT(DISTINCT idu) FROM personajes')->fetchColumn() == 2, 'Duplicate missing');
echo "PASS: create, update, stable ID, rollback, authentication, ownership, duplicate\n";
