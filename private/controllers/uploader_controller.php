<?php
class UploaderController extends AppController
{
    public function upload()
    {
        View::select(null, null);
        ini_set('display_errors','1'); error_reporting(E_ALL);

        $idu = Session::get('idu');
        if (!$idu) { http_response_code(401); header('Content-Type:text/plain; charset=utf-8'); echo '401 unauthorized: falta idu'; return; }
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { http_response_code(400); header('Content-Type:text/plain; charset=utf-8'); echo '400 no file'; return; }

        $idu = preg_replace('/[^a-z0-9_-]/i','', $idu);

        $public_fs = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_FILENAME'])), '/');
        $dir = $public_fs . '/img/usuarios/' . $idu;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) { http_response_code(500); header('Content-Type:text/plain; charset=utf-8'); echo '500 mkdir failed: '.$dir; return; }

        $tmp  = $_FILES['file']['tmp_name'];
        $orig = $_FILES['file']['name'];
        $orig_base = pathinfo($orig, PATHINFO_FILENAME);
        $orig_base = trim(preg_replace('/\s+/',' ', (string)$orig_base));
        if (function_exists('normalizer_normalize')) $orig_base = normalizer_normalize($orig_base, Normalizer::FORM_KD);
        $ascii = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$orig_base);
        $ascii = strtolower(preg_replace('/[^a-z0-9_-]+/','-', $ascii ?: $orig_base));
        $ascii = preg_replace('/-+/','-',$ascii);
        if ($ascii==='') $ascii='unnamed';

        $hash = substr(sha1($idu.'|'.$ascii), 0, 16);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp); finfo_close($finfo);

        if ($mime === 'image/svg+xml') {
            $name = $hash . '.svg';
            $target = $dir.'/'.$name;
            @unlink($target);
            if (!move_uploaded_file($tmp, $target)) { http_response_code(500); header('Content-Type:text/plain; charset=utf-8'); echo '500 write failed (svg)'; return; }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>true,'url'=>'/img/usuarios/'.$idu.'/'.$name,'name'=>$name,'uid'=>$idu,'format'=>'svg','engine'=>'raw','hash'=>$hash]);
            return;
        }

        $name   = $hash . '.webp';
        $target = $dir.'/'.$name;
        @unlink($target);

        $ok=false; $engine='';

        if (class_exists('Imagick')) {
            try {
                $im = new Imagick(); $im->readImage($tmp);
                $im->setImageFormat('webp'); $im->setImageCompressionQuality(82);
                if ($im->getNumberImages()>1) {
                    $seq=$im->coalesceImages();
                    foreach($seq as $f){ $f->setImageFormat('webp'); $f->setImageCompressionQuality(82); }
                    $ok=$seq->writeImages($target,true);
                } else { $ok=$im->writeImage($target); }
                $im->clear(); $im->destroy(); $engine='imagick';
            } catch (\Throwable $e) {
                http_response_code(500); header('Content-Type:text/plain; charset=utf-8'); echo '500 imagick: '.$e->getMessage(); return;
            }
        }

        if (!$ok) {
            $bin=@file_get_contents($tmp);
            $src=($bin!==false)?@imagecreatefromstring($bin):false;
            if ($src && function_exists('imagewebp')) {
                if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($src);
                imagealphablending($src,false); imagesavealpha($src,true);
                $ok=@imagewebp($src,$target,82); imagedestroy($src); $engine='gd';
            } else {
                http_response_code(415); header('Content-Type:text/plain; charset=utf-8');
                echo '415 convert-unavailable (imagick='.(class_exists('Imagick')?'present':'missing').', gd_webp='.(function_exists('imagewebp')?'present':'missing').')';
                return;
            }
        }

        if (!$ok) { http_response_code(500); header('Content-Type:text/plain; charset=utf-8'); echo '500 convert failed'; return; }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'=>true,
            'url'=>'/img/usuarios/'.$idu.'/'.$name,
            'name'=>$name,
            'uid'=>$idu,
            'format'=>'webp',
            'engine'=>$engine,
            'hash'=>$hash
        ]);
    }
}
