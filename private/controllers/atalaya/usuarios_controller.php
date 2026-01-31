<?php
/**
 */
class UsuariosController extends AtalayaController
{
    #
    public function index()
    {
        $this->usuarios = (new Usuarios)->todos(['cols'=>'*']);
        $configuraciones = (new Configuracion)->all();
        $this->configuraciones = (new Configuracion)->groupBy($configuraciones, 'usuarios_idu');
        #_var::die($this->configuraciones);
        
    }

    #
    public function avatar($apodo)
    {
        $this->usu = (new Usuarios)->uno($apodo);
    }

    #
    public function baja_del_boletin($idu)
    {
        (new Configuracion)->asignar($idu, 'no_al_boletin', 1);

        $this->configuraciones[$idu] = (new Configuracion)->todasPorUsuario($idu);
        $this->idu = $idu;

        View::select('configuraciones');
    }

    #
    public function eliminar($idu)
    {
        (new Usuarios)->eliminar($idu);
        View::select(null);
    }

    #
    public function generar_slugs($campo)
    {
        (new Usuarios)->genSlugs($campo);
        exit();
    }

    #
    public function generar_uid($campo)
    {
        (new Usuarios)->genUid($campo);
        exit();
    }

    #
    public function socios()
    {
        $this->niveles = (new Usuarios)->socios();
    }
}
