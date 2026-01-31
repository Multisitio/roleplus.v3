<?php
#
class PaperController extends AtalayaController
{
    private $url = 'https://www.getpapercss.com/docs/components/articles/';

    #
    protected function before_filter()
    {
        View::template('paper');
    }

    #
    public function index()
    {
    }
}
