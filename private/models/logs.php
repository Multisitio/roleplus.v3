<?php
/**
 */
class Logs extends LiteRecord
{
	# 0
	/*public function __construct()
	{
		$this->mail_alerta = Config::get('variables.mail_alerta');
		$this->get         = $_GET;
		$this->post        = $_POST;
		$this->session     = Config::get('variables.session');
	}

	# 1
	public function perderVida()
	{
		$uno = $this->obtenerAcceso();
		empty($uno->id) ? $this->crearAcceso() : $this->actualizarAcceso($uno);
	}

	# 1.1
	public function obtenerAcceso()
	{
		$vals[] = $_SERVER['REMOTE_ADDR'];

		$sql = 'SELECT * FROM accesos WHERE ip=?';
		return self::first($sql, $vals) ?: parent::cols();
	}

	# 1.2
	public function crearAcceso()
	{
		$vals[] = Session::get('idu');
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = $_SERVER['REMOTE_ADDR'];
		$vals[] = Config::get('variables.referer');
		$vals[] = $_SERVER['HTTP_USER_AGENT'];

		$sql = 'INSERT INTO accesos SET usuarios_idu=?, fecha=?, ip=?, referer=?, browser=?';
		self::query($sql, $vals);
	}

	# 1.3
	public function actualizarAcceso($uno)
	{
		$vals[] = ($uno->pv < 1) ? 0 : --$uno->pv;
		$vals[] = date('Y-m-d H:i:s');
		$vals[] = $_SERVER['REMOTE_ADDR'];

		$sql = 'UPDATE accesos SET pv=?, fecha=? WHERE ip=?';
		self::query($sql, $vals);
	}

	# 2
	public function comprobarBloqueo()
	{
		$uno = $this->obtenerAcceso();
		return ( ! empty($uno->id) && $uno->pv < 1) ? true : false;
	}

    # 3
    public function desbloquear()
    {
        $vals[] = 10;
        $vals[] = $_SERVER['REMOTE_ADDR'];

        $sql = 'UPDATE accesos SET pv=? WHERE ip=?';
        parent::query($sql, $vals);
    }*/
}
