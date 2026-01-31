<?php
/**
 */
class ComentariosController extends RegistradosController
{
	#
	protected function before_filter()
	{
        if ($action = Input::post('action')) {
            unset($_POST['action']);
            if (method_exists($this, $action)) {
                $this->$action();
            }
        }
    }

    #
    public function formatear($confirmar=0)
    {
        $this->comentarios = (new Comentarios)->formatearComentariosSinformatear($confirmar);
        _var::die($this->comentarios);
    }

    #
    public function crear()
    {
        (new Comentarios)->crear($_POST);
    }

    #
    public function editar($idu)
    {
        $comentarios[] = $this->comentario = (new Comentarios)->uno($idu);
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($comentarios);
    }

    #
    public function editado($idu)
    {
        if ($_POST) {
            (new Comentarios)->editar($_POST);
        }
        $comentarios[] = $this->comentario = (new Comentarios)->uno($idu);
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($comentarios);
    }

    #
    public function eliminar($idu)
    {
        (new Comentarios)->eliminar($idu);
        View::select('');
    }

    #
    public function experiencia($comentarios_idu)
    {
        $this->comentario_experiencia = (new Experiencia)->comentarios($comentarios_idu);
    }

    #
    public function sueltos()
    {
        if ($this->usuario->rol < 4) {
            Session::setArray('toast', t('Adquiera el rol Azotamentes.'));
            return Redirect::to('/registrados/tienda');
        }
        $this->comentarios = (new Comentarios)->sueltos();
    }

    #
    public function todos($publicaciones_idu)
    {
        $this->comentarios = (new Comentarios)->todos($publicaciones_idu);
        $this->experiencia = (new Experiencia)->entregada($this->comentarios);
        $this->publicaciones_idu = $publicaciones_idu;
        $this->vistas_previas = (new Vistas_previas)->obtenerTodas($this->comentarios);
    }

    #
    public function eliminar_enlace($idu)
    {
        (new Comentarios)->eliminarEnlace($idu);
        View::select(null);
    }

    #
    public function ver_experiencia($comentarios_idu)
    {
        $this->quienes = (new Experiencia)->enComentario($comentarios_idu);
        $this->titulo = t('Cautivados');
        $this->filtro_filas = '.cautivados li';
        View::template('ventana');
    }
}
