<?php
/**
 */
class EtiquetasController extends RegistradosController
{
    #
    public function incluir($donde)
    {
        $this->etiquetas = (new Etiquetas)->todas();
        $this->donde = $donde;
        $this->titulo = str_replace('_', ' ', ucfirst($donde));

        $this->filtro_filas = '.modal form.etiquetas label';
        View::template('ventana');
    }

    #
    public function guardar()
    {
        (new Etiquetas_usuarios)->guardar($_POST);
        $this->donde = $_POST['donde'];
        $this->etiquetas_usuario = (new Etiquetas_usuarios)->todas($this->donde);
        View::select('caja');
    }

    #
    public function quitar($hashtag, $donde)
    {
        (new Etiquetas_usuarios)->quitar($hashtag, $donde);
        $this->etiquetas_usuario = (new Etiquetas_usuarios)->todas($donde);
        $this->donde = $donde;
        View::select('caja');
    }
}
