<?php
/**
 */
class _common extends _old
{
    # 
	public static function get_content()
	{  
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
