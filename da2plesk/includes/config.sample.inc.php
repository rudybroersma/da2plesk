<?php

define("IPv4", "IPv4 IP of Plesk server");
define("IPv6", "IPv6 IP of Plesk server");

define("SERVICE_PLAN", "Name of your service plan");

define("BACKUP_PATH", "path to your backup directory");
define("IMAPSYNC_PATH", "path to your imapsync binary with trailing slash");
// You can grab a copy of imapsync from https://github.com/imapsync/imapsync
// Debian deps: apt-get install libmail-imapclient-perl

define("EMAIL_PWS", "filename containing email passwords [email][space][password]");

define("MAIL_FROM_ADDR", "Your e-mail address");
define("MAIL_FROM_NAME", "Your name");
define("SEND_MAIL", false); // Do not send any email, use this for testing.

/* Constants cannot be arrays, so we serialize them */
define("IGNORE_DB_NAMES", serialize(array("db_collation", "mysql", "psa", "da", "horde", "squirrelmail"))); // Databases to ignore.
define("IGNORE_DB_USERS", serialize(array("admin", "root", "da_admin", "db_collation"))); // Database usernames to ignore.


/* Valid fields for mail_body:
 * #USERNAME#
 * #PASSWORD#
 * #DOMAIN#
 * #MAIL_FROM_NAME#
 */

define("MAIL_SUBJECT", "New login details");

define("MAIL_BODY", 
        "Hello,
            
Your domain has been migrated to a new server. As a result of this migration your login details have changed. We hereby send you your new credentials:

Control Panel: http://#DOMAIN#:8880/
Username: #USERNAME#
Password: #PASSWORD#

FTP: ftp://ftp.#DOMAIN#/
Username: #USERNAME#
Password: #PASSWORD#

Please let us know if you experience any difficulties.

Regards,
#MAIL_FROM_NAME#
            ");
?>
