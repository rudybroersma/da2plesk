<?php
class Other {

    const SPACE_LENGTH = 40;
    const USE_COLOR = FALSE;
    const TAG_COLOR = "yellow";
    const MSG_COLOR = "light_green";
    const BG_COLOR = "black";
    const WARN_COLOR = "light_red";

    const HELPTEXT = <<<STR
You need to specificy parameters to run this script:

In order to get the migration commands, you need to specify a serviceplan:
            
--serviceplan=       to migrate this account to the specified serviceplan ID

Optionally you can use these parameters:
            
--username=          to override the new username
--password=          to override the password
            
The following parameters return informational output only:
            
--list-serviceplans  to request a list of serviceplan IDs
--list-domains       retrieve a list of all domains listed in this backup
--help               get this help text


STR;
        
    private $debug = FALSE;
    private $colors = null;
    private $mail_from_addr;
    private $mail_from_name;
    private $send_mail = false;

    public function __construct($mail_from_addr, $mail_from_name, $send_mail, $debug) {
        $this->colors = new Colors(self::USE_COLOR);
        
        $this->debug = $debug;

        $this->mail_from_addr = $mail_from_addr;
        $this->mail_from_name = $mail_from_name;

        $this->send_email = $send_mail;
    }

    public function setDebug($debug) {
        $this->debug = $debug;
    }
    
    # Credits go where credits due: https://gist.githubusercontent.com/magnetik/2959619/raw/f7b6eb0521086755e5c17f5b622d376292906e4a/php-cli.php
    public function parseArguments($argv) {
        array_shift($argv);
        $out = array();
        
        foreach ($argv as $arg) {
            if (substr($arg, 0, 2) == '--') {
                $eqPos = strpos($arg, '=');
                if ($eqPos === false) {
                    $key = substr($arg, 2);
                    $out[$key] = isset($out[$key]) ? $out[$key] : true;
                } else {
                    $key = substr($arg, 2, $eqPos - 2);
                    $out[$key] = substr($arg, $eqPos + 1);
                }
            } else if (substr($arg, 0, 1) == '-') {
                if (substr($arg, 2, 1) == '=') {
                    $key = substr($arg, 1, 1);
                    $out[$key] = substr($arg, 3);
                } else {
                    $chars = str_split(substr($arg, 1));
                    foreach ($chars as $char) {
                        $key = $char;
                        $out[$key] = isset($out[$key]) ? $out[$key] : true;
                    }
                }
            } else {
                $out[] = $arg;
            }
        }
        
        $this->showHelp($out);
        
        return $out;
    }
    
    private function showHelp($arguments) {
        if (array_key_exists("help", $arguments) || count($arguments) == 0) {
            echo self::HELPTEXT;
            exit;
        }
    }

    public function Log($tag, $message, $warn = false) {
        
        
        $tag = "# " . $tag . ":";
        $length = 3 + strlen($tag) + 1;

        for ($i = 0; $i < (self::SPACE_LENGTH - $length); $i++) {
            $tag .= " ";
        }

        if ($this->debug === TRUE) {
            echo $this->colors->getColoredString($tag, self::TAG_COLOR, self::BG_COLOR);
            if ($warn == false) {
                echo $this->colors->getColoredString($message . "\n", self::MSG_COLOR, self::BG_COLOR);
            } else {
                echo $this->colors->getColoredString($message . "\n", self::WARN_COLOR, self::BG_COLOR);
            }
        };
    }

    public function generatePassword($length = 9, $strength = 0) {
        $vowels = 'aeuy';
        $consonants = 'bdghjmnpqrstvz';
        if ($strength & 1) {
            $consonants .= 'BDGHJLMNPQRSTVWXZ';
        }
        if ($strength & 2) {
            $vowels .= "AEUY";
        }
        if ($strength & 4) {
            $consonants .= '23456789';
        }
        if ($strength & 8) {
            $consonants .= '@#$%';
        }

        $password = '';
        $alt = time() % 2;
        for ($i = 0; $i < $length; $i++) {
            if ($alt == 1) {
                $password .= $consonants[(rand() % strlen($consonants))];
                $alt = 0;
            } else {
                $password .= $vowels[(rand() % strlen($vowels))];
                $alt = 1;
            }
        }
        return $password;
    }

    public function sendMail($domain, $username, $password, $email) {
        $body = MAIL_BODY; // get template to string
        $body = str_replace("#DOMAIN#", $domain, $body);
        $body = str_replace("#USERNAME#", $username, $body);
        $body = str_replace("#PASSWORD#", $password, $body);
        $body = str_replace("#MAIL_FROM_NAME#", MAIL_FROM_NAME, $body);

        $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDR . ">\r\n" .
                'X-Mailer: PHP/' . phpversion();

        if ($this->send_mail == true) {
            mail($email, MAIL_SUBJECT, $body, $headers, "-f" . MAIL_FROM_ADDR);
            $this->log("Other->sendMail()", "New credentails have been mailed to " . $email);
        } else {
            $this->log("Other->sendMail()", "sendMail() was called, but no mail was sent.");
        }
    }

}
