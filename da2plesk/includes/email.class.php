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
    }
    
    public function getPassword($email) {
        $handle = @fopen($this->filename, "r");

        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $buffer = trim($buffer);
                $row_array = explode(" ", $buffer);
                if ($row_array[0] == $email) {
                    $this->other->Log("Email->getPassword", $email . " has password " . $row_array[1]);
                    return trim($row_array[1]);
                };
            }
            if (!feof($handle)) {
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

?>