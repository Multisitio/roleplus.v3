<?php
/**
 */
class Atalaya_vistas_previas extends LiteRecord
{
	# 1
	public function todas()
	{
		$sql = 'SELECT id FROM vistas_previas';
		return parent::all($sql);
	}

	# 2
	public function enComentarios()
	{
		$sql = 'SELECT v.id FROM vistas_previas v, comentarios c WHERE v.donde_idu=c.idu';
		return parent::all($sql);
	}

	# 3
	public function enPublicaciones()
	{
		$sql = 'SELECT v.id FROM vistas_previas v, publicaciones p WHERE v.donde_idu=p.idu';
		return parent::all($sql);
	}

	# 4
	public function huerfanas($limpiar=0, $asignar_usuario=0)
	{
		$sql = 'SELECT idu, usuarios_idu FROM comentarios';
		$comentarios = parent::all($sql);
		$array['comentarios'] = parent::arrayBy($comentarios);

		$sql = 'SELECT idu, usuarios_idu FROM publicaciones';
		$publicaciones = parent::all($sql);
		$array['publicaciones'] = parent::arrayBy($publicaciones);

		$sql = 'SELECT id, usuarios_idu, donde_idu FROM vistas_previas';
		$vistas_previas = parent::all($sql);

		$contador = 0;
		foreach ($vistas_previas as $v_p) {
			if ( ! empty($array['comentarios'][$v_p->donde_idu])) {
				$com_usuarios_idu = $array['comentarios'][$v_p->donde_idu]->usuarios_idu;
				if ($asignar_usuario && ! $v_p->usuarios_idu) {
					$sql = 'UPDATE vistas_previas SET usuarios_idu=? WHERE id=?';
					parent::query($sql, [
						$array['comentarios'][$v_p->donde_idu]->usuarios_idu,
						$v_p->id,
					]);
				}
				continue;
			}

			if ( ! empty($array['publicaciones'][$v_p->donde_idu])) {
				$pub_usuarios_idu = $array['publicaciones'][$v_p->donde_idu]->usuarios_idu;
				if ($asignar_usuario && ! $v_p->usuarios_idu) {
					$sql = 'UPDATE vistas_previas SET usuarios_idu=? WHERE id=?';
					parent::query($sql, [$pub_usuarios_idu, $v_p->id]);
				}
				continue;
			}

			if ($limpiar) {
				$sql = 'DELETE FROM vistas_previas WHERE id=?';
				parent::query($sql, [$v_p->id]);
			}
			++$contador;
		}
		return $contador;
	}
}