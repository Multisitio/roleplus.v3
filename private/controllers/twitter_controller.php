<?php
/**
 */
class TwitterController extends AppController
{
    #
    public function callback() {
        _mail::toAdmin('Twitter callback', _var::return($_GET));
        die;
    }
}
