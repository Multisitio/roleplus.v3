<?php
/**
 * Mejorado con ChatGPT 5 razonador el 26 de octubre de 2025
 */
class ConsejosController extends RegistradosController
{
	#
	protected function before_filter()
	{
		// RIESGO: ejecutar métodos arbitrarios desde POST.
		// Se limita a nombres alfanuméricos y guion bajo.
		if ($action = Input::post('action')) {
			unset($_POST['action']);
			if (preg_match('/^[a-zA-Z0-9_]+$/', $action) && method_exists($this, $action)) {
				$this->$action();
			}
		}
	}

	#
	public function index()
	{
		$this->consejos = (new Publicidad)->todosMisConsejos();
	}

	#
	public function actualizar()
	{
		(new Publicidad)->actualizar($_POST);
		Redirect::to('/registrados/consejos');
	}

	#
	public function arreglo()
	{
		(new Publicidad)->arreglo();
		Redirect::to('/registrados/consejos');
	}

	#
	public function crear()
	{
		(new Publicidad)->crear($_POST);
		Redirect::to('/registrados/consejos');
	}

	#
	public function desactivar($idu)
	{
		(new Publicidad)->desactivar($idu);
		Redirect::to('/registrados/consejos');
	}

	#
	public function eliminar()
	{
		(new Publicidad)->eliminar($_POST['idu']);
		Redirect::to('/registrados/consejos');
	}

	#
	public function formulario($idu = '')
	{
		$this->consejo = (new Publicidad)->miConsejo($idu);
		View::template('ventana');
	}

	#
	public function reactivar($idu)
	{
		(new Publicidad)->reactivar($idu);
		Redirect::to('/registrados/consejos');
	}
}
