<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
/*
** DirectAdmin to Plesk Migration script
**
** Created by Rudy Broersma <r.broersma@exsilia.net>
*/

include("includes/color.class.php");
include("includes/other.class.php");
include("includes/email.class.php");
include("includes/backup.class.php");
include("includes/plesk.class.php");
include("includes/config.inc.php");

// Do not log notices and warnings (imap_open logs notices and warnings on wrong login)
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

if (VERSION != 2) {
    echo "Version mismatch. You need to update your configuration file\n";
    exit;
};

if (isset($argv[1])) {
  $serviceplan = $argv[1];
} else {
  $serviceplan = 0;
};

$plesk = new Plesk();
$sp = $plesk->getServicePlans();

if (array_key_exists($serviceplan, $sp)) {
  $valid_serviceplan = true;
} else {
  $valid_serviceplan = false;
};

if ($valid_serviceplan === false) {
  echo "Invalid serviceplan given. Please pass the serviceplan number as parameter (eg. php index.php 5): \n\n";
  foreach($sp as $plan) {
    echo $plan['id'] . ": " . $plan['name'] . "\n";
  };
 
  exit;
} else {
  $serviceplan_name = $sp[$serviceplan]['name'];
};


$backup = new Backup(BACKUP_PATH, IGNORE_DB_NAMES, IGNORE_DB_USERS, IGNORE_SITES); // backup_path is a constant from the config file containing untarred DA backup
$other = new Other(MAIL_FROM_ADDR, MAIL_FROM_NAME, SEND_MAIL);
$mail = new Email(EMAIL_PWS); // email_pws is a constant from the config file, containing email passwords

$password = $other->generatePassword();

$mailaccounts = array();

$domain = $backup->getDomain();
$tld = explode(".", $domain);
$tld = $tld[0];

$ip = $backup->getIP();
$username = $backup->getUsername();
$acctemail = $backup->getEmail();

/* BEGIN PRIMARY DOMAIN */
echo "# Control Panel: http://www.$domain:8880/\n";
echo "# Gebruikersnaam: $username\n";
echo "# Wachtwoord: $password\n";
echo "#\n";
echo "# FTP: ftp://ftp.$domain/\n";
echo "# Gebruikersnaam: $username\n";
echo "# Wachtwoord: $password\n";
echo "#\n";

$of = fopen("logins/" . $domain, "w+");
fwrite($of, "Geachte heer/mevrouw,\n\nAls klant van ons neemt u onderstaande domeinen en bijbehorende hosting bij ons af.\nZoals aangekondigd zijn we begonnen met het migreren van hosting naar nieuwe servers.\nHieronder vindt u nieuwe logingegevens. Deze migratie moet in principe geruisloos verlopen.\nMocht u toch tegen problemen aanlopen, laat het ons dan vooral weten.\n\n");
fwrite($of, "# Control Panel: http://www." . $domain . ":8880/\n");
fwrite($of, "# Gebruikersnaam: " . $username . "\n");
fwrite($of, "# Wachtwoord: " . $password . "\n");
fwrite($of, "#\n");
fwrite($of, "# FTP: ftp://ftp." . $domain . "/\n");
fwrite($of, "# Gebruikersnaam: " . $username . "\n");
fwrite($of, "# Wachtwoord: " . $password . "\n");
fwrite($of, "#\n\n");
fwrite($of, "Met vriendelijke groet,\nWannahost Support\n");
fclose($of);

echo "/opt/psa/bin/customer -c $username -email $acctemail -name $username -passwd $password\n";
echo "/opt/psa/bin/subscription -c $domain -owner $username -service-plan \"$serviceplan_name\" -ip " . IPv4 . "," . IPv6 . " -login $username -passwd $password -seo-redirect none\n";
echo "\n";
echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $domain . "/ -type f -print | xargs -I {} sed -i \"s@/home/" . $username . "/domains/" . $domain . "/public_html@/var/www/vhosts/" . $domain . "/httpdocs@g\" {}\n";
echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $domain . "/ -type f -print | grep configuration.php | xargs -I {} sed -i \"s@ftp_enable = '1'@ftp_enable = '0'@g\" {}\n";
echo "mkdir " . $backup->getPath() . "/domains/" . $domain . "/public_html/webmail/\n";
echo "echo \"Redirect 301 /webmail http://webmail." . $domain . "/\" > " . $backup->getPath() . "/domains/" . $domain . "/public_html/webmail/.htaccess\n";
echo "cd " . $backup->getPath() . "/domains/" . $domain . "/public_html; /usr/bin/ncftpput -R -u$username -p$password localhost httpdocs .\n";

foreach ($backup->getSubdomains($domain) as $sub) {
    echo "/opt/psa/bin/subdomain -c $sub -domain $domain -www-root /httpdocs/$sub -php true\n";
};
/* END PRIMARY DOMAIN */

/* START ADDITIONAL DOMAINS */
foreach ($backup->getAdditionalDomains(TRUE) as $extradomain) {
    echo "/opt/psa/bin/site -c $extradomain -hosting true -hst_type phys -webspace-name $domain -www-root domains/$extradomain -seo-redirect none\n";
    
    echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $extradomain . "/ -type f -print | xargs -I {} sed -i \"s@/home/" . $username . "/domains/" . $extradomain . "/public_html@/var/www/vhosts/" . $domain . "/domains/" . $extradomain . "@g\" {}\n";
    echo "/usr/bin/find " . $backup->getPath() . "/domains/" . $extradomain . "/ -type f -print | grep configuration.php | xargs -I {} sed -i \"s@ftp_enable = '1'@ftp_enable = '0'@g\" {}\n";
    echo "mkdir " . $backup->getPath() . "/domains/" . $extradomain . "/public_html/webmail/\n";
    echo "echo \"Redirect 301 /webmail http://webmail." . $extradomain . "/\" > " . $backup->getPath() . "/domains/" . $extradomain . "/public_html/webmail/.htaccess\n";
    echo "cd " . $backup->getPath() . "/domains/" . $extradomain . "/public_html; /usr/bin/ncftpput -R -u$username -p$password localhost domains/" . $extradomain . " .\n";

    foreach ($backup->getSubdomains($extradomain) as $sub) {
        echo "/opt/psa/bin/subdomain -c $sub -domain $extradomain -www-root /domains/$extradomain/$sub -php true\n";
    };
    
}

/* END ADDITIONAL DOMAINS */

