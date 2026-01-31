<?php
/**
 */
class _code
{
    #
    static function open($class, $lang='html')
    {
        echo "<pre><code class=\"$class language-$lang\">";
        ob_start();
	}

    #
    static function close()
    {
        $content = ob_get_contents();
        ob_end_clean();
        echo h($content) . '</code></pre>';
	}
}