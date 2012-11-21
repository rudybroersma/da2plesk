<?php
class Backup {
    //const BACKUP_PATH = "/tmp/feel";
    
    private $other;
    private $backup_path;
    
    public function __construct($path) {
         $this->other = new Other();
         $this->backup_path = $path;
    }
    
    public function getPath() {
        return $this->backup_path;
    }
    
    private function readFile($file) {
        $rows = array();
        $handle = @fopen($file, "r");
        
        if ($handle) {
            while (($buffer = fgets($handle, 4096)) !== false) {
                $rows[] = trim($buffer);
            }
            if (!feof($handle)) {
                return false;
            }
            fclose($handle);
        }
        
        return $rows;
    }

    public function getForward($domain) {
        if ($domain == "") { echo "ERROR: domain not set\n"; exit; };
        
        $forwards = array();
        foreach($this->readFile($this->backup_path . "/backup/" . $domain . "/email/aliases") as $row) {
            if ($row != $this->getUsername(FALSE) . ":" . $this->getUsername(FALSE)) {
                $forward = explode(":", $row);
                $this->other->Log("Backup->getForward", $forward[0] . " to " . $forward[1]);
                $forwards[] = array("account" => $forward[0], "to" => $forward[1]);
            }
        }
        
        if (count($forwards) > 0) {
            return $forwards;
        } else {
            $this->other->Log("Backup->getForward", $domain . " has no forwards", true);
            return false;
        }
    }
    
    public function getPOP($domain) {
        if ($domain == "") { echo "ERROR: domain not set\n"; exit; };
        
        $popaccounts = array();
        foreach ($this->readFile($this->backup_path . "/backup/" . $domain . "/email/passwd") as $row) {
            $popaccount = explode(":", $row);
            $popaccounts[] = $popaccount[0];
            $this->other->Log("Backup->getPOP", $popaccount[0] . "@" . $domain);
        }
        
        if (count($popaccounts) > 0) {
            return $popaccounts;
        } else {
            $this->other->Log("Backup->getPOP", "No POP accounts found for $domain", true);
        }
    }
    
    public function getSubdomains($domain) {
        $subdomains = array();
        $file = $this->backup_path . "/backup/" . $domain . "/subdomain.list";
        
        foreach($this->readFile($file) as $row) {
                $this->other->Log("Backup->getSubdomains", $row);
                $subdomains[] = $row;
        };
        
        if (count($subdomains) > 0) {
            return $subdomains;
        } else {
            $this->other->Log("Backup->getSubdomains", "Domain " . $domain . " does not have subdomains", true);
            return false;
        };
    }
    
    private function getNVP($path, $name) {
        foreach($this->readFile($path) as $row) {
                $row_array = explode("=", $row);
                if ($row_array[0] == $name) {
                    return trim($row_array[1]);
                };
        }
        
        return false;
    }

    public function getUsername($log = TRUE) {
        $result = $this->getNVP($this->backup_path . "/backup/user.conf", "username");
        if ($log) { $this->other->Log("Backup->getUsername", $result); };
        return $result;
    }

    public function getIP() {
        $result = $this->getNVP($this->backup_path . "/backup/user.conf", "ip");
        $this->other->Log("Backup->getIP", $result);
        return $result;
    }
    
    public function getAdditionalDomains($skip = TRUE) {
        $addDomains = array();
        foreach (glob($this->backup_path . "/domains/*", GLOB_ONLYDIR) as $filename) {
            $filename = str_replace($this->backup_path . "/domains/", "", $filename);
            if ($filename != $this->getDomain(FALSE) || $skip == FALSE) {
                // als dit domein niet het eerste domein is
                // OF
                // als we het eerste domein niet willen overslaan
                $addDomains[] = $filename;
                if ($skip == FALSE) { $this->other->Log("Backup->getAdditionalDomains", "Found domain: " . $filename); };
            }
        };
        
        if (count($addDomains) > 0) { 
            return $addDomains;
        } else {
            $this->other->Log("Backup->getAdditionalDomains", "No additional domains found");
            return FALSE;
        }
    }
    public function getDomain($log = TRUE) {
        $result = $this->getNVP($this->backup_path . "/backup/user.conf", "domain");
        if ($log) { $this->other->Log("Backup->getDomain", $result); }; 
        return $result;
    }
    
    private function readPointerFile($key, $domain) {
        if ($domain == "") { echo "ERROR: domain not set\n"; exit; };
        
        $path = $this->backup_path . "/backup/" . $domain . "/domain.pointers";

        $pointers = array();
        foreach($this->readFile($path) as $row) {
                $row_array = explode("=", $row);
                if ($row_array[1] == $key) {
                    $pointers[] = $row_array[0];
                    $this->other->Log("Backup->readPointerFile", "Found " . $key . " for " . $row_array[0] . " under " . $domain);
                };
        }
        
        if (count($pointers) > 0) {
            return $pointers;
        } else {
            $this->other->Log("Backup->readPointerFile", "No " . $key . " found for $domain", true);
        }
    }
    
    public function getPointers($domain) {
        return $this->readPointerFile("pointer", $domain);
    }

    public function getAliases($domain) {
        return $this->readPointerFile("alias", $domain);
    }
    
    public function getDatabaseList() {
        $dir = $this->backup_path . "/backup";
        $user = $this->getUsername(FALSE);
        $databases = array();
        foreach (glob($dir . "/" . $user . "_*.sql") as $filename) {
            $dbname = str_replace($dir . "/", "", $filename);   // remove path
            $dbname = preg_replace("/.sql$/", "", $dbname);     // remove file extension
            $databases[] = $dbname;
            $this->other->Log("Backup->getDatabaseList", $dbname);
        };
        
        if (count($databases > 0)) {
            return $databases;
        } else {
            $this->other->Log("Backup->getDatabaseList", "Client does not have any databases", true);
            return false;
        }
    }
    
    public function getDatabaseLogin($database) {
        $user = $this->getUsername(FALSE);
        $logins = array();
        foreach($this->readFile($this->backup_path . "/backup/" . $database . ".conf") as $row) {
                if (preg_match("/". $user . "_/", $row) === 1) {
                    $line_array = explode("&", $row);
                    $temp = explode("=", $line_array[0]);
                    $sql_user = $temp[0];
                    $sql_pass = str_replace("passwd=", "", $line_array[9]);
                        
                    $this->other->Log("Backup->getDatabaseLogin", $sql_user . "|" . $sql_pass);
                    $logins[] = array("user" => $sql_user, "pass" => $sql_pass);
                } 
        }

        if (count($logins) > 0) { 
            return $logins;
        } else {
            $this->other->Log("Backup->getDatabaseLogin", "WARNING: " . $database . " has no users", true);
            return false;
        };
    }
}
?>
