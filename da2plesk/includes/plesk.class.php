<?php

class Plesk {
    
    private function getUsername() {
        return "admin";
    }
    
    private function getPassword() {
        $password = exec("cat /etc/psa/.psa.shadow");
        return $password;
    }
    
    public function getServicePlans() {
        $plesk_db = mysql_connect("localhost", $this->getUsername(), $this->getPassword());
        mysql_select_db("psa", $plesk_db);

        $plesk_query = "SELECT id, name FROM Templates WHERE type = 'domain' AND owner_id = 1 AND name != 'Admin Simple'";
        $plesk_result = mysql_query($plesk_query);

        $sp = array();
        while($row = mysql_fetch_assoc($plesk_result)) {
          $sp[$row['id']] = $row;
        }

        return $sp;
    }
}
?>