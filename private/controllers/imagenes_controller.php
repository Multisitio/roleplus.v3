<?php
/**
 */
class ImagenesController extends AppController
{
    # 1
    public function ver($usuario, $img)
    {  
        $this->dir = "usuarios/$usuario";
        $this->img = $img;
        if (Input::isAjax()) {
            View::select('imagen', 'ventana');
        }
        else {
            View::select('imagen', null);
        }
    }

    # 2
    public function vistas_previas($vistas_previas_idu, $img)
    {  
        $this->dir = "vistas_previas/$vistas_previas_idu";
        $this->img = $img;
        View::select('imagen', 'ventana');
    }

    # 3
    public function pixel($referencia, $clave='boletin-23-01|2')
    {  
        $referencia = base64_decode($referencia);
        (new Estadisticas)->insertar($clave, $referencia);
    }
}
