<?php
/**
 */
class Article
{
    #
    public static function content($pub)
    {
        if ( ! $pub->contenido_formateado) {
            return;
        }
        ob_start();
        if (_str::count_files($pub->contenido_formateado) > 4): ?>
            <span class="leer-mas-<?=$pub->idu?>">
                <?=_str::truncate_files($pub->contenido_formateado, 4)?>
                <button class="outline" data-toggle=".leer-mas-<?=$pub->idu?>">
                    <?=t('Leer más')?>
                </button>
            </span>
            <span class="leer-mas-<?=$pub->idu?>" style="display:none">
                <?=$pub->contenido_formateado?>
            </span>
        <?php else:
            echo $pub->contenido_formateado;
        endif;
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function event($pub)
    {
        if ( ! $pub->evento_aforo) {
            return;
        }
        ob_start();
        ?>
        <fieldset>
            <legend><?=t('Datos del evento')?></legend>
            <div class="grid">
                <div class="s6">
                    <label><?=t('Desde:')?></label>
                    <span><?=date('H:i · d-m-y', strtotime($pub->evento_desde))?></span>
                </div>
                <div class="s6">
                    <label><?=t('Hasta:')?></label>
                    <span><?=date('H:i · d-m-y', strtotime($pub->evento_hasta))?></span>
                </div>
            </div>
            <hr>
            <div class="apuntados-<?=$pub->idu?>">
                <?php include APP_PATH . 'views/registrados/publicaciones/apuntados.phtml'; ?>
            </div>
        </fieldset>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function images($pub)
    {
        if ( ! $pub->fotos) {
            return;
        }
        $fotos = explode(',', $pub->fotos);
        if (count($fotos) < 2) {
            $fot = $fotos[0];
            $mini = preg_match('/\.(gif|webm)$/i', $fotos[0]) ? '' : 'l.';
            $img = "img/usuarios/$pub->usuarios_idu/$fot";
            if ( ! _file::exists($img)) {
                return;
            }
            ob_start();
            if (preg_match('/\.webm$/i', $fotos[0])) {
                ?><video autoplay controls width="100%">
                    <source src="/img/usuarios/<?=$pub->usuarios_idu?>/<?="$mini$fot"?>" type="video/webm">
                    <a href="/media/cc0-videos/flower.webm">WEBM</a>
                </video><?php
            }
            else {
                ?><a data-ajax=".ajax.show" data-style="body, overflow:hidden" href="/imagenes/ver/<?=$pub->usuarios_idu?>/<?=$fot?>"><img alt="<?=$fot?>" loading="lazy" src="/img/usuarios/<?=$pub->usuarios_idu?>/<?="$mini$fot"?>"></a><?php
            }
            $fotos = ob_get_contents();
            ob_end_clean();
            return $fotos;
        }
        $i = 1;
        ob_start(); ?>
        <picture>
            <?php foreach ($fotos as $fot):
                $fot = trim($fot);
                $mini = preg_match('/\.gif$/i', $fot) ? '' : 'l.';
                $img = "img/usuarios/$pub->usuarios_idu/$fot";
                if ( ! _file::exists($img)) {
                    continue;
                } ?>
                <a data-ajax=".ajax.show" data-style="body, overflow:hidden" href="/imagenes/ver/<?=$pub->usuarios_idu?>/<?=$fot?>"><img alt="<?=$fot?>" loading="lazy" src="/img/usuarios/<?=$pub->usuarios_idu?>/<?="$mini$fot"?>"></a>
                <?php if ($i==2): ?>
                    </picture><picture>
                <?php endif;
                ++$i;
            endforeach; ?>
        </picture>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function link($pub) 
    {
        if (empty($pub->enlace)) {
            return;
        }
        ob_start();
        if (stristr($pub->enlace, 'youtu')): ?>
            <div class="mt15 youtube-player" data-id="<?=_var::getUrlVar($pub->enlace)?>"></div>
        <?php else: ?>
            <section><?=_html::links($pub->enlace)?></section>
        <?php endif;
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function poll($pub, $encuestas, $encuestas_opciones)
    {
        if (empty($pub->encuesta)) {
            return;
        }
        ob_start();
        ?>
        <section>
            <div class="encuesta _<?=$pub->idu?>">
                <?php include APP_PATH . 'views/registrados/publicaciones/votar.phtml'; ?>
            </div>
        </section>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function previews($pub, $vistas_previas, $accion='')
    {
        if (empty($vistas_previas[$pub->idu])) {
            return;
        }
        ob_start();
        ?>
        <section class="vistas-previas <?=$accion?>">
            <?php foreach ($vistas_previas[$pub->idu] as $v_p): ?>
                <div>
                    <a href="<?=$v_p->url?>" rel="noopener noreferrer" target="_blank" title="<?=t('Enlace de la vista previa.')?>"><h3><?=empty($v_p->title)?t('Enlace'):$v_p->title?></h3></a>

                    <?php if ($accion == 'editar'): ?>
                        <a data-ajax=".ajax.hide" data-remove=".vistas-previas.editar" rel="nofollow" href="/registrados/publicaciones/eliminar_vista/<?=$v_p->idu?>" title="<?=t('Quitar vista previa.')?>"><img alt="<?=t('Quitar vista previa.')?>" height="30" loading="lazy" src="/img/icons/x-square.svg" width="30"></a>
                    <?php endif; ?>
                </div>
                
                <?php if ($v_p->description): ?>
                    <small><?=$v_p->description?></small>
                <?php endif; ?>

                <?php
                $image = "img/vistas_previas/$v_p->idu/l.$v_p->image";
                #if (_file::exists($image)): ?>
                    <img alt="<?=t('Imagen de la vista previa.')?>" loading="lazy" src="/<?=$image?>">
                <?php #endif; ?>
            <?php endforeach; ?>
        </section>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    #
    public static function title($pub)
    {
        if ( ! $pub->titulo) {
            return;
        }
        ob_start();
        ?><a class="titulo" href="/publicaciones/<?=$pub->slug?>" title="<?=$pub->titulo?>"><?=$pub->titulo?></a><?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
