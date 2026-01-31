<?php
class NotificacionesController extends RegistradosController
{
    public function index()
    {
        $this->notificaciones = (new Notificaciones)->nuevas();
        $this->titulo = t('Notificaciones');
        $this->filtro_filas = '.notificaciones li';
        View::template('ventana');
    }

    public function borrar($donde_idu)
    {
        (new Notificaciones)->borrarUna($donde_idu);
        $this->nuevas();
        View::select('nuevas');
    }

    public function conversaciones($apartado, $idu, $conversaciones_idu)
    {
        (new Notificaciones)->borrarUna($idu);
        Redirect::to('/?conversaciones=' . $conversaciones_idu . '&apartado=' . $apartado);
    }

    public function grupos($idu, $publicaciones_idu)
    {
        (new Notificaciones)->borrarUna($idu);
        $pub = (new Publicaciones)->una($publicaciones_idu);
        Redirect::to('/usuarios/perfil/' . urlencode($pub->apodo) . '/publicacion/' . $publicaciones_idu);
    }

    public function limpiar()
    {
        (new Notificaciones)->borrarTodas();
        Redirect::to('/');
    }

    public function lista()
    {
        $this->notificaciones = (new Notificaciones)->nuevas();
    }

    public function nuevas()
    {
        $this->notificaciones_nuevas = (new Notificaciones)->nuevas();
    }

    public function viejas()
    {
        $this->notificaciones = (new Notificaciones)->viejas();
        View::select('lista');
    }

    public function publicaciones($idu, $publicaciones_idu = '')
    {
        if (!$publicaciones_idu) {
            Redirect::to('/');
        }
        (new Notificaciones)->borrarUna($publicaciones_idu);
        $pub = (new Publicaciones)->una($publicaciones_idu);
        if (empty($pub->idu)) {
            (new Notificaciones)->eliminarUna($publicaciones_idu);
            Session::setArray('toast', t('La publicación ya no está disponible.'));
            return Redirect::to('/');
        }
        Redirect::to('/usuarios/perfil/' . urlencode($pub->apodo) . '/publicacion/' . $publicaciones_idu);
    }

    public function suscribirse()
    {
        (new Suscripciones)->suscribirse();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        View::template(null);
        View::select(null);
        return false;
    }

    public function tareas($idu, $tareas_idu)
    {
        (new Notificaciones)->borrarUna($idu);
        Redirect::to('/registrados/tareas/listar/una/' . $tareas_idu);
    }

    public function unsuscribirse($suscripcion = null)
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        (new Suscripciones)->unsuscribirse($data);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        View::template(null);
        View::select(null);
        return false;
    }

    public function usuarios($idu, $usuarios_idu)
    {
        (new Notificaciones)->borrarUna($idu);
        $usuario = (new Usuarios)->uno($usuarios_idu);
        Redirect::to('/usuarios/perfil/' . urlencode($usuario->apodo));
    }

    // --- NUEVO: envío de prueba de push para el usuario logueado ---
    public function prueba_push()
    {
        if (!Session::get('idu')) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'login requerido']);
            View::template(null); View::select(null);
            return false;
        }

        $idu = Session::get('idu');
        (new Suscripciones)->notificar([
            'usuarios_idu' => $idu,
            'title' => 'ROLEplus · Prueba de notificación',
            'body'  => 'Si ves esto, el push está funcionando en producción.',
            'url'   => '/',
            'icon'  => '/img/logos/icon-192x192.png',
            'badge' => '/img/logos/badge.png',
            'requireInteraction' => false
        ]);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true]);
        View::template(null); View::select(null);
        return false;
    }
}
