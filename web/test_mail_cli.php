<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$autoload = 'C:/xampp/htdocs/vendor/autoload.php';
if (!file_exists($autoload)) {
    $autoload = 'x:/htdocs/vendor/autoload.php';
}
if (!file_exists($autoload)) {
    die("No autoload.php found\n");
}
require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->CharSet = 'UTF-8';
    $mail->Host = 'mail.multisitio.es';
    $mail->SMTPAuth = true;
    $mail->Username = 'webmaster@multisitio.es';
    $mail->Password = 'h1SCTwrbmPZRcgnAtiG1tPPFMokBrVBG';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
	$mail->SMTPOptions = [
		'ssl' => [
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		]
	];

    $mail->setFrom('ia@roleplus.app', 'Test IA');
    $mail->addAddress('dj@roleplus.app');
    $mail->Subject = 'Test SMTP from Local';
    $mail->Body    = 'This is a test message.';

    $mail->send();
    echo "Message has been sent\n";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
}
