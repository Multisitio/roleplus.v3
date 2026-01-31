<?php
use Kumbia\ActiveRecord\LiteRecord as ORM;
use Kumbia\ActiveRecord\QueryGenerator;
/**
 */
class LiteRecord extends ORM
{
    #
	public static function arrayBy($arr_old, $field='idu')
	{
        $arr_new = [];
        foreach ($arr_old as $obj) {
            $arr_new[$obj->$field] = $obj;
        }
		return $arr_new;
	}

    #
	public static function groupBy($arr_old, $field)
	{
        $arr_new = [];
        foreach ($arr_old as $obj) {
            $arr_new[$obj->$field][] = $obj;
        }
		return $arr_new;
	}

    #
	public static function cols()
	{
		$source = static::getSource();
		$rows = self::all("DESCRIBE $source");
		$a = [];
		foreach ($rows as $row) {
			$a[$row->Field] = '';
		}
		return (object)$a;
	}

    #
	public static function count(string $where='', array $values=[])
    {
        $source = static::getSource();
        $sql = QueryGenerator::count($source, $where);
        $sth = static::query($sql, $values);
        return $sth->fetch()->count;
	}

	#
	/*public static function count(string $sql='', array $values=[]) : int
    {
        $source = static::getSource();
		$query = $source::query("SELECT COUNT(*) AS count FROM ($sql) AS t", $values)->fetch();
        return (int) $query->count;
	}*/

    #
    public static function emptyField($field) {
        $source = static::getSource();

        $sql = "UPDATE $source SET $field=?";
        static::query($sql, [null]);
    }

    #
    public static function genUid($field) {
        $source = static::getSource();
        $rows = $source::all();
        foreach ($rows as $row) {
            $vals[] = _str::uid();
            $vals[] = $row->id;

            $sql = "UPDATE $source SET $field=? WHERE id=?";
            static::query($sql, $vals);

            _var::flush([$sql, $vals]);
            $vals = [];
        }
    }

    #
    public static function genSlugs($field, $field_if_empty='') {
        $source = static::getSource();
        $sql = "SELECT * FROM $source WHERE slug IS NULL ORDER BY slug";
        $rows = $source::all($sql);
        foreach ($rows as $row) {
            if (empty($row->$field) && $field_if_empty) {
                $row->$field = _str::truncate($row->$field_if_empty, 9);
                if (strstr($row->$field, 'http')) {
                    $row->$field = strstr($row->$field, 'http', true);
                }
            }
            if (empty($row->$field)) {
                $row->$field = $row->idu;
            }
            $slug = _url::slug($row->$field);

            $vals[] = self::getSlug($source, $slug);
            $vals[] = $row->id;

            $sql = "UPDATE $source SET slug=? WHERE id=?";
            static::query($sql, $vals);

            _var::flush([$sql, $vals]);
            $vals = [];
        }
    }

    #
    public static function getSlug($table, $slug, $n='')
    {
        $slug_n = empty($n) ? $slug : "$slug-$n";
        $sql = "SELECT * FROM $table WHERE slug=?";
        $row = parent::first($sql, [$slug_n]);
        if ($row) {
            $n = empty($n) ? 1 : ++$n;
            return self::getSlug($table, $slug, $n);
        }
        return $slug_n;
    }

    #
	public static function getValue(string $col='')
    {
        $source = static::getSource();
		if ($source == 'usuarios') {
			$sql = "SELECT * FROM $source WHERE idu=?";
		}
		else {
			$sql = "SELECT * FROM $source WHERE usuarios_idu=?";
		}
        $row = (object)parent::first($sql, [Session::get('rol')]);
		return $row->$col;
	}

    #
	public static function setValue(string $col='', string $val='')
    {
        $source = static::getSource();
		if ($source == 'usuarios') {
			$sql = "UPDATE $source SET $col=? WHERE idu=?";
		}
		else {
			$sql = "UPDATE $source SET $col=? WHERE usuarios_idu=?";
		}
        static::query($sql, [$val, Session::get('rol')]);
	}

    #
	public static function validate($type, $var)
    {
		if ($type == 'email') {
			$var = filter_var($var, FILTER_VALIDATE_EMAIL);
			$var = mb_strtolower($var);
		}
		return $var;
	}

	# R1
	public static function tables()
    {
		$sql = 'SHOW TABLES';
		$rows = self::all($sql);
		foreach ($rows as $row) {
			$tables[] = array_values((array)$row)[0];
		}
		return $tables;
	}

	# R2
	public function select()
    {
		$fields = $this->fields ?? '*';
		$table = mb_strtolower(get_class($this));

		$this->sql = "SELECT $fields FROM $table";

		if ( ! empty($this->where)) {
			$this->sql .= $this->where;
		}

		if ( ! empty($this->order)) {
			$this->sql .= $this->order;
		}
	}

	# R2a
	public function fields($fields)
    {
		$this->fields = $fields;
		return $this;
	}

	# R2b
	public function where($where, $vals=[])
    {
		$this->vals = $vals;
		$this->where = " WHERE $where";
		return $this;
	}

	# R2c
	public function order($order)
    {
		$this->order = " ORDER BY $order";
		return $this;
	}

	# R2d1
	public function row($id=0)
    {
		/*if ( ! $id && empty($this->vals)) {
			return self::cols();
		}
		else*/
		if ($id) {
			$this->where('id=?', [$id]);
		}

		self::select();
		$row = empty($this->vals)
			? parent::first($this->sql) : parent::first($this->sql, $this->vals);

		return empty($row) ? self::cols() : $row;
	}

	# R2d2
	public function rows()
    {
		self::select();
		return empty($this->vals)
			? self::all($this->sql) : self::all($this->sql, $this->vals);
	}

	# R3
	public function parents()
    {
		self::select();
		$rows = self::all($this->sql);
		foreach ($rows as $row) {
			foreach ($row as $key=>$val) {
				$fields[] = "$key: " . _str::truncate($val, 3);
			}
			$result[$row->aid] = implode(', ', $fields);
			$fields = [];
		}
		return $result;
	}

	#
	public function byParents($parents, $by='parents_idu')
	{
		$keys = $vals = [];
		foreach ($parents as $par) {
			$keys[] = '?';
			$vals[] = $par->idu;
		}
		$keys = implode(', ', $keys);

		if ( ! $keys) {
			return [];
		}

		$table = mb_strtolower(get_class($this));
		$sql = "SELECT * FROM $table WHERE $by IN ($keys)";
		$rows = self::all($sql, $vals);
		$result = [];
		foreach ($rows as $row) {
			$result[$row->$by][] = $row;
		}
		return $result;
	}
}
