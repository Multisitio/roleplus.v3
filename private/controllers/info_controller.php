<?php
/**
 */
class InfoController extends AppController
{
    #
    public function contacto()
    {
        if ( Input::post('contacto') )
        {
            if ( Input::post('terminos') and ! Input::post('telefono') and ! Input::post('direccion') )
            {
                # ENVIANDO CORREO
                $body = Input::post('nombre') . ' ' . t('escribió:') . "\n\n";
                $body .= Input::post('motivo');

                $c['from'] = Input::post('correo');
                $c['subject'] = 'Contacto con tacto a Rol+';
                $c['body'] = $body;
                _mail::send($c['from'], $c['subject'], $c['body']);
                Session::setArray('toast', t('Pronto recibirá una respuesta.'));
            }
            elseif ( ! Input::post('terminos') )
            {
                Session::setArray('toast', 'Por favor, acepte los términos.');
            }
            Redirect::to('/');
        }
    }

    #
    public function responder($respuestas_idu='')
    {
        $this->respuesta = '';
        if ($_POST) {
            $this->respuesta = (new Respuestas)->responderPregunta($_POST);
        }
        if ($respuestas_idu) {
            $this->respuesta = (new Respuestas)->obtenerPregunta($respuestas_idu)->respuesta;
        }
    }

    #
    public function ventana($archivo)
    {
        if ( ! preg_match('(contacto|filosofia|ia|nosotros|presentacion|privacidad|publicidad|reacciones|respuestas|sustento|tecnico)', $archivo) ) $archivo = 'politicas';

        $encabezados =
        [
            'contacto'=>t('Cuénteme que sucede'),
            'filosofia'=>t('Premisas y principios'),
            'ia'=>t('i-A'),
            'nosotros'=>t('Nosotros'),
            'politicas'=>t('Políticas'),
            'presentacion'=>t('Presentación'),
            'privacidad'=>t('Privacidad'),
            'publicidad'=>t('Publicidad'),
            'reacciones'=>t('Reacciones en publicaciones'),
            'respuestas'=>t('Preguntas y respuestas'),
            'sustento'=>t('Pilares de financiación'),
            'tecnico'=>t('Informe técnico'),
        ];
        $this->archivo = $archivo;

        $this->titulo = $encabezados[$archivo];
        View::select($archivo);

        Input::isAjax()
            ? View::template('ventana')
            : View::select('ventana');
    }
}
