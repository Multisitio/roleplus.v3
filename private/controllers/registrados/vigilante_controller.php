<?php
class VigilanteController extends RegistradosController
{
    public function aviso($referencia = '')
    {
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        $this->title = t('Vigilante del VPS');
        $this->aviso = (new Vigilante)->detalle($referencia, (string) Session::get('idu'));
        if ($this->aviso === null) {
            http_response_code(404);
        }
    }
}
