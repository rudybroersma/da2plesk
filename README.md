da2plesk
========

DirectAdmin to Plesk Migration script

INSTRUCTIONS:

* You need to patch Dovecot to log succeeded logins:
  untar /usr/local/directadmin/custombuild/dovecot-<latest>.tar.gz

  in src/auth-request.c find:
  
  
        if (ret <= 0 && request->set->debug_passwords) T_BEGIN {
                log_password_failure(request, plain_password,
                                     crypted_password, scheme,
                                     request->original_username,
                                     subsystem);
                                     
  and append:
  
          if (ret == 1) T_BEGIN {
                log_password_succes(request, plain_password,
                                     crypted_password, scheme,
                                     request->original_username,
                                     subsystem);
        } T_END;

  Find function log_password_failure, copy/paste it and rename to log_password_succes. Replace the log message
  containing "!=" (to indicate auth failure) to "is a match" (to indicate it matches).
  
  Tar the patched version, copy it to /usr/local/directadmin/custombuild/dovecot-<latest>tar.gz.
  
* Recompile dovecot:
  cd /usr/local/directadmin/custombuild
  ./build dovecot

* In /etc/dovecot.conf add:
  auth_debug=yes
  auth_debug_passwords=yes

* /etc/init.d/dovecot restart

Succesful logins are now logged to /var/log/maillog:

Nov 21 15:54:19 webserver002 dovecot: auth: Debug: passwd-file(user@domain.com,127.0.0.1): MD5-CRYPT(UseRp4ssw0rd) is a match '$1$fojfvco9jg9j4gt9jt', try CRYPT scheme instead

* Use the following command to build a clean list of users and passwords:
  cat /var/log/maillog | grep "is a match" | grep "passwd-file" | awk '{ print $8 " " $9 }' | sed "s/[(),]/ /g" |  sed 's/[0-9]\{1,3\}.[0-9]\{1,3\}.[0-9]\{1,3\}.[0-9]\{1,3\}//g' | awk '{ print $2 " " $6 }' | sort | uniq > /root/emailpws

  On some systems the $6 should be replaced with $5.
  
* Edit config.inc.php to point to your newly created password list.
