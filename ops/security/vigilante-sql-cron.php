<?php
/** ISPConfig conserva la programacion; el controlador realiza las copias SQL. */
class cronjob_backup extends cronjob
{
    protected $_schedule = '*/15 * * * *';
    protected $_max_runtime = 0;

    public function onRunJob()
    {
        global $app, $conf;
        if ((int) $conf['server_id'] !== 1) {
            throw new RuntimeException('Servidor SQL fuera del alcance revisado');
        }
        $output = [];
        $code = 1;
        exec('/usr/bin/systemctl start --no-block vigilante-sql-schedule.service 2>&1', $output, $code);
        if ($code !== 0) {
            $app->log('No se pudo solicitar la copia SQL al controlador central', LOGLEVEL_ERROR);
            throw new RuntimeException('No se acepto la solicitud de copia SQL');
        }
        parent::onRunJob();
    }
}
