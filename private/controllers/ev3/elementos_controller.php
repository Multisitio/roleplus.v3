<?php
/**
 */
class ElementosController extends Ev3Controller
{
    #
    public function index()
    {
        $this->elementos = (new Ev3_elementos)->rows();
    }

    #
    public function salvar_elemento()
    {
        (new Ev3_elementos)->salvar($_POST);
        Redirect::to('/ev3/elementos');
    }
}