foreach ($backup->getAdditionalDomains(FALSE) as $extradomain) {

    foreach($backup->getProtectedDirectories($extradomain) as $dir) {
	echo "/opt/psa/bin/protdir -c " . $dir["path"] . " -domain " . $extradomain . " -title \"" . $dir["name"] . "\" -type wwwroot\n";
	foreach($dir["list"] as $account) {
	  echo "/opt/psa/bin/protdir -u " . $dir["path"] . " -domain " . $extradomain . " -add_user \"" . $account["user"] . "\" -passwd_type encrypted -passwd '" . $account["pass"] . "'\n";
	};

	// remove directadmin .htaccess
	if ($backup->getDomain(FALSE) == $extradomain) {
          echo "rm -v /var/www/vhosts/" . $extradomain . "/httpdocs/" . $dir["path"] . "/.htaccess\n";
        } else {
          echo "rm -v /var/www/vhosts/" . $backup->getDomain(FALSE) . "/domains/" . $extradomain . "/" . $dir["path"] . "/.htaccess\n";
        };
    };

    foreach ($backup->getAliases($extradomain) as $alias) {
        echo "/opt/psa/bin/domalias -c $alias -domain $extradomain\n";
    }

    foreach ($backup->getPointers($extradomain) as $alias) {
        echo "/opt/psa/bin/site -c $alias -hosting true -hst_type phys -webspace-name $domain -www-root domains/$alias  -seo-redirect none\n";
        $tmpfname = tempnam("/tmp", "damigration");
        $handle = fopen($tmpfname, "w");
        fwrite($handle, "Redirect 301 / http://www." . $extradomain . "/\n");
        fclose($handle);

        echo "/usr/bin/ncftpput -c -u$username -p$password localhost domains/$alias/.htaccess < $tmpfname\n";
        echo "rm $tmpfname\n";
    }

    $popresult = false;
    foreach ($backup->getPOP($extradomain) as $pop) {
        array_push($mailaccounts, $pop . "@" . $extradomain);
        
        $mailpw_restored = FALSE;
        $mailpw = $mail->getPassword($pop . "@" . $extradomain);
        if ($mailpw == false) {
            $mailpw = $password;
        } else {
            $mailpw_restored = TRUE;
        }
        
        echo "/opt/psa/bin/mail -c $pop@$extradomain -mailbox true -passwd '$mailpw' -passwd_type plain\n";
        echo "/opt/psa/bin/spamassassin -u $pop@$extradomain -status true -hits 5 -action del\n";
        
        if ($mailpw_restored == TRUE) {
            // only run imapsync when we recovered the password. Otherwise its useless.
          echo IMAPSYNC_PATH . "imapsync --noreleasecheck --host1 " . $ip . " --host2 localhost --user1 " . $pop . "@" . $extradomain . " --user2 "  . $pop . "@" . $extradomain . " --password1 " . $mailpw . " --password2 " . $mailpw . "\n";
        };
    }
    
    foreach ($backup->getForward($extradomain) as $forward) {
        if (!in_array($forward['account'] . "@" . $extradomain, $mailaccounts)) {
            // Mailaccount is not in array, so we create a new one.
          $forward['to'] = preg_replace('/\s+/', '', $forward['to']); // remove all spaces
          $forward['to'] = preg_replace('/,$/', '', $forward['to']); // remove all commas
          echo "/opt/psa/bin/mail -c " . $forward['account'] . "@$extradomain -mailbox false -forwarding true -forwarding-addresses add:" . $forward['to'] . "\n";
          echo "/opt/psa/bin/spamassassin -u " . $forward['account'] . "@$extradomain -status true -hits 5 -action del\n";
        } else {
            // We add the forward to the already created account.
          $forward['to'] = preg_replace('/\s+/', '', $forward['to']); // remove all spaces
          $forward['to'] = preg_replace('/,$/', '', $forward['to']); // remove all commas
          echo "/opt/psa/bin/mail -u " . $forward['account'] . "@$extradomain -forwarding true -forwarding-addresses add:" . $forward['to'] . "\n";
          array_push($mailaccounts, $forward['to'] . "@" . $extradomain);
        }
    }
    
    $catchall = $backup->getCatchall($extradomain);
    if ($catchall != FALSE) {  
        // Catchall is configured
        echo "/opt/psa/admin/bin/mailmng --set-catchall --domain-name=" . $extradomain . " --email=" . $catchall . "\n";
    } else {
        echo "/opt/psa/admin/bin/mailmng --set-reject --domain-name=" . $extradomain . "\n";
    }
}

foreach ($backup->getDatabaseList() as $db) {
    echo "/opt/psa/bin/database -c $db -domain $domain -type mysql\n";
    echo "/bin/sed -i \"s@/home/" . $username . "/domains/" . $domain . "/public_html@/var/www/vhosts/" . $domain . "/httpdocs@g\" " . $backup->getPath() . "/backup/" . $db . ".sql\n";

    foreach($backup->getAdditionalDomains(TRUE) as $extradomain) {
        echo "/bin/sed -i \"s@/home/" . $username . "/domains/" . $extradomain . "/public_html@/var/www/vhosts/" . $domain . "/domains/" . $extradomain . "@g\" " . $backup->getPath() . "/backup/" . $db . ".sql\n";
    }
    echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` $db < " . $backup->getPath() . "/backup/" . $db . ".sql\n";

    foreach ($backup->getDatabaseLogin($db) as $user) {
# Plesk 11.5 supports 1 user for multiple DBs. Use that feature :)
#        echo "/opt/psa/bin/database -u $db -add_user " . $user['user'] . " -passwd $password\n";
        echo "/opt/psa/bin/database --create-dbuser " . $user['user'] . " -domain " . $domain . " -passwd $password -type mysql\n";
        echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"UPDATE mysql.user SET Password = '" . $user['pass'] . "' WHERE User = '" . $user['user'] . "'\"\n";
        echo "/usr/bin/mysql -uadmin -p`cat /etc/psa/.psa.shadow` mysql -e \"FLUSH PRIVILEGES\"\n";
    };
}

$backup->getCron();

// Send mail to customer
//$other->sendMail($domain, $username, $password, $backup->getEmail());
$other->sendMail($domain, $username, $password, "tozz@kijkt.tv");

// DO NOT FORGET TO DO SOME DNS MAGIC!


?>
