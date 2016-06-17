<?php

class Plesk {
    
    private function getUsername() {
        return "admin";
    }
    
    private function getPassword() {
        $password = exec("cat /etc/psa/.psa.shadow");
        return $password;
    }
    
    public function isValidReseller($login) {
	
	try {
		$db = new PDO("mysql:host=localhost;dbname=psa", $this->getUsername(), $this->getPassword());
		
		$sql = $db->prepare("SELECT COUNT(*) FROM clients WHERE type = 'reseller' and login = :login");
		$sql->bindParam(':login', $login);
		
		$sql->execute();
		if ($result = $sql->fetchColumn() == 1) {
			return TRUE;
		} else {
			return FALSE;
		}

	} catch (PDOException $e) {
		print("MySQL error: " . $e->getMessage());
		die;
	}
    }
    
    public function getResellers() {
        $resellers = [];
	try {
		$db = new PDO("mysql:host=localhost;dbname=psa", $this->getUsername(), $this->getPassword());
		
		$sql = $db->prepare("SELECT * FROM clients WHERE type = 'reseller'");
		
		$sql->execute();
		$result = $sql->fetchAll();
		foreach ($result as $r) {
			$resellers[$r['login']] = $r;
		}
		return $resellers;

	} catch (PDOException $e) {
		print("MySQL error: " . $e->getMessage());
		die;
	}
    }
    
    public function getServicePlans($reseller = FALSE) {
	try {
		$db = new PDO("mysql:host=localhost;dbname=psa", $this->getUsername(), $this->getPassword());
		
		if ($reseller == FALSE) {
			$sql = $db->prepare("SELECT id, name FROM Templates WHERE type = 'domain' AND owner_id = 1 AND name != 'Admin Simple'");
		} else {
			$sql = $db->prepare("SELECT id, name FROM Templates WHERE type = 'domain' AND owner_id = (SELECT id FROM clients WHERE type = 'reseller' AND login = :reseller)");
			$sql->bindParam(':reseller', $reseller);
		}
		
		$sql->execute();
		$result = $sql->fetchAll();

		foreach ($result as $r) {
			$sp[$r['id']] = $r;
		}
		return $sp;
	} catch (PDOException $e) {
		print("MySQL error: " . $e->getMessage());
		die;
	}
    }
}
?>
