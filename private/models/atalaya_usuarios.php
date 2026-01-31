<?php
/**
 */
class Atalaya_usuarios extends LiteRecord
{
	#
	public function publicacionesPorGrupo($arreglar)
	{
        $grupos = (new Grupos)->todos();
		foreach ($grupos as $gru) {
			if ($arreglar) {
				$sql = 'SELECT * FROM publicaciones WHERE grupos_idu=? AND contenido NOT LIKE ?';
				$gru->publicaciones_sin_hashtag = (new Publicaciones)->all($sql, [$gru->idu, "%#$gru->hashtag%"]);

				foreach ($gru->publicaciones_sin_hashtag as $pub) {
					$sql = 'UPDATE publicaciones SET contenido=?, contenido_formateado=? WHERE idu=?';

					$contenido = "$pub->contenido\n#$gru->hashtag";

					$contenido_formateado = $pub->contenido_formateado.'<br><a class="tag" href="/publicaciones/buscar/'.$gru->hashtag.'">'.$gru->hashtag.'</a>';

					(new Publicaciones)->query($sql, [$contenido, $contenido_formateado, $pub->idu]);
					
					(new Etiquetas)->crear(['hashtag'=>$gru->hashtag, 'tipo'=>'publicacion_contenido']);

					unset($contenido, $contenido_formateado);
				}
			}
			$sql = 'SELECT * FROM publicaciones WHERE grupos_idu=?';
			$gru->publicaciones = (new Publicaciones)->all($sql, [$gru->idu]);

			$sql = 'SELECT * FROM publicaciones WHERE grupos_idu=? AND contenido LIKE ?';
			$gru->publicaciones_con_hashtag = (new Publicaciones)->all($sql, [$gru->idu, "%#$gru->hashtag%"]);

			#_var::echo([$sql, $gru->idu, "%#$gru->hashtag%", $gru->publicaciones_con_hashtag]);
		}
		#die;
		return $grupos;
	}

	#
	public function borrarUsuario($usuario_idu)
	{
		if ( ! $usuario_idu) {
			return;
		}
		$sql = 'DELETE FROM usuarios WHERE idu=? LIMIT 1';
		(new Usuarios)->query($sql, [$usuario_idu]);
        Session::setArray('toast', t('Usuario eliminado.'));
	}

