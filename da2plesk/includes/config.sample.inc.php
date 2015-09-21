<?php

define("VERSION", 5);

/* Prior to running migration this script sets the password policy to low. Define your default setting here.
 * after all migration commands have ran it will set the password policy to the value defined here.
 *
 * Available options:
 * very_weak|weak|medium|strong|very_strong
 */

define("PW_POLICY", "medium"); # Medium is required for WHMCS automatic provisioning.

/*
 * Show debugging output
 */
define("DEBUG", FALSE);

define("IPv4", "IPv4 IP of Plesk server");
define("IPv6", "IPv6 IP of Plesk server");

/* 
 * DNS Settings: ignore certain regexes in DNS MX record
 * Records that match these regexes are ignored when updating the DNS
 * zonefile.
 */
define("MX_IGNORE_REGEX", serialize(array("/turtle/", "/mailbackup/")));

/*
 * Here you can define an API call (which is ran using CURL) to update the DNS
 * servers for migrated domains. This can be for example a call to WHMCS
 * API system or a API call to your domain registry.
 * 
 * NS_API_DOUPDATE: Set TRUE to do API calls. False to not do any CURL requests.
 * NS_API_UP: HTTP Basic Auth username/password devided by colon
 * NS_API_DATA: HTTP POST data to send
 * NS_API_URL: HTTP URL to use.
 * 
 * NS_OUR_CONTROL: Domains matching these regexps as DNS are changed
 *  * 
 * The NS_API_DATA accepts the following parameters:
 * #DOMAIN# - Is replaced with the domain name.
 */
define("NS_API_DOUPDATE", TRUE);
define("NS_API_UP", "username:password");
define("NS_API_PASS", "password");
define("NS_API_DATA", "domain=#DOMAIN#&ns1=ns1.example.com&ns2=ns2.example.com");
define("NS_API_URL", "http://myregistry.example.com/api/changens");
define("NS_OUR_CONTROL", serialize(array('/example.com/', '/myisp.eu/')));

define("BACKUP_PATH", "path to your backup directory");

// You can grab a copy of imapsync from https://github.com/imapsync/imapsync
// Debian deps: apt-get install libmail-imapclient-perl
define("IMAPSYNC_PATH", "path to your imapsync binary with trailing slash");

define("EMAIL_PWS", "filename containing email passwords [email][space][password]");

define("MAIL_FROM_ADDR", "Your e-mail address");
define("MAIL_FROM_NAME", "Your name");
define("SEND_MAIL", false); // Do not send any email, use this for testing.

/* Constants cannot be arrays, so we serialize them */
define("IGNORE_DB_NAMES", serialize(array("test", "db_collation", "mysql", "psa", "da", "horde", "squirrelmail"))); // Databases to ignore.
define("IGNORE_DB_USERS", serialize(array("admin", "root", "da_admin", "db_collation"))); // Database usernames to ignore.

define("IGNORE_SITES", serialize(array("default", "sharedip", "suspended")));

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

