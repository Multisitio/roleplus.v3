<?php
/**
 */
class ProbandoController extends AtalayaController
{
    # 0
	protected function before_filter()
	{
        $this->url_base = implode('/', [
            $this->module_name,
            $this->controller_name,
            $this->action_name,
        ]);
        View::template('kumbia_css');
    }

    # 1
    public function kumbia_css($slug='')
    {
        $this->aside_links = (new Kumbia_css)->links();
        $this->slug = $slug ?: 'introduction';
        View::select($this->slug);
    }
}
