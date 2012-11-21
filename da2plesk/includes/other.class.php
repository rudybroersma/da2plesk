<?php

class Other {
    const SPACE_LENGTH = 40;
    
    const TAG_COLOR = "yellow";
    const MSG_COLOR = "light_green";
    const BG_COLOR = "black";
    const WARN_COLOR = "light_red";

    private $colors = null;
    
    public function __construct() {
        $this->colors = new Colors();
    }
    
    public function Log($tag, $message, $warn = false) {
        
        $tag = "# " . $tag . ":";
        $length = 3 + strlen($tag) + 1;

        for ($i = 0; $i < (self::SPACE_LENGTH - $length); $i++) {
            $tag .= " ";
        }

        echo $this->colors->getColoredString($tag, self::TAG_COLOR, self::BG_COLOR);
        if ($warn == false) { 
          echo $this->colors->getColoredString($message . "\n", self::MSG_COLOR, self::BG_COLOR);
        } else {
          echo $this->colors->getColoredString($message . "\n", self::WARN_COLOR, self::BG_COLOR);
        }
    }

    function generatePassword($length=9, $strength=0) {
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

}

?>
