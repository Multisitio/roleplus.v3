<?php
/**
 */
class Pico
{
    # Métodos principales
    #
    private static function arrayToStr(array $array) : string
    {
        if ( ! $array) {
            return '';
        }
        $str = '';
        foreach ($array as $key=>$val) {
            $str .= " $key=\"$val\"";
        }
        return $str;
    }
    
    #
    public static function closeTag($tag) : string
    {
        return "</$tag>";
    }
    
    #
    public static function openTag($tag, $args=[]) : string
    {
        $params = self::arrayToStr($args);
        return "<$tag$params>";
    }

    #
    public static function returnTag($content) : string
    {
        ob_end_clean();
        return $content;
    }
    
    #
    public static function tag($tag, $content='', $args=[]) : string
    {
        return self::openTag($tag, $args) . $content . self::closeTag($tag);
    }

    # Métodos secundarios
    #
    public static function boxButtonSubmit($label, $content, $name='', $value='') : string
    {
        ob_start();
        ?><label>
            <?=$label?>
            <?=Pico::buttonSubmit($content, $name, $value)?>
        </label><?php
        return Pico::returnTag(ob_get_contents());
    }

    #
    public static function boxInputFile($label, $name, $file_dir='', $file_name='') : string
    {
        $class = empty($file_name) ? '' : ' dropimagehover dropnocontent'; 
        $style = empty($file_name)
            ? ''
            : 'style="background:url('."/$file_dir$file_name".') center no-repeat; background-size:cover"'; 
        ob_start();
        ?><label>
            <?=$label?>
            <div class="dropimage<?=$class?>"<?=$style?>>
                <?=Pico::inputFile($name)?>
                <?=Pico::inputHidden($name, $file_name)?>
                <?=self::buttonTransparent(self::img(t('Quitar imagen'), '/img/icons/x-square.svg'), ['class'=>'tr-15'])?>
            </div>
        </label><?php
        return Pico::returnTag(ob_get_contents());
    }

    #
    public static function boxInputText($label, $name, $value='') : string
    {
        ob_start();
        ?><label>
            <?=$label?>
            <?=Pico::inputText($name, $value)?>
        </label><?php
        return Pico::returnTag(ob_get_contents());
    }

    #
    public static function boxTextarea($label, $name, $value='') : string
    {
        ob_start();
        ?><label>
            <?=$label?>
            <?=Pico::textarea($name, $value)?>
        </label><?php
        return Pico::returnTag(ob_get_contents());
    }

    #
    public static function buttonOutlineToggle($content, $selector, $class='') : string
    {
        $span['class'] = 'truncate';
        $content = self::tag('span', $content, $span);

        $button['type'] = 'button';
        $button['class'] = "outline $class";
        $button['data-toggle'] = $selector;
        return self::tag('button', $content, $button);
    }

    #
    public static function buttonSubmit($content, $name='', $value='') : string
    {
        $span['class'] = 'truncate';
        $content = self::tag('span', $content, $span);

        $button['type'] = 'summit';
        $button['name'] = $name;
        $button['value'] = $value;
        return self::tag('button', $content, $button);
    }

    #
    public static function buttonTransparent($content, $args=[]) : string
    {
        $args['type'] = 'button';
        $args['class'] = 'transparent';
        return self::tag('button', $content, $args);
    }
    
    #
    public static function externalLinkButton($title, $href, $content="", $args=[]) : string
    {        
        $content = empty($content) ? $title : $content;
        $args['role'] = 'button';
        $args['href'] = $href;
        $args['rel'] = 'noopener noreferrer';
        $args['target'] = '_blank';
        $args['title'] = $title;
        return self::tag('a', $content, $args);
    }

    public static function externalLinkButtonOutline($title, $href, $content="") : string
    {        
        $args['class'] = 'outline';
        return self::externalLinkButton($title, $href, $content, $args);
    }

    #
    public static function formOpen($action) : string
    {        
        $args['action'] = $action;
        $args['method'] = 'post';
        return self::openTag('form', $args);
    }

    #
    public static function img($alt, $src, $width='', $height='') : string
    {
        $args['alt'] = $alt;
        $args['loading'] = empty($args['loading']) ? 'lazy' : $args['loading'];
        $args['src'] = $src;
        if ($width) $args['width'] = $width;
        if ($height) $args['height'] = $height;
        return self::openTag('img', $args);
    }

    #
    public static function inputFile($name) : string
    {        
        $args['type'] = 'file';
        $args['name'] = $name;
        return self::openTag('input', $args);
    }

    #
    public static function inputHidden($name, $value) : string
    {
        $args['type'] = 'hidden';
        $args['name'] = $name;
        $args['value'] = $value;
        return self::openTag('input', $args);
    }

    #
    public static function inputText($name, $value='') : string
    {
        $args['type'] = 'text';
        $args['name'] = $name;
        $args['value'] = $value;
        return self::openTag('input', $args);
    }
    
    #
    public static function link($title, $href, $content='', $args=[]) : string
    {        
        $content = empty($content) ? $title : $content;
        $args['href'] = $href;
        $args['title'] = $title;
        return self::tag('a', $content, $args);
    }
    
    #
    public static function linkAjax($container, $title, $href, $content='', $args=[]) : string
    {
        $args['data-ajax'] = $container;
        return self::link($title, $href, $content, $args);
    }
    
    #
    public static function linkAjaxOpen($container, $title, $href) : string
    {
        $args['data-ajax'] = $container;
        $args['href'] = $href;
        $args['title'] = $title;
        return self::openTag('a', $args);
    }
    
    #
    public static function linkButton($title, $href, $content='', $args=[]) : string
    {        
        $content = empty($content) ? $title : $content;
        $args['role'] = 'button';
        $args['href'] = $href;
        $args['title'] = $title;
        return self::tag('a', $content, $args);
    }
    
    #
    public static function linkButtonAjax($container, $title, $href, $content='') : string
    {
        $args['data-ajax'] = $container;
        return self::linkButton($title, $href, $content, $args);
    }
    
    #
    public static function linkButtonOutlineAjax($container, $title, $href, $content='') : string
    {        
        $args['class'] = 'outline';
        $args['data-ajax'] = $container;
        return self::linkButton($title, $href, $content, $args);
    }
    
    #
    public static function linkButtonOutline($title, $href, $content='') : string
    {
        $args['class'] = 'outline';
        return self::linkButton($title, $href, $content, $args);
    }
    
    #
    public static function linkOpen($title, $href) : string
    {
        $args['href'] = $href;
        $args['title'] = $title;
        return self::openTag('a', $args);
    }
    
    #
    public static function table($rows) : string
    {
        if (empty($rows[0])) {
            return '';
        }
        $cols = (array)$rows[0];
        ob_start();
        ?><table>
            <thead>
                <tr>
                    <?php foreach ($cols as $col=>$val): ?>
                        <td><?=$col?></td>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($cols as $col=>$val): ?>
                            <td><?=$row->$col?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table><?php
        return Pico::returnTag(ob_get_contents());
    }

    #
    public static function textarea($name, $value='') : string
    {        
        $args['name'] = $name;
        return self::tag('textarea', $value, $args);
    }
}
