<?php
/**
 */
class PreguntasController extends AtalayaController
{
    #
    public function index()
    {
        $this->preguntas = (new Preguntas)->todas();
    }
}
