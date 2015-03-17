<?php
/*
 * cat /var/log/maillog | grep "is a match" | grep "passwd-file" | awk '{ print $8 " " $9 }' | sed "s/[(),]/ /g" |  sed 's/[0-9]\{1,3\}.[0-9]\{1,3\}.[0-9]\{1,3\}.[0-9]\{1,3\}//g' | awk '{ print $2 " " $6 }' | sort | uniq
 * 
 */
class Email {
    private $other;
    private $filename;
    
    public function __construct($filename) {
        $this->other = new Other();
        $this->filename = $filename;
        
        if (!file_exists($this->filename)) {
            $this->other->Log("Email->__construct", "E-mail database file does not exists. Exiting...");
            exit;
        }
    }
    
    public function getPassword($email) {
        $handle = @fopen($this->filename, "r");

        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $buffer = trim($buffer);
                $row_array = explode(" ", $buffer);
                if ($row_array[0] == $email) {
                    $explodeEmail = explode("@", $email);
                    if (strpos($row_array[1], $explodeEmail[0]) !== false) {
                        $this->other->Log("Email->getPassword", $email . " has invalid password (" . $row_array[1] . ") (username in password is not allowed)", true);
                        return false;
                    } else {
                        $this->other->Log("Email->getPassword", $email . " has password " . $row_array[1]);
                        return trim($row_array[1]);
                    };
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

