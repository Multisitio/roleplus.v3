<?php
require_once VENDOR_PATH . 'autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
/**
 */
class _mail
{
    # 1
	static public function toAdmin($subject='Problems in ROLEplus', $body='')
	{
		if ( ! $body) {
			$body = print_r([$_GET, $_POST, $_SESSION, $_SERVER], 1);
		}
		self::send('dj@roleplus.app', $subject, $body);
    }

    # 1.1
	/*
	$mail->isSMTP();                         
	$mail->setLanguage('es');                 
	$mail->Host       = $parametros->host; 
	$mail->SMTPOptions = array(
							'ssl' => array(
							'verify_peer' => false,
							'verify_peer_name' => false,
							'allow_self_signed' => true
		)
	);  
	//$mail->SMTPDebug  = 2;                  
	$mail->SMTPAuth   = true;  
	$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;                                
	$mail->Username   = $parametros->usuario;                  
	$mail->Password   = $parametros->password;                 
	$mail->Port       = $parametros->puerto;                                   

	$mail->setFrom($parametros->usuario, 'Asociación de trompetistas Manuel López Torres');
	$mail->addAddress($destinatario);

	if($cco){
		$mail->addBCC($cco);
	}

	$mail->addReplyTo($parametros->usuario, 'Asociación de trompetistas Manuel López Torres');
	$mail->CharSet = 'UTF-8';
	$mail->isHTML(true);                                 
	$mail->Subject = $asunto;
	$mail->Body    = $cuerpo;
	$mail->AltBody = $cuerpo;

	$mail->send();
	*/
	static public function send($to, $subject, $body, $headers=[])
	{
		if (preg_match('/localhost|roleplus\.vh/', $_SERVER['HTTP_HOST'])) {
			return;
		}
		
		$mail = new PHPMailer();
		if (empty($headers['IsText'])) {
			$mail->IsHTML(true);
		}
		$mail->isSMTP();                        
		$mail->setLanguage('es');
		$mail->Host = 'mail.roleplus.app';
		$mail->SMTPOptions = [
			'ssl' => [
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			]
		];
		//$mail->SMTPDebug = 2;
		$mail->SMTPAuth = true;
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
		//$mail->SMTPSecure = 'ssl'; // Puedes cambiar a 'tls' o 'ssl' si es necesario
		$mail->Username = "ia@roleplus.app";
		$mail->Password = 'h1SCTwrbmPZRcgnAtiG1tPPFMokBrVBG';
		$mail->Port = 587;
		//$mail->Port = 25;
		//$mail->Port = 465;

		$mail->CharSet = 'UTF-8';
		$mail->AddAddress($to);
		$mail->Subject = $subject;
		$mail->Body = $body;
		$mail->setFrom('ia@roleplus.app', 'Inteligencia Artificial de R+');
		$mail->AddReplyTo('dj@roleplus.app', 'Director de Juego de R+');
		$mail->send();
	}

    # 2
	static public function sendText($to, $subject, $body)
	{
		self::send($to, $subject, $body, ['IsText'=>true]);
	}
}
