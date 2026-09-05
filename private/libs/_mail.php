<?php
require_once VENDOR_PATH . 'autoload.php';

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Transactional email sent through the local, DKIM-enabled mail server.
 */
class _mail
{
    private const FROM_ADDRESS = 'ia@roleplus.app';
    private const FROM_NAME = 'ROLEplus';
    private const REPLY_TO_ADDRESS = 'dj@roleplus.app';

    static public function toAdmin($subject = 'Problems in ROLEplus', $body = '')
    {
        if (!$body) {
            $body = print_r([$_GET, $_POST, $_SERVER], true);
        }

        return self::send(self::REPLY_TO_ADDRESS, $subject, $body);
    }

    static public function send($to, $subject, $body, $headers = [])
    {
        if (self::isLocalRequest()) {
            return true;
        }

        if (!PHPMailer::validateAddress($to)) {
            error_log('ROLEplus mail rejected an invalid recipient address.');
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isMail();
            $mail->setLanguage('es');
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->setFrom(self::FROM_ADDRESS, self::FROM_NAME);
            $mail->Sender = self::FROM_ADDRESS;
            $mail->addReplyTo(self::REPLY_TO_ADDRESS, self::FROM_NAME);
            $mail->Subject = self::singleLine($subject);
            $mail->addAddress($to);

            foreach (['List-Unsubscribe', 'List-Unsubscribe-Post'] as $headerName) {
                if (!empty($headers[$headerName])) {
                    $mail->addCustomHeader($headerName, self::singleLine($headers[$headerName]));
                }
            }

            if (empty($headers['IsText'])) {
                $htmlBody = is_array($body)
                    ? '<pre>' . htmlspecialchars(print_r($body, true), ENT_QUOTES, 'UTF-8') . '</pre>'
                    : (string) $body;

                $mail->isHTML(true);
                $mail->Body = "<!DOCTYPE html>\n<html lang=\"es\">\n<body>\n" . $htmlBody . "\n</body>\n</html>";
                $mail->AltBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            } else {
                $mail->isHTML(false);
                $mail->Body = is_array($body) ? print_r($body, true) : (string) $body;
            }

            return $mail->send();
        } catch (MailerException $exception) {
            error_log('ROLEplus mail delivery failed: ' . $exception->getMessage());
            return false;
        }
    }

    static public function sendText($to, $subject, $body)
    {
        return self::send($to, $subject, $body, ['IsText' => true]);
    }

    private static function isLocalRequest()
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return (bool) preg_match('/^(?:localhost|roleplus\.v(?:h)?)(?::\d+)?$/i', $host);
    }

    private static function singleLine($value)
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', (string) $value));
    }
}
