<?php
/**
 */
class TranslatorController extends AdminController
{
    # 
    public function index()
    {
        ini_set('max_execution_time', 120000);

        (new Translator)->desactivartodas();

		$idiomas = Config::get('combos.idiomas');
        unset($idiomas['ES']);

        foreach ($idiomas as $iso=>$idioma) {
            _var::flush("<h2>($iso) $idioma</h2>");
            (new Translator)->getEntriesInFiles('', $iso);
        }
        (new Translator)->borrarDesactivadas();
        (new Traducciones)->volcarAFichero();
        exit;
    }
}
