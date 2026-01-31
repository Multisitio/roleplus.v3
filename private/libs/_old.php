<?php
/**
 */
class _old
{
    # 
	public static function getExt($img)
	{        
		$info = getimagesize($img);

        # exception because getimagesize no work with svg
        if (empty($info[2])) {
            if (strstr($img, '?')) {
                $img = explode('?', $img, 2)[0];
            }
            if (strstr($img, '.')) {
                $img = explode('.', $img);
                $img = array_pop($img);
            }
            return $img;
        }

        switch ($info[2]) {
            case IMAGETYPE_GIF:
                return 'gif';
            case IMAGETYPE_JPEG:
                return 'jpg';
            case IMAGETYPE_PNG:
                return 'png';
            case IMAGETYPE_WEBP:
                return 'webp';
            default:
				return false;
        }
    }
}
