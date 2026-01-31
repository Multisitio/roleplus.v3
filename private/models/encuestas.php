<?php
#
class Encuestas extends LiteRecord
{
	#
	public function actualizar($publicaciones_idu, $opciones)
	{	
		$this->eliminar($publicaciones_idu);
		$this->crear($publicaciones_idu, $opciones);
	}

	#
	public function crear($publicaciones_idu, $opciones)
	{	
		$sql = 'INSERT INTO encuestas (publicaciones_idu, opcion, opciones_idu) VALUES';
		foreach ($opciones as $opc)
		{
			if ( ! $opc) {
				continue;
			}
			$sql .= ' (?, ?, ?),';
			$values[] = $publicaciones_idu;
			$values[] = $opc;
			$values[] = _str::uid($opc);
		}
        $sql = rtrim($sql, ',');
		self::query($sql, $values);
	}

	#
	public function eliminar($publicaciones_idu)
	{	
		$sql = 'DELETE FROM encuestas WHERE publicaciones_idu=?';
		self::query($sql, [$publicaciones_idu]);
		$sql = 'DELETE FROM encuestas_opciones WHERE publicaciones_idu=?';
		self::query($sql, [$publicaciones_idu]);
	}

	#
	public function opciones($encuestas)
	{
		if ( ! $encuestas) {
			return [];
		}
		$keys = [];
		$vals[] = Session::get('idu');
		foreach ($encuestas as $encuestas_idu=>$opciones) {
			$keys[] = '?';
			$vals[] = $encuestas_idu;
		}
		$keys = implode(', ', $keys);
		$sql = "SELECT publicaciones_idu, opciones_idu FROM encuestas_opciones WHERE usuarios_idu=?";
		if ($keys) {
 			$sql .= " AND publicaciones_idu IN ($keys)";
		}
		$sql .= ' ORDER BY id DESC';
		$todas = self::all($sql, $vals);
		$opciones_elegidas = [];
		foreach ($todas as $una) {
			$opciones_elegidas[$una->publicaciones_idu][$una->opciones_idu] = 1;
		}
		return $opciones_elegidas;
	}

	#
	public function salvar($publicaciones_idu, $opciones)
	{	
		$sql = 'SELECT id FROM encuestas WHERE publicaciones_idu=?';
		self::first($sql, [$publicaciones_idu])
			? $this->actualizar($publicaciones_idu, $opciones)
			: $this->crear($publicaciones_idu, $opciones);
	}
	
	#
	public function todas($publicaciones)
	{
		$keys = $vals = [];
		foreach ($publicaciones as $pub) {
			$keys[] = '?';
			$vals[] = $pub->idu;
		}
		$keys = implode(', ', $keys);

		if ( ! $keys) {
			return [];
		}

		$sql = "SELECT * FROM encuestas WHERE publicaciones_idu IN ($keys)";
		$todas = self::all($sql, $vals);

		$encuestas = [];
		foreach ($todas as $una) {
			$encuestas[$una->publicaciones_idu][$una->opciones_idu] = $una;
		}
		return $encuestas;
	}
	
	#
	public function votando($publicaciones_idu)
	{
        $sql = 'SELECT usu.idu as usuarios_idu, usu.apodo, usu.hashtag, usu.avatar, usu.email, usu.eslogan FROM encuestas_opciones e_o, usuarios usu WHERE e_o.publicaciones_idu=? AND e_o.usuarios_idu=usu.idu';
		$cat = self::all($sql, [$publicaciones_idu]);
		foreach ($cat as $obj) {
			$votantes[] = $obj;
		}
		return $votantes;
	}
	
	#
	public function votar($publicaciones_idu, $opciones_idu)
	{
		$sql = 'SELECT * FROM encuestas_opciones WHERE publicaciones_idu=? AND usuarios_idu=?';
		$una = self::first($sql, [$publicaciones_idu, Session::get('idu')]);
		if ($una)
		{
			$sql = 'UPDATE encuestas_opciones SET opciones_idu=? WHERE publicaciones_idu=? AND usuarios_idu=?';
			self::query($sql, [$opciones_idu, $publicaciones_idu, Session::get('idu')]);
			Session::setArray('toast', '¡Voto actualizado!');
		}
		else 
		{
			$sql = 'INSERT INTO encuestas_opciones SET publicaciones_idu=?, opciones_idu=?, usuarios_idu=?';
			self::query($sql, [$publicaciones_idu, $opciones_idu, Session::get('idu')]);
			Session::setArray('toast', '¡Has votado!');
		}

		$sql = 'SELECT * FROM encuestas_opciones WHERE publicaciones_idu=?';
		$opciones = self::all($sql, [$publicaciones_idu]);
		$votos = [];
		foreach ($opciones as $opc)
		{
			$votos[$opc->publicaciones_idu][$opc->opciones_idu] = empty($votos[$opc->publicaciones_idu][$opc->opciones_idu])
				? 1
				: ++$votos[$opc->publicaciones_idu][$opc->opciones_idu];
		}
		
		$sql = 'SELECT * FROM encuestas WHERE publicaciones_idu=?';
		$opciones = self::all($sql, [$publicaciones_idu]);
		foreach ($opciones as $opc)
		{
			$votos_total = empty($votos[$opc->publicaciones_idu][$opc->opciones_idu])
				? 0
				: $votos[$opc->publicaciones_idu][$opc->opciones_idu];
			$sql = 'UPDATE encuestas SET votos=? WHERE publicaciones_idu=? AND opciones_idu=?';
			self::query($sql, [$votos_total, $opc->publicaciones_idu, $opc->opciones_idu]);
		}
	}
}
