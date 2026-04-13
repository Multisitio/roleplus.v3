find /var/www/clients -maxdepth 5 -name test_mail_cli.php -exec php {} \;
grep -r "Mailer Error" /var/www/clients/client1/*/log/error.log | tail -n 20
