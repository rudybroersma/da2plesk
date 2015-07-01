<?php

class Backup {

    //const BACKUP_PATH = "/tmp/feel";

    private $debug;
    private $other;
    private $backup_path;
    private $ignore_db_names;
    private $ignore_db_users;
    private $ignore_sites;

    public function __construct($path, $names, $users, $sites, $debug) {
        $this->other = new Other();
        $this->other->setDebug($debug);

        $this->backup_path = $path;
        $this->ignore_db_names = unserialize($names);
        $this->ignore_db_users = unserialize($users);
        $this->ignore_sites = unserialize($sites);
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

    private function getAuthName($domain, $protected_dir) {
        $htaccess_path = $this->backup_path . "/domains/" . $domain . str_replace("/domains/" . $domain, "", $protected_dir) . "/.htaccess";
        foreach ($this->readFile($htaccess_path) as $row) {
            if (strpos($row, "AuthName") !== FALSE) {
                return str_replace("\"", "", substr($row, strlen("AuthName "), strlen($row)));
            };
        };
    }

    /* Fetch users and (encrypted) passwords from .htpasswd file */

    private function getUserList($domain, $protected_dir) {
        $credentials = array();

        $htpasswd_path = $this->backup_path . "/domains/" . $domain . "/.htpasswd" . str_replace("/domains/" . $domain, "", $protected_dir) . "/.htpasswd";

        foreach ($this->readFile($htpasswd_path) as $row) {
            $split_row = explode(":", $row);
            array_push($credentials, array("user" => $split_row[0], "pass" => $split_row[1]));
        };

        return $credentials;
    }

    public function getProtectedDirectories($domain) {
        $protdir = array();
        foreach ($this->readFile($this->backup_path . "/domains/" . $domain . "/.htpasswd/.protected.list") as $path) {
            if (strpos($path, "/domains/" . $domain . "/public_html") !== FALSE) { // Must be public accessible directory
                $plesk_path = $path;
                $plesk_path = str_replace("/domains/" . $domain . "/public_html", "", $plesk_path);
                $plesk_path = str_replace("//", "/", $plesk_path);
//	  if (substr($plesk_path, 0, 1) == "/") { $plesk_path = substr($plesk_path, 1, strlen($plesk_path)); };
                if ($plesk_path == "") {
                    $plesk_path = "/";
                };

                $this->other->Log("Backup->getProtectedDirectories", "found protected dir: " . $path, false);
                array_push($protdir, array(
                    "path" => $plesk_path,
                    "name" => $this->getAuthName($domain, $path),
                    "list" => $this->getUserList($domain, $path)
                        )
                );
            };
        };

        if (count($protdir) == 0) {
            $this->other->Log("Backup->getProtectedDirectories", "No protected directories for " . $domain, true);
            return null;
        } else {
            return $protdir;
        };
    }

    public function getCatchall($domain) {
        if ($domain == "") {
            echo "ERROR: domain not set\n";
            exit;
        };

        $forwards = array();
        foreach ($this->readFile($this->backup_path . "/backup/" . $domain . "/email/email.conf") as $row) {
            if (substr($row, 0, 9) == "catchall=") {
                $catchall = explode("=", $row);
                if (strstr($catchall[1], $this->getUsername(FALSE)) !== FALSE) {
                    # Replace all midline occurences  
                    $catchall[1] = str_replace("," . $this->getUsername(FALSE) . ",", "," . $this->getUsername(FALSE) . "@" . $domain . ",", $catchall[1]); // username is found within a list of multiple addresses. 
                    $catchall[1] = preg_replace("/^" . $this->getUsername(FALSE) . "[^@].*/", $this->getUsername(FALSE) . "@" . $domain, $catchall[1]);
                    $catchall[1] = preg_replace("/" . $this->getUsername(FALSE) . "$/", $this->getUsername(FALSE) . "@" . $domain, $catchall[1]);
                };

                if (substr_count($catchall[1], ",") > 1) {
                    $this->other->Log("Backup->getCatchall", $domain . " has catchall to multiple mail addresses. Plesk does not support this. Using first only.", true);
                    $temp = explode(",", $catchall[1]);
                    $catchall[1] = $temp[0];
                };

                if (strstr($catchall[1], "@")) {
                    $email = substr($row, 9, strlen($row));
                    $this->other->Log("Backup->getCatchall", $domain . " catchall to " . $catchall[1], false);
                    return $catchall[1];
                }
            };
        }

        $this->other->Log("Backup->getCatchall", $domain . " has no catchall address", true);
    }

    public function getForward($domain) {
        if ($domain == "") {
            echo "ERROR: domain not set\n";
            exit;
        };

        $forwards = array();
        foreach ($this->readFile($this->backup_path . "/backup/" . $domain . "/email/aliases") as $row) {
            if ($row != $this->getUsername(FALSE) . ":" . $this->getUsername(FALSE)) {
                $forward = explode(":", $row);

                #$forward[0] is forward mail address to create.
                #$forward[1] is where we forward to. Can be multiple addresses.
                # Replace the system username with the full e-mail addresses.
                if (strstr($forward[1], $this->getUsername(FALSE)) !== FALSE) {
                    # Replace all midline occurences  
                    $forward[1] = str_replace("," . $this->getUsername(FALSE) . ",", "," . $this->getUsername(FALSE) . "@" . $domain . ",", $forward[1]); // username is found within a list of multiple addresses. 
                    $forward[1] = preg_replace("/^" . $this->getUsername(FALSE) . "[^@].*/", $this->getUsername(FALSE) . "@" . $domain, $forward[1]);
                    $forward[1] = preg_replace("/" . $this->getUsername(FALSE) . "$/", $this->getUsername(FALSE) . "@" . $domain, $forward[1]);
                };

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
        if ($domain == "") {
            echo "ERROR: domain not set\n";
            exit;
        };

        $popaccounts = array();
        foreach ($this->readFile($this->backup_path . "/backup/" . $domain . "/email/passwd") as $row) {
            $popaccount = explode(":", $row);
            $popaccounts[] = $popaccount[0];
            $this->other->Log("Backup->getPOP", $popaccount[0] . "@" . $domain);
        }

        $popaccounts[] = $this->getUsername(FALSE);

        if (count($popaccounts) > 0) {
            return $popaccounts;
        } else {
            /* Because we now include the system user as well, we will never get here. Leave it for legacy purposes or something :] */
            $this->other->Log("Backup->getPOP", "No POP accounts found for $domain", true);
        }
    }

    public function getSubdomains($domain) {
        $subdomains = array();
        $file = $this->backup_path . "/backup/" . $domain . "/subdomain.list";

        foreach ($this->readFile($file) as $row) {
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
        foreach ($this->readFile($path) as $row) {
            $row_array = explode("=", $row);
            if ($row_array[0] == $name) {
                return trim($row_array[1]);
            };
        }

        return false;
    }

    public function getUsername($log = TRUE) {
        $result = strtolower($this->getNVP($this->backup_path . "/backup/user.conf", "username"));
        if ($log) {
            $this->other->Log("Backup->getUsername", $result);
        };
        return $result;
    }

    public function getEmail($log = TRUE) {
        $result = strtolower($this->getNVP($this->backup_path . "/backup/user.conf", "email"));

        $exploded = explode(",", $result);
        $result = $exploded[0]; // get first mail address.

        if ($log) {
            $this->other->Log("Backup->getEmail", $result);
        };
        return $result;
    }

    public function getIP() {
        $result = $this->getNVP($this->backup_path . "/backup/user.conf", "ip");
        $this->other->Log("Backup->getIP", $result);
        return $result;
    }

    public function getAdditionalDomains($skip = TRUE) {
        $addDomains = array();
        #foreach (glob($this->backup_path . "/domains/*", GLOB_ONLYDIR) as $filename) {
        foreach (glob($this->backup_path . "/backup/*", GLOB_ONLYDIR) as $filename) {
            if (file_exists($filename . "/domain.conf")) { # See if example.com/domain.conf exists. This indicates it is a configured domain.
                $filename = str_replace($this->backup_path . "/backup/", "", $filename);
                if ($filename != $this->getDomain(FALSE) || $skip == FALSE) {
                    // als dit domein niet het eerste domein is
                    // OF
                    // als we het eerste domein niet willen overslaan
                    $addDomains[] = $filename;

                    if (!in_array($filename, $this->ignore_sites)) {
                        if ($skip == FALSE) {
                            $this->other->Log("Backup->getAdditionalDomains", "Found domain: " . $filename);
                        };
                    } else {
                        if ($skip == FALSE) {
                            $this->other->Log("Backup->getAdditionalDomains", "Domain: " . $filename . " is in banlist. Ignored!");
                        };
                    }
                };
            };
        };

        if (count($addDomains) > 0) {
            $addDomains = array_diff($addDomains, $this->ignore_sites);
            foreach ($addDomains as $domain) {
                $subDomains = array();
                foreach ($this->getSubdomains($domain) as $subdomain) {
                    $subDomains[] = $subdomain . "." . $domain;
                };
                $addDomains = array_diff($addDomains, $subDomains);
            }
            return $addDomains;
        } else {
            $this->other->Log("Backup->getAdditionalDomains", "No additional domains found");
            return FALSE;
        }
    }

    public function getDomain($log = TRUE) {
        $result = $this->getNVP($this->backup_path . "/backup/user.conf", "domain");
        if ($log) {
            $this->other->Log("Backup->getDomain", $result);
        };
        return $result;
    }

    private function readPointerFile($key, $domain) {
        if ($domain == "") {
            echo "ERROR: domain not set\n";
            exit;
        };

        $path = $this->backup_path . "/backup/" . $domain . "/domain.pointers";

        $pointers = array();
        foreach ($this->readFile($path) as $row) {
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
            return array_diff($databases, $this->ignore_db_names);
            //return $databases;
        } else {
            $this->other->Log("Backup->getDatabaseList", "Client does not have any databases", true);
            return false;
        }
    }

    public function getCron() {
        $this->other->Log("Backup->getCron", "Cron replacement");
        echo "sed -i \"s@/home/" . $this->getUsername(FALSE) . "/domains/" . $domain . "/public_html@/var/www/vhosts/" . $this->getDomain(FALSE) . "/httpdocs@g\" " . $this->getPath() . "/backup/crontab.conf\n";
        echo "sed -i \"s/\blocal\/\b//g\" " . $this->getPath() . "/backup/crontab.conf\n";
        echo "cat " . $this->getPath() . "/backup/crontab.conf | crontab -u" . $this->getUsername(FALSE) . " -\n";
    }

    public function getDatabaseLogin($database) {
        $this->other->Log("Backup->getDatabaseLogin", "Retrieving database usernames for database " . $database);
        $user = $this->getUsername(FALSE);
        $logins = array();
        foreach ($this->readFile($this->backup_path . "/backup/" . $database . ".conf") as $row) {
            // Bugfix: DirectAdmin creates a MySQL account for the system user. The old regexp didn't catch this, as it expected $user suffixed by _. 
            // extend the regexp so that the sole username is also allowed.
            if (preg_match("/" . $user . "_/", $row) === 1 || preg_match("/" . $user . "=/", $row) === 1) {
                $line_array = explode("&", $row);
                $temp = explode("=", $line_array[0]);
                $sql_user = $temp[0];
                $sql_pass = str_replace("passwd=", "", $line_array[9]);

                if (!in_array($sql_user, $this->ignore_db_users)) {
                    $this->other->Log("Backup->getDatabaseLogin", "Found user: " . $sql_user . "|" . $sql_pass);
                    $logins[] = array("user" => $sql_user, "pass" => $sql_pass);
                } else {
                    $this->other->Log("Backup->getDatabaseLogin", $sql_user . " is in banlist. Ignored!", true);
                }
            }
        }

        if (count($logins) > 0) {
            //return array_diff($logins, $this->ignore_db_users); // This wont work, because this is a 2dimensional array
            return $logins;
        } else {
            $this->other->Log("Backup->getDatabaseLogin", "WARNING: " . $database . " has no users", true);
            return false;
        };
    }

}
