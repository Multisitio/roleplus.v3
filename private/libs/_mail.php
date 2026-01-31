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
		$mail->Host = 'mail.multisitio.es';
		$mail->SMTPOptions = [
			'ssl' => [
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			]
		];
		$mail->SMTPAuth = true;
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		$mail->Username = 'webmaster@multisitio.es';
		$mail->Password = 'h1SCTwrbmPZRcgnAtiG1tPPFMokBrVBG';
		$mail->Port = 587;

		$mail->setFrom('ia@roleplus.app', 'Inteligencia Artificial de R+');
		$mail->Sender = 'ia@roleplus.app';
		$mail->addReplyTo('dj@roleplus.app', 'Director de Juego de R+');
		$mail->Subject = $subject;
		$mail->addAddress($to);

		if (empty($headers['IsText'])) {
			$mail->isHTML(true);
			$mail->Body = $body;
		} else {
			$mail->isHTML(false);
			$mail->Body = is_array($body) ? print_r($body, 1) : (string)$body;
		}

		/* ======================
		 * DKIM en ruta permitida (open_basedir)
		 * ====================== */
		$dkimDomain = 'roleplus.app';
		$dkimSelector = 'default';
		// Ruta dentro de open_basedir (ajústala si usas otra)
		$dkimPath = '/var/www/clients/client1/web6/private/dkim/default.private';

		if (is_readable($dkimPath)) {
			$dkimKey = file_get_contents($dkimPath);
			if ($dkimKey) {
				$mail->DKIM_domain = $dkimDomain;
				$mail->DKIM_selector = $dkimSelector;
				$mail->DKIM_private_string = $dkimKey; // evita file_exists() fuera de open_basedir
				$mail->DKIM_passphrase = '';
				$mail->DKIM_identity = $mail->From;
			}
		} else {
			// No forcemos $mail->DKIM_private para no disparar el warning de open_basedir
			error_log('PHPMailer: DKIM key not readable at ' . $dkimPath . ' (skipping DKIM).');
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
