<?php
require_once CORE_PATH . 'kumbia/controller.php';

/**
 * Controlador base (KumbiaPHP).
 * Principios: Minimalista, Elegante y Correcto.
 */
abstract class AppController extends GeneralController
{
    final protected function initialize()
    {
        // 0) Filtro de seguridad JS
        $this->_filtrar_js_recursivo($_GET);
        $this->_filtrar_js_recursivo($_POST);

        // 1) Bloqueo general
        if ($this->action_name !== 'desbloquear' && (new Accesos)->comprobarBloqueo()) {
            View::template('bloqueado');
            return false;
        }

        // 2) Auto-login por cookie si no hay sesión de usuario
        if (!Session::get('idu')) {
            (new Usuarios)->entrarSiGalleta();
        }

        // 3) Datos/acciones comunes de usuario autenticado
        if (Session::get('idu')) {
            if (Input::post('respuesta_a_pregunta_importante')) {
                (new Preguntas_respuestas)->guardarRespuesta(Input::post());
            }
            $this->pregunta_sin_responder = (new Preguntas_respuestas)->sinResponder();
            (new Usuarios)->marcarAcceso();
        }

        // 4) Datos comunes de vistas
        $this->claves  = (new Configuracion)->todas();
        $this->usuario = (new Usuarios)->uno();
        $this->version = '251128';

        // 5) CRÍTICO: liberar el lock de sesión antes de renderizar
        Session::close(); // equivale a session_write_close() si está abierta

        // 6) Template según AJAX
        Input::isAjax() ? View::template('ajax') : View::template('principal');
    }

    final protected function finalize()
    {
        // Nada: si alguna acción vuelve a escribir en sesión,
        // la propia API Session la reabre de forma perezosa.
    }
}
