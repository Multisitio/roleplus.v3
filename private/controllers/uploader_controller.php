<?php
class UploaderController extends AppController
{
    public function upload()
    {
        View::select(null, null);
        $idu = Session::get('idu');
        if (!$idu) {
            $this->respond(['error' => 'No se ha iniciado sesión.'], 401);
            return;
        }
        if (empty($_FILES['file'])) {
            $this->respond(['error' => 'No se recibió ninguna imagen.'], 400);
            return;
        }

        $idu = preg_replace('/[^a-z0-9_-]/i','', $idu);
        $public_fs = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_FILENAME'])), '/');
        $dir = $public_fs . '/img/usuarios/' . $idu;

        try {
            $hash = substr(hash_file('sha256', $_FILES['file']['tmp_name']), 0, 20);
            $result = MediaProcessor::processUpload($_FILES['file'], $dir, [
                'basename' => $idu . '-' . $hash,
                'max_width' => (int) ($_POST['max_width'] ?? MediaProcessor::MAX_WIDTH),
                'quality' => (int) ($_POST['quality'] ?? $_POST['loss'] ?? MediaProcessor::WEBP_QUALITY),
            ]);
            $result['ok'] = true;
            $result['url'] = '/img/usuarios/' . $idu . '/' . $result['name'];
            $result['uid'] = $idu;
            $result['hash'] = $hash;
            $result['engine'] = $result['format'] === 'mp4' ? 'ffmpeg' : 'imagick';
            $this->respond($result);
        } catch (RuntimeException $e) {
            $this->respond(['error' => $e->getMessage()], 415);
        } catch (Throwable $e) {
            error_log('Media upload failed: ' . $e->getMessage());
            $this->respond(['error' => 'No se pudo procesar la imagen.'], 500);
        }
    }

    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
