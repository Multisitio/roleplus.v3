<?php
/**
 */
class RolelocalController extends AppController
{
    public function before_filter()
    {
        ini_set('max_execution_time', 120000);
    }

    #
    public function autorenovar_publi()
    {
        (new Publicidad)->autorenovar();
        View::select(null, null);
    }

    public function boletin($accion='ver')
    {
        $this->accion = $accion;
        if ($accion == 'enviar') {
            $this->usuarios = (new Usuarios)->paraBoletin();
            #_var::die($this->usuarios);
        }

        $this->publicaciones = (new Publicaciones)->semanaAnterior();
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($this->publicaciones);
        $this->encuestas = (new Encuestas)->todas($this->publicaciones);
        $this->encuestas_opciones = (new Encuestas)->opciones($this->encuestas);
        View::select('boletin', null);
    }

    #
    public function oferta($referencia)
    {
        $this->referencia = $referencia;
        View::template(null);
    }

    #
    public function propuesta($pagina)
    {
        if ( ! preg_match('/patrocinadores/', $pagina)) {
            die("<h1>Nada que rascar por aquí.</h1>");
        }
        $this->referencia = $pagina;
        View::select($pagina, null);
    }

    public function roleflix()
    {
        $start_time = microtime(1);
        (new Rolflix_entradas)->cargarSitios();
        throw new KumbiaException(microtime(1)-$start_time);
    }

    /*public function rolefix()
    {
        (new Rolflix_sitios)->arreglo();
        View::select(null, null);
    }*/

    #
    public function rss()
    {
        $start_time = microtime(1);
        (new Rss_entradas)->cargarSitios();
        throw new KumbiaException(microtime(1)-$start_time);
    }

    #
    public function fuerza($referencia)
    {
        $this->referencia = $referencia;
        View::template(null);
    }

    #
    public function mas_fuerza($referencia)
    {
        $_POST['forma'] = $referencia;
        $_POST['detalles'] = $_SERVER['HTTP_REFERER'];

        (new Fuerza)->guardar(Input::post());

        $pagina = ($referencia == 'gracias-de-todas-formas-por-intentar-unirte-a-role-plus-d4e')
            ? 'gracias_de_todas_formas'
            : 'gracias';

        $this->referencia = $referencia;
        View::select($pagina, null);
    }
    /**
     * Importar historial de chat (JSON) para descubrir usuarios silenciosos.
     * 1. Exportar chat en Telegram Desktop (JSON).
     * 2. Subir a private/temp/result.json
     * 3. Ejecutar: php private/bin/kcli.php rolelocal import_history
     */
    public function import_history($file = 'result.json')
    {
        View::select(null, null);
        $path = APP_PATH . 'temp/' . $file;
        
        if (!file_exists($path)) {
            die("Error: No encuentro el archivo: $path\nSúbelo primero.");
        }

        echo "Leyendo $path ...\n";
        $json = json_decode(file_get_contents($path), true);
        
        if (!$json || !isset($json['messages'])) {
            die("Error: JSON inválido o sin mensajes.\n");
        }

        $count = 0;
        $nuevos = 0;
        
        foreach ($json['messages'] as $msg) {
            // Usuario que escribe
            if (isset($msg['from_id']) && strpos($msg['from_id'], 'user') === 0) {
                $uid = substr($msg['from_id'], 4); // user12345 -> 12345
                $alias = $msg['from'] ?? ''; // En el JSON 'from' suele ser el nombre
                
                // Intentamos sacar username real si existe (a veces no viene en export simple)
                // Pero guardamos lo que tenemos
                $r = (new TelegramUsuarios)->set($uid, ['nombre' => $alias]);
            }
            
            // Usuarios que se unen (Service Messages)
            if (isset($msg['actor_id']) && strpos($msg['actor_id'], 'user') === 0) {
                 $uid = substr($msg['actor_id'], 4);
                 $alias = $msg['actor'] ?? '';
                 (new TelegramUsuarios)->set($uid, ['nombre' => $alias]);
            }
            
            $count++;
        }
        
        echo "Procesados $count mensajes.\n";
        echo "Base de datos actualizada. Ahora ejecuta el cron 'sophia' para verificar niveles.\n";
    }

