<?php
/*
 * cat /var/log/maillog | grep "is a match" | grep "passwd-file" | awk '{ print $8 " " $9 }' | sed "s/[(),]/ /g" |  sed 's/[0-9]\{1,3\}.[0-9]\{1,3\}.[0-9]\{1,3\}.[0-9]\{1,3\}//g' | awk '{ print $2 " " $6 }' | sort | uniq
 * 
 */
class Email {
    private $debug;
    private $other;
    private $filename;
    
    public function __construct($filename, $debug) {
        $this->other = new Other();
        $this->other->setDebug($debug);
        $this->filename = $filename;
        
        if (!file_exists($this->filename)) {
            $this->other->Log("Email->__construct", "E-mail database file does not exists. Exiting...");
            exit;
        }
    }
 
    public function getPassword($email) {

        $explodeEmail = explode("@", $email); # da mail shadow file does not contain full mail addresses
        $handle = @fopen($this->filename . "backup/" . $explodeEmail[1] . "/email/passwd", "r");

        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $buffer = trim($buffer);
                $row_array = explode(":", $buffer); # da mail shadow file entries are separated by colon
                if ($row_array[0] == $explodeEmail[0]) {
                        $this->other->Log("Email->getPassword", $email . " has password " . $row_array[1]);
                        return trim($row_array[1]);
                };
            }
            if (!feof($handle)) {
                $this->other->Log("Email->getPassword", $email . " unable to retrieve password", true);
                return false;
            }
            fclose($handle);
        }
        
        $this->other->Log("Email->getPassword", $email . " unable to retrieve password", true);
        return false;
    }
    
    public function verifyPassword($email, $password, $server) {
        $pop = imap_open("{" . $server . ":110/pop3/novalidate-cert}INBOX", $email, $password);
        if ($pop == FALSE) {
            return FALSE;
        } else {
            return TRUE;
        }
    }
}

