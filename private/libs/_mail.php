<?php
require_once VENDOR_PATH . 'autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
/**
 */
class _mail
{
	# 1
	static public function toAdmin($subject = 'Problems in ROLEplus', $body = '')
	{
		if (!$body) {
			$body = print_r([$_GET, $_POST, $_SERVER], 1);
		}
		self::send('dj@roleplus.app', $subject, $body);
	}

	# 1.1
	static public function send($to, $subject, $body, $headers = [])
	{
		$mail = new PHPMailer();
		$mail->isSMTP();
		$mail->setLanguage('es');
		$mail->CharSet = 'UTF-8';
		$mail->Host = 'smtp.gmail.com';
		$mail->SMTPAuth = true;
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		$mail->Username = 'distrotuz@gmail.com';
		$mail->Password = 'fgrhrzowigkyjtyw';
		$mail->Port = 587;

		$mail->setFrom('distrotuz@gmail.com', 'Inteligencia Artificial de R+');
		$mail->Sender = 'distrotuz@gmail.com';
		$mail->Subject = $subject;
		$mail->addAddress($to);

		if (empty($headers['IsText'])) {
			$mail->isHTML(true);
			$mail->Body = "<!DOCTYPE html>\n<html>\n<body>\n" . $body . "\n</body>\n</html>";
			$mail->AltBody = strip_tags($body);
		} else {
			$mail->isHTML(false);
			$mail->Body = is_array($body) ? print_r($body, 1) : (string)$body;
		}

		if (!$mail->send()) {
			error_log('Mailer Error: ' . $mail->ErrorInfo);
		}
		unset($mail);
	}

	# 1.1
	/*static public function send($to, $subject, $body, $headers=[])
	{
		if (_server::isLocal()) {
			return;
		}

		$body = is_array($body) ? print_r($body, 1) : $body;

		if (empty($headers['IsText'])) {
			$cabeceras  = 'MIME-Version: 1.0' . "\r\n";
			$cabeceras .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
			mail($to, $subject, $body, $cabeceras);
			return;
		}

		mail($to, $subject, $body);
	}*/

	# 2
	static public function sendText($to, $subject, $body)
	{
		self::send($to, $subject, $body, ['IsText' => true]);
	}
}