    /**
     * Mantenimiento de Sophia (Grupo Telegram)
     * Ejecutar vía CLI: php private/bin/kcli.php rolelocal sophia
     */
    public function sophia()
    {
        View::select(null, null);
        
        // CONFIGURACION
        $bot_token = Config::get('keys.telegram.bot2_token');
        if (empty($bot_token)) die("Error: Token no encontrado.");

        $group_chat_id = '-1001442242098'; // Grupo Privado
        $admin_chat_id = '316666459'; // ID del Admin Humano

        fwrite(STDOUT, "Iniciando mantenimiento Sophia " . date('Y-m-d H:i:s') . "...\n");
        // $this->sendTelegramMsg($bot_token, $admin_chat_id, "🔧 <b>Iniciando Mantenimiento</b>\nHora: " . date('H:i:s'));

        try {
            $users = (new TelegramUsuarios)->getAll();
        } catch (Error $e) {
            fwrite(STDOUT, "Error cargando modelos: " . $e->getMessage() . "\n");
            return;
        }

        if (empty($users)) {
             fwrite(STDOUT, " [!] La base de datos 'telegram_usuarios' está vacía.\n");
             fwrite(STDOUT, "     Sophia solo puede verificar a los usuarios que hayan hablado alguna vez\n");
             fwrite(STDOUT, "     desde que se instaló este sistema.\n");
             return;
        }

        fwrite(STDOUT, " Revisando " . count($users) . " usuarios...\n");

        foreach ($users as $u) {
            // 1. Verificar si sigue en el grupo
            // 1. Verificar si sigue en el grupo
            $url = "https://api.telegram.org/bot$bot_token/getChatMember?chat_id=$group_chat_id&user_id=$u->id_telegram";
            
            // Allow 400 errors to be read
            $ctx = stream_context_create(['http' => ['ignore_errors' => true]]);
            $res = json_decode(file_get_contents($url, false, $ctx));

            if (!$res || !$res->ok) {
                $desc = $res->description ?? 'Error desconocidio';
                
                // Si el error es que no encuentra al usuario, lo borramos
                if (stripos($desc, 'user not found') !== false || stripos($desc, 'member not found') !== false || stripos($desc, 'participant_id_invalid') !== false || stripos($desc, 'Invalid user_id') !== false) {
                     (new TelegramUsuarios)->query("DELETE FROM telegram_usuarios WHERE id=?", [$u->id]);
                     
                     if ($admin_chat_id) {
                         $msg = "🗑️ <b>Limpieza (Usuario no válido)</b>\n" .
                                "ID: $u->id_telegram\n" .
                                "Info: $desc\n" .
                                "Acción: Eliminado de la BD.";
                         $this->sendTelegramMsg($bot_token, $admin_chat_id, $msg);
                     }
                     fwrite(STDOUT, "Deleted $u->id_telegram ($u->nombre): $desc\n");
                } else {
                     fwrite(STDOUT, "Skip $u->id_telegram: $desc\n");
                }
                continue;
            }

            $status = $res->result->status;

            if (in_array($status, ['left', 'kicked'])) {
                (new TelegramUsuarios)->query("DELETE FROM telegram_usuarios WHERE id=?", [$u->id]);
                
                if ($admin_chat_id) {
                     $nom = $u->alias ? "@$u->alias" : $u->nombre;
                     $msg = "👋 <b>Usuario Salió</b>\n" .
                            "Usuario: $nom\n" .
                            "Estado: $status\n" .
                            "Acción: Registro eliminado de la BD.";
                     $this->sendTelegramMsg($bot_token, $admin_chat_id, $msg);
                }
                
                fwrite(STDOUT, "Deleted $u->id_telegram ($u->nombre): Status $status\n");
                continue;
            }

            if ($status === 'creator' || $status === 'administrator') {
                continue; 
            }

            // 2. Verificar vinculación
            if (empty($u->usuarios_idu)) {
                // A) Auto-Match
                $posibles = [];
                if ($u->alias) $posibles[] = $u->alias;
                if ($u->nombre) $posibles[] = $u->nombre;
                
                $encontrado = null;
                foreach ($posibles as $txt) {
                    $match = (new Usuarios)->first("SELECT * FROM usuarios WHERE apodo = ?", [$txt]);
                    if ($match) {
                        $encontrado = $match;
                        break;
                    }
                }

                if ($encontrado) {
                    (new TelegramUsuarios)->linkUsuario($u->id_telegram, $encontrado->idu);
                    // Also save the alias for consistency
                    (new TelegramUsuarios)->set($u->id_telegram, ['alias' => "R+: $encontrado->apodo"]);

                    fwrite(STDOUT, "Auto-linked $u->alias to {$encontrado->apodo}\n");
                    $u->usuarios_idu = $encontrado->idu;
                } else {
                    // B) Preguntar al Admin
                    if ($admin_chat_id) {
                        $msg = "⚠️ <b>Usuario Desconocido</b>\n" .
                               "ID: {$u->id_telegram}\n" .
                               "Alias: " . ($u->alias ? "@$u->alias" : 'Sin alias') . "\n" .
                               "Nombre: {$u->nombre}\n" .
                               "No encuentro coincidencia en R+. Por favor, <b>responde a este mensaje</b> con el <b>NICK exacto</b> en R+ para vincularlo.";
                        
                        $this->sendTelegramMsg($bot_token, $admin_chat_id, $msg, true);
                        fwrite(STDOUT, "Asked Admin about $u->id_telegram\n");
                    }
                    continue;
                }
            }

            // 3. Verificar Nivel
            if (!empty($u->usuarios_idu)) {
                // ESTADO ESPECIAL: INVITADO (Bots o excepciones manuales) -> Se queda.
                if ($u->usuarios_idu === 'INVITADO') {
                    continue; 
                }

                $userR = (new Usuarios)->first("SELECT * FROM usuarios WHERE idu=?", [$u->usuarios_idu]);
                
                // Si no existe el usuario en R+, EXPULSAR (Orfandad)
                if (!$userR) {
                     file_get_contents("https://api.telegram.org/bot$bot_token/banChatMember?chat_id=$group_chat_id&user_id=$u->id_telegram");
                     file_get_contents("https://api.telegram.org/bot$bot_token/unbanChatMember?chat_id=$group_chat_id&user_id=$u->id_telegram");

                     if ($admin_chat_id) {
                        $nom = $u->alias ? "@$u->alias" : $u->nombre;
                        $msg = "🚫 <b>Expulsión (Usuario no encontrado)</b>\n" .
                               "Usuario TG: $nom\n" .
                               "Motivo: Su cuenta R+ vinculada ya no existe.";
                        $this->sendTelegramMsg($bot_token, $admin_chat_id, $msg);
                     }
                     fwrite(STDOUT, "Kicked Orphan $u->id_telegram (IDU $u->usuarios_idu)\n");
                     continue;
                }

                if ($userR->rol < 4) {
                    // A) EXPULSAR
                    file_get_contents("https://api.telegram.org/bot$bot_token/banChatMember?chat_id=$group_chat_id&user_id=$u->id_telegram");
                    file_get_contents("https://api.telegram.org/bot$bot_token/unbanChatMember?chat_id=$group_chat_id&user_id=$u->id_telegram");
                    
                    // B) NOTIFICAR AL ADMIN
                    if ($admin_chat_id) {
                        $msg = "🚫 <b>Expulsión Realizada</b>\n" .
                               "Usuario: {$userR->apodo}\n" .
                               "Nivel: {$userR->rol} (Requerido: 4)\n" .
                               "Acción: Expulsado del grupo de pago.";
                        $this->sendTelegramMsg($bot_token, $admin_chat_id, $msg);
                    }
                    
                    fwrite(STDOUT, "Kicked {$userR->apodo} (Level {$userR->rol})\n");
                }
            }
            sleep(1); // Evitar rate-limiting
        }
    }

    private function sendTelegramMsg($token, $chat, $text, $force_reply = false) {
        $url = "https://api.telegram.org/bot$token/sendMessage";
        $data = [
            'chat_id' => $chat,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($force_reply) {
            $data['reply_markup'] = json_encode(['force_reply' => true]);
        }
        
        $res = null;
        try {
            if (class_exists('Kttp')) {
                 $res = Kttp::post($url)->formBody($data)->send();
            } else {
                $options = [
                    'http' => [
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method'  => 'POST',
                        'content' => http_build_query($data)
                    ]
                ];
                $context  = stream_context_create($options);
                $res = @file_get_contents($url, false, $context);
            }
        } catch (Throwable $e) {
             fwrite(STDOUT, " [Excepción TG]: " . $e->getMessage() . "\n");
             return;
        }
        
        // Debug respuesta Telegram
        if ($res && is_string($res)) {
             $json = json_decode($res);
             if (!$json || !$json->ok) {
                 fwrite(STDOUT, " [Error TG]: " . ($json->description ?? 'Desconocido') . "\n");
             }
        } else {
             fwrite(STDOUT, " [Error TG]: Sin respuesta o formato inválido.\n");
        }
    }
}
