<?php
/**
 */
class Preguntas_respuestas extends LiteRecord
{
	#
	public function guardarRespuesta($post)
	{	
		$sql = 'INSERT INTO preguntas_respuestas SET idu=?, usuarios_idu=?, preguntas_idu=?, respuesta=?, creada=?';

		(new Preguntas_respuestas)->query($sql, [
			_str::uid(),
			Session::get('idu'),
			$post['preguntas_idu'],
			$post['respuesta_a_pregunta_importante'],
			date('Y-m-d H:i:s')
		]);
	}

	#
	public function sinResponder()
	{	
		$pregunta = (new Preguntas)
			->order('creada DESC')
			->where('borrada IS NULL')
			->row();

		if (empty($pregunta->pregunta)) {
			return false;
		}

		$respuesta = (new Preguntas_respuestas)
			->where('usuarios_idu=? AND preguntas_idu=?', [Session::get('idu'), $pregunta->idu])
			->row();

		return empty($respuesta->respuesta) ? $pregunta : false;
	}
}