	#
	public function buscarRegistrosHuerfanos($borrar=0)
	{
		$sql = 'SELECT idu FROM usuarios';
		$usuarios = (new Usuarios)->all($sql);
		foreach ($usuarios as $usu) {
			$in[] = '?';
			$idus[] = $usu->idu;
		}
		$in = implode(',', $in);

		$a['Busquedas'] = (object)['from'=>'buscador', 'where'=>"quien NOT IN ($in)"];
		$a['Carpetas'] = (object)['from'=>'carpetas', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Comentarios'] = (object)['from'=>'comentarios', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Configuracion'] = (object)['from'=>'configuracion', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Conversaciones mensajes'] = (object)['from'=>'conversaciones_mensajes', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Conversaciones usuarios'] = (object)['from'=>'conversaciones_usuarios', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Experiencia entregada'] = (object)['from'=>'experiencia', 'where'=>"de NOT IN ($in)"];
		$a['Experiencia recibida'] = (object)['from'=>'experiencia', 'where'=>"para NOT IN ($in)"];
		$a['Fichas'] = (object)['from'=>'fichas', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Fichas valores'] = (object)['from'=>'fichas_valores', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Fichas variables'] = (object)['from'=>'fichas_variables', 'where'=>"usuarios_idu NOT IN ($in)"];
		#$a['Grupos'] = (object)['from'=>'grupos', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Notificaciones entregadas'] = (object)['from'=>'notificaciones', 'where'=>"de_idu NOT IN ($in)"];
		$a['Notificaciones recibidas'] = (object)['from'=>'notificaciones', 'where'=>"para_idu NOT IN ($in)"];
		$a['Publicaciones'] = (object)['from'=>'publicaciones', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Publicaciones en carpetas'] = (object)['from'=>'carpetas_publicaciones', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Seguidores'] = (object)['from'=>'siguiendo', 'where'=>"idu NOT IN ($in)"];
		$a['Siguiendo'] = (object)['from'=>'siguiendo', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Suscripciones push'] = (object)['from'=>'suscripciones', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Suscrito a grupos'] = (object)['from'=>'usuarios_grupos', 'where'=>"usuarios_idu NOT IN ($in)"];
		$a['Votado en encuestas'] = (object)['from'=>'encuestas_opciones', 'where'=>"usuarios_idu NOT IN ($in)"];

		#$i=0;
		foreach ($a as $k=>$v) {
			if ($borrar and $idus) {
				$sql = "DELETE FROM {$v->from} WHERE {$v->where}";
				$this->query($sql, $idus);
			}
			$sql = "SELECT COUNT(id) as count FROM {$v->from} WHERE {$v->where}";
			$b[$k] = $this->first($sql, $idus)->count;
			#++$i;
			#if ($i==8) _::d($b);
		}
		return $b;
	}

	#
	public function contarRegistros($idu, $borrar=0)
	{
		$a['Busquedas'] = (object)['from'=>'buscador', 'where'=>'quien=?'];
		$a['Carpetas'] = (object)['from'=>'carpetas', 'where'=>'usuarios_idu=?'];
		$a['Comentarios'] = (object)['from'=>'comentarios', 'where'=>'usuarios_idu=?'];
		$a['Configuracion'] = (object)['from'=>'configuracion', 'where'=>'usuarios_idu=?'];
		$a['Conversaciones mensajes'] = (object)['from'=>'conversaciones_mensajes', 'where'=>"usuarios_idu=?"];
		$a['Conversaciones usuarios'] = (object)['from'=>'conversaciones_usuarios', 'where'=>"usuarios_idu=?"];
		$a['Experiencia entregada'] = (object)['from'=>'experiencia', 'where'=>'de=?'];
		$a['Experiencia recibida'] = (object)['from'=>'experiencia', 'where'=>'para=?'];
		$a['Fichas'] = (object)['from'=>'fichas', 'where'=>'usuarios_idu=?'];
		$a['Fichas valores'] = (object)['from'=>'fichas_valores', 'where'=>'usuarios_idu=?'];
		$a['Fichas variables'] = (object)['from'=>'fichas_variables', 'where'=>'usuarios_idu=?'];
		#$a['Grupos'] = (object)['from'=>'grupos', 'where'=>'usuarios_idu=?'];
		$a['Notificaciones entregadas'] = (object)['from'=>'notificaciones', 'where'=>'de_idu=?'];
		$a['Notificaciones recibidas'] = (object)['from'=>'notificaciones', 'where'=>'para_idu=?'];
		$a['Publicaciones'] = (object)['from'=>'publicaciones', 'where'=>'usuarios_idu=?'];
		$a['Publicaciones en carpetas'] = (object)['from'=>'carpetas_publicaciones', 'where'=>'usuarios_idu=?'];
		$a['Seguidores'] = (object)['from'=>'siguiendo', 'where'=>'idu=?'];
		$a['Siguiendo'] = (object)['from'=>'siguiendo', 'where'=>'usuarios_idu=?'];
		$a['Suscripciones push'] = (object)['from'=>'suscripciones', 'where'=>'usuarios_idu=?'];
		$a['Suscrito a grupos'] = (object)['from'=>'usuarios_grupos', 'where'=>'usuarios_idu=?'];
		$a['Votado en encuestas'] = (object)['from'=>'encuestas_opciones', 'where'=>'usuarios_idu=?'];

		foreach ($a as $k=>$v) {
			if ($borrar and $idu) {
				$sql = "DELETE FROM {$v->from} WHERE {$v->where}";
				$this->query($sql, [$idu]);
			}
			$sql = "SELECT COUNT(id) as count FROM {$v->from} WHERE {$v->where}";
			$b[$k] = $this->first($sql, [$idu])->count;
		}
		return $b;
	}

	#
	public function enviarCorreoDeRescate()
	{
		$sql = 'SELECT email,clave FROM usuarios WHERE clave IS NOT NULL AND la_clave IS NULL';
		#$sql = 'SELECT * FROM usuarios WHERE clave IS NOT NULL AND la_clave IS NULL AND email="raul.montejano@multisitio.es"';
		$usuarios = Usuarios::all($sql);
		#_::d($usuarios);
        foreach ($usuarios as $usu) {
			# ENVIANDO CORREO
			$protocol = ($_SERVER["SERVER_PROTOCOL"]=='HTTP/1.1') ? 'http://' : 'https://';
			$url = $protocol . $_SERVER['HTTP_HOST'] . "/usuarios/resetear/$usu->email/" . base64_encode($usu->clave);

			$url2 = $protocol . $_SERVER['HTTP_HOST'] . "/usuarios/borrar/$usu->email/" . base64_encode($usu->clave);

			ob_start();
			?>
Estimado usuario, <?=$apodo?>:

Próximamente R+ 3.0 va a entrar en producción sustituyendo a la actual versión 2.0.

Fieles a nuestras políticas de privacidad y seguridad, procederemos al borrado de todos aquellos usuarios que no hayan interactuado con la plataforma en el último año.
Para interactuar con la plataforma basta con que te identifiques con tu usuario contraseña.

https://roleplus.app/usuarios/formularios#identificarse

De no proceder a dicho proceso de identificación, todo dato relativo a su usuario será eliminado permanentemente sin posibilidad de recuperación, incluyendo los PX que tuviera acumulados.

La fecha límite para identificarse es el 1 de febrero de 2023. 

Si desea darse de baja ahora mismo, siga este enlace:

https://roleplus.app/usuarios/formularios#borrarse

Atentamente, el equipo de R+.

        	<?php
			$body = ob_get_clean();

			$subject = t('El Ordenador le requiere en R+');

			$headers =
				'From: dj@roleplus.app' . "\r\n" .
				'Reply-To: dj@roleplus.app' . "\r\n" .
				'X-Mailer: PHP/' . phpversion();

			$res = _mail::send($usu->email, $subject, $body, $headers);
			_var::echo([$usu->email, $res]);

			while (ob_get_level() > 0) {
				ob_end_flush();
			}
			flush();
		}
		die;
	}
}
