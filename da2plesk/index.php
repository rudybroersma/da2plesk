<?php
include("includes/color.class.php");
include("includes/other.class.php");
include("includes/email.class.php");
include("includes/backup.class.php");
include("includes/config.inc.php");

// Do not log notices and warnings (imap_open logs notices and warnings on wrong login)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$backup = new Backup(BACKUP_PATH); // backup_path is a constant from the config file containing untarred DA backup
$other = new Other(MAIL_FROM_ADDR, MAIL_FROM_NAME, SEND_MAIL);
$mail = new Email(EMAIL_PWS); // email_pws is a constant from the config file, containing email passwords

$password = $other->generatePassword();

$domain = $backup->getDomain();
$tld = explode(".", $domain);
$tld = $tld[0];

$ip = $backup->getIP();
$username = $backup->getUsername();

$ictmp = tempnam("/tmp", "imapcopy");

/* BEGIN PRIMARY DOMAIN */
echo "/opt/psa/bin/customer -c $username -name $username -passwd $password\n";
echo "/opt/psa/bin/subscription -c $domain -owner $username -service-plan \"" . SERVICE_PLAN . "\" -ip " . IPv4 . "," . IPv6 . " -login $username -passwd $password\n";
echo "\n";
echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $domain . "/ -type f -print | xargs -I {} sed -i \"s@/home/" . $username . "/domains/" . $domain . "/public_html@/var/www/vhosts/" . $domain . "/httpdocs@g\" {}\n";
echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $domain . "/ -type f -print | grep configuration.php | xargs -I {} sed -i \"s@ftp_enable = '1'@ftp_enable = '0'@g\" {}\n";
echo "cd " . $backup->getPath() . "/domains/" . $domain . "/public_html; /usr/bin/ncftpput -R -u$username -p$password localhost httpdocs .\n";

foreach ($backup->getSubdomains($domain) as $sub) {
    echo "/opt/psa/bin/subdomain -c $sub -domain $domain -www-root /httpdocs/$sub -php true\n";
};
/* END PRIMARY DOMAIN */

/* START ADDITIONAL DOMAINS */
foreach ($backup->getAdditionalDomains() as $extradomain) {
    echo "/opt/psa/bin/site -c $extradomain -hosting true -hst_type phys -webspace-name $domain -www-root domains/$extradomain\n";
    
    echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $extradomain . "/ -type f -print | xargs -I {} sed -i \"s@/home/" . $username . "/domains/" . $extradomain . "/public_html@/var/www/vhosts/" . $domain . "/domains/" . $extradomain . "@g\" {}\n";
    echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $extradomain . "/ -type f -print | grep configuration.php | xargs -I {} sed -i \"s@ftp_enable = '1'@ftp_enable = '0'@g\" {}\n";
    echo "cd " . $backup->getPath() . "/domains/" . $extradomain . "/public_html; /usr/bin/ncftpput -R -u$username -p$password localhost domains/" . $extradomain . " .\n";

    foreach ($backup->getSubdomains($extradomain) as $sub) {
        echo "/opt/psa/bin/subdomain -c $sub -domain $extradomain -www-root /domains/$extradomain/$sub -php true\n";
    };
    
}

/* END ADDITIONAL DOMAINS */

foreach ($backup->getAdditionalDomains(FALSE) as $extradomain) {

    foreach ($backup->getAliases($extradomain) as $alias) {
        echo "/opt/psa/bin/domalias -c $alias -domain $extradomain\n";
    }

    foreach ($backup->getPointers($extradomain) as $alias) {
        echo "/opt/psa/bin/site -c $alias -hosting true -hst_type phys -webspace-name $domain -www-root domains/$alias\n";
        // TODO: Dit moet een 301 worden.
        // redirect 301 / http://www.you.com/
        $tmpfname = tempnam("/tmp", "damigration");
        $handle = fopen($tmpfname, "w");
        fwrite($handle, "Redirect 301 / http://www." . $extradomain . "/\n");
        fclose($handle);

        echo "/usr/bin/ncftpput -c -u$username -p$password localhost domains/$alias/.htaccess < $tmpfname\n";
        echo "rm $tmpfname\n";
    }

    // Create imapcopy config file header
    $handle = fopen("/root/ImapCopy.cfg", "w");
    fwrite($handle, "SourceServer " . $ip . "\n");
    fwrite($handle, "SourcePort 143\n");
    fwrite($handle, "DestServer localhost\n");
    fwrite($handle, "DestPort 143\n");
    fwrite($handle, "DenyFlags \"\Recent\"\n");

    $popresult = false;
    foreach ($backup->getPOP($extradomain) as $pop) {
        $mailpw = $mail->getPassword($pop . "@" . $extradomain);
        if ($mailpw == false) {
            $mailpw = $password;
        };
        echo "/opt/psa/bin/mail -c $pop@$extradomain -mailbox true -passwd $mailpw -passwd_type plain\n";
        echo "/opt/psa/bin/spamassassin -u $pop@$extradomain -status true -hits 5 -action del\n";
        echo "Copy \"$pop@$extradomain\" \"$mailpw\" \"$pop@$extradomain\" \"$mailpw\" >> $ictmp";
        fwrite($handle, "Copy \"" . $pop . "@" . $domain . "\" \"" . $pop . "@" . $domain . "\" \"\n");
        $popresult = true;
    }

    fclose($handle);

    if ($popresult == true) { 
        echo "cd /root; imapcopy\n";
    };
    
    foreach ($backup->getForward($extradomain) as $forward) {
        echo "/opt/psa/bin/mail -c " . $forward['account'] . "@$extradomain -mailbox false -forwarding true -forwarding-addresses add:" . $forward['to'] . "\n";
        echo "/opt/psa/bin/spamassassin -u " . $forward['account'] . "@$extradomain -status true -hits 5 -action del\n";
    }
}

foreach ($backup->getDatabaseList() as $db) {
    echo "/opt/psa/bin/database -c $db -domain $domain -type mysql\n";
    echo "/bin/sed -i \"s@/home/" . $username . "/domains/" . $domain . "/public_html@/var/www/vhosts/" . $domain . "/httpdocs@g\" " . $backup->getPath() . "/backup/" . $db . ".sql\n";
    echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` $db < " . $backup->getPath() . "/backup/" . $db . ".sql\n";

    foreach ($backup->getDatabaseLogin($db) as $user) {
        echo "/opt/psa/bin/database -u $db -add_user " . $user['user'] . " -passwd $password\n";
        echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"UPDATE mysql.user SET Password = '" . $user['pass'] . "' WHERE User = '" . $user['user'] . "'\"\n";
        echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"FLUSH PRIVILEGES\"\n";
    };
}

// Send mail to customer
//$other->sendMail($domain, $username, $password, $backup->getEmail());
$other->sendMail($domain, $username, $password, "tozz@kijkt.tv");

// DO NOT FORGET TO DO SOME DNS MAGIC!


?>
