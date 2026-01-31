<?php
/**
 */
class Historico extends LiteRecord
{
    #
    public function add($alcance, $valor_anterior, $valor_nuevo)
    {
        $vals[] = Session::get('idu');
        $vals[] = $alcance;
        $vals[] = $valor_anterior;
        $vals[] = $valor_nuevo;
        $vals[] = date('Y-m-d H:i:s');
        
        $sql = 'INSERT INTO historico SET usuarios_idu=?, alcance=?, valor_anterior=?, valor_nuevo=?, actualizado=?';
        parent::query($sql, $vals);
    }
}
