<?php

/*
  include("dnsparser.php");

  $domain = preg_replace("/.db$/", "", $argv[1]);
  $domain = preg_replace("/.*\//", "", $domain);

  compareZones($argv[1], $domain);
  #return;
 */

class DNS {
    private $record_types = array('SOA', 'A', 'NS', 'MX', 'CNAME', 'PTR', 'TXT', 'AAAA', 'SRV');
    private $removed = array();
    private $toadd = array();
    private $oldIPv4;
    private $oldIPv6 = ""; # TODO
    private $newIPv4;
    private $newIPv6;
    private $other;
    
    private $apiDoUpdate, $apiUP, $apiData, $apiUrl;
    
    public function __construct($apiDoUpdate, $apiUP, $apiData, $apiUrl, $filters, $ipv4, $ipv6, $debug) {
        $this->other = new Other();
        $this->other->setDebug($debug);
        
        $this->apiDoUpdate = $apiDoUpdate;
        $this->apiUP = $apiUP;
        $this->apiData = $apiData;
        $this->apiUrl = $apiUrl;
        $this->filters = $filters;
        
        $this->newIPv4 = $ipv4;
        $this->newIPv6 = $ipv6;
    }
    
    private function isDomainInOurControl($domain) {
        if (strlen($domain) == 0) {
            die("domain cannot be empty on " . __FILE__ . " at " . __LINE__);
        }
        
        $cnt = 0;
        $result = dns_get_record($domain, DNS_NS, $authns, $addtl);
        $isOurs = TRUE;
        
        if (count($result) < 1) {
                $this->other->Log("DNS->isDomainInOurControl", "No list of nameservers received!", false);
                $isOurs = FALSE;
        }

        foreach ($result as $ns) {
                foreach ($this->filters as $filter) {
                        if (preg_match($filter, $ns['target'])) {
                                #print("# MATCH: " . $ns['target'] . "\n");
                                $cnt++;
                        }
                }
        }

        if (count($result) > $cnt && $cnt > 0) {
                $this->other->Log("DNS->isDomainInOurControl", "List of nameservers larger than expected!", false);
        } else if ($cnt < 2) {
                $this->other->Log("DNS->isDomainInOurControl", "Nameservers did not match", false);
                $isOurs = FALSE;
        }

        return $isOurs;
    }
    
    public function updateNS($domain) {
        if ($this->isDomainInOurControl($domain) == TRUE && $this->apiDoUpdate == TRUE) {
            $postFields = str_replace("#DOMAIN#", $domain, $this->apiData);
            /*
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_USERAGENT, "DirectAdmin 2 Plesk Migration Script");
            
            if (strlen($this->apiUP) > 0) {
                curl_setopt($ch, CURLOPT_USERPWD, $this->apiUP);
            }
            // this is better:
            // curl_setopt($ch, CURLOPT_POSTFIELDS, 
            //          http_build_query(array('postvar1' => 'value1')));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $server_output = curl_exec ($ch);
            curl_close ($ch);

            // TODO: further processing ....
            #if ($server_output == "OK") { ... } else { ... };
             * 
             */
            echo "\ncurl -u " . $this->apiUP . " --data \"" . $postFields . "\" " . $this->apiUrl . "\n\n";
            
        } else {
            $this->other->Log("DNS->updateNS", "Domain " . $domain . " DNS is not within our control or API updating is disabled", false);
        }
    }
    
    public function parseDNS($zone_file) {

        if (!file_exists($zone_file)) {
            #TODO: Can be done neater.
            echo "########### CRITICAL ERROR ############";
            echo "Filename " . $zone_file . " does not exists";
            echo "########### CRITICAL ERROR ############";
            exit;
        }
        $file = file_get_contents($zone_file);

        $file = str_replace("
", "\n", $file);

        $checksum = sha1($file);

        $raw_array = explode("\n", $file);

//convert zone file to array - removing empty rowes and spaces
        $convert_array = array();
        $i = 0;

        foreach ($raw_array as $v) {
            $trim = trim($v);
            if (preg_match('/^;/', $trim))
                continue;

            if (!empty($trim)) {
                $data_array = array();
                $trim = preg_replace("/\t/", " ", $trim);
                $trim = preg_replace("/\s\s+/", " ", $trim);
                $data_array[] = $raw_array = explode(" ", $trim);
                foreach ($raw_array as $v) {
                    $trim = trim($v);
                    if (empty($trim)) {
                        
                    } elseif ($trim == "IN") {
                        
                    } elseif ($trim == "(") {
                        
                    } elseif ($trim == ")") {
                        
                    } else {
                        $convert_array[$i][] = $trim;
                    }
                }
            }
            $i++;
        }

//remove comments
        $comment_array = $convert_array;
        $comment = false;
        foreach ($comment_array as $array_k => $array_v) {
            foreach ($array_v as $k => $v) {
                if ($v == ";" || $comment == true) {
                    unset($convert_array[$array_k][$k]);
                    $comment = true;
                }
            }
            if (!isset($convert_array[$array_k][0])) {
                unset($convert_array[$array_k]);
            }
            $comment = false;
        }

//remove TTL from records except SOA
        $ttl_array = $convert_array;
        $ttl_value = false;
        foreach ($comment_array as $array_k => $array_v) {
            foreach ($array_v as $k => $v) {
                if ($v == "\$TTL") {
                    $ttl_value = true;
                } elseif ($ttl_value == true) {
                    $ttl = $v;
                    unset($convert_array[$array_k]);
                }
            }
            $ttl_value = false;
            if (isset($ttl)) {
                if (isset($convert_array[$array_k][1]) && $ttl == $convert_array[$array_k][1]) {
                    unset($convert_array[$array_k][1]);
                }
            }
        }

//is the first one a record type if so, replace it with previous record name
        $record_array = $convert_array;
        $record_value = '';
        foreach ($record_array as $k => $v) {
            foreach ($this->record_types as $type) {
                if ($v[0] == $type) {
                    $v = array_reverse($v);
                    $v[] = $record_value;
                    $v = array_reverse($v);
                    $convert_array[$k] = $v;
                }
            }

            $record_value = $v[0];
        }

//generate new indexes
        $array = $convert_array;
        $convert_array = array();
        $i = 0;
        foreach ($array as $v1) {
            foreach ($v1 as $v2) {
                $convert_array[$i][] = $v2;
            }
            $i++;
        }

//generate SOA and TXT records
        $zone_array = explode('.', $zone_file);
        $zone_array = array_reverse($zone_array);
        unset($zone_array[0]);
        $zone_array = array_reverse($zone_array);
        $domain = implode('.', $zone_array);


        $fqdn_array = $convert_array;

        foreach ($fqdn_array as $k => $v) {
            if (isset($v[1]) && $v[1] == "SOA") {
                $row = $k;
                $soa = $v[2] . " " . $v[3];
                $k++;
                $soa .= " " . $convert_array[$k][0];
                unset($convert_array[$k][0]);
                $k++;
                $soa .= " " . $convert_array[$k][0];
                $convert_array[$row][3] = $convert_array[$k][0];
                unset($convert_array[$k][0]);
                $k++;
                $soa .= " " . $convert_array[$k][0];
                unset($convert_array[$k][0]);
                $k++;
                $soa .= " " . $convert_array[$k][0];
                unset($convert_array[$k][0]);
                $k++;
                $soa .= " " . $convert_array[$k][0];
                unset($convert_array[$k][0]);

                $convert_array[$row][2] = $soa;
                $convert_array[$row][4] = $checksum;
            } elseif (isset($v[1]) && $v[1] == "TXT") {
                $count = count($v);
                $txt = $v[2];
                for ($i = 3; $i < $count; $i++) {
                    $txt .= " " . $v[$i];
                    unset($convert_array[$k][$i]);
                }
                $convert_array[$k][2] = $txt;
            }
        }

//generate new indexes
        $array = $convert_array;
        $convert_array = array();
        $i = 0;
        foreach ($array as $v1) {
            foreach ($v1 as $v2) {
                $convert_array[$i][] = $v2;
            }
            $i++;
        }

//generate FQDN
        $zone_array = explode('.', $zone_file);
        $zone_array = array_reverse($zone_array);
        unset($zone_array[0]);
        $zone_array = array_reverse($zone_array);
        $domain = implode('.', $zone_array);
        $crap = explode('/', $domain);
        $crap = array_reverse($crap);
        $domain = $crap[0];

        $fqdn_array = $convert_array;
        foreach ($fqdn_array as $k => $v) {
            foreach ($this->record_types as $type) {
                if (isset($v[1]) && $v[1] == $type) {
                    $check_array = explode(".", $v[0]);
                    $check_array = array_reverse($check_array);
                    if ($check_array[0] == '') {
                        unset($check_array[0]);
                        $check_array = array_reverse($check_array);
                        $convert_array[$k][0] = implode('.', $check_array);
                    } elseif ($v[0] == "@") {
                        $convert_array[$k][0] = $domain;
                    } elseif ($v[0] == $domain . ".") {
                        $convert_array[$k][0] = $domain;
                    } else {
                        $convert_array[$k][0] = $v[0] . "." . $domain;
                    }
                }
            }
        }

//array sorten
        $array = array();
        $i = 0;
        foreach ($convert_array as $v1) {
            foreach ($v1 as $v2) {
                $array[$i][] = $v2;
            }
            $i++;
        }

        return $array;
    }

    /*
     TODO: This function isnt used? 
    private function identicalValues($arrayA, $arrayB) {

        sort($arrayA);
        sort($arrayB);

        return $arrayA == $arrayB;
    }
    */
    
    private function removeMailRecord($domain) {
        $delstring = "/opt/psa/bin/dns --del " . $domain . " -a mail -ip " . $this->newIPv4;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);

        $delstring = "/opt/psa/bin/dns --del " . $domain . " -aaaa mail -ip " . $this->newIPv6;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);
    }

    private function removeWWWRecord($domain) {
        $delstring = "/opt/psa/bin/dns --del " . $domain . " -cname www -canonical " . $domain;

        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);
    }

    private function removeWebMailRecord($domain) {
        $delstring = "/opt/psa/bin/dns --del " . $domain . " -cname webmail -canonical " . $domain;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);
    }

    private function checkSrvRecord($record) {
        #TODO: Should not be hardcoded.!
        if (!preg_match("/cpanelemaildiscovery.cpanel.net/", $record[3]) && !preg_match("/metis.X.eu/", $record[3]))
            print("# SRV RECORD CHECK MANUALLY\n"); #TODO: Que?
    }

    private function removeTopRecord($domain) {
        $delstring = "/opt/psa/bin/dns --del " . $domain . " -a '' -ip " . $this->newIPv4;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);

        $delstring = "/opt/psa/bin/dns --del " . $domain . " -aaaa '' -ip " . $this->newIPv6;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);
    }

    private function removeNsRecord($domain) {
        #TODO: Hardcoded NS records. Retrieve these from the DNS zone template.
        $delstring = "/opt/psa/bin/dns --del " . $domain . " -ns '' -nameserver ns1.exsilia.net";
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);

        $delstring = "/opt/psa/bin/dns --del " . $domain . " -ns '' -nameserver ns2.exsilia.net";
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);
    }

    private function removeWildcardRecord($domain) {
        $delstring = "/opt/psa/bin/dns --del " . $domain . " -a '*' -ip " . $this->newIPv4;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);

        $delstring = "/opt/psa/bin/dns --del " . $domain . " -aaaa '*' -ip " . $this->newIPv6;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);
    }

    private function removeMxRecord($domain) {
        $delstring = "/opt/psa/bin/dns --del " . $domain . " -mx '' -mailexchanger mail." . $domain;
        if (array_search($delstring, $this->removed) === FALSE)
            array_push($this->removed, $delstring);
    }

    private function addRecord($string) {
        if (array_search($string, $this->toadd) === FALSE)
            array_push($this->toadd, $string);
    }

    private function isType($string) {
        if (in_array($string, $this->record_types)) {
            return TRUE;
        } else {
            return false;
        };
    }

    private function checkAndRemoveExisting($record, $domain) {
        if (count($record) < 3) // te weinig records
            return;

        if (count($record) >= 5) {
            $offset = 2;
        } else {
            $offset = 1;
        }

        if ($this->isType($record[$offset]) != true) { // check if the record at the offset is actually a DNS type. if not, raise the offset.
            $offset++;
        }

        if ($record[$offset + 1] == $this->newIPv4 || $record[$offset + 1] == "127.0.0.1") { // wordt al geregeld met wildcards, hoeft geen apart record voor te komen
            return;
        }

        if (preg_match("/^www$/i", $record[0])) {
            $this->removeWWWRecord($domain);
        }

        if (preg_match("/^webmail$/i", $record[0])) {
            $this->removeWebMailRecord($domain);
        }

        if (preg_match("/^mail$/i", $record[0])) {
            $this->removeMailRecord($domain);
        }

        if (preg_match("/^\*/", $record[0])) { // Wildcard
            $this->removeWildcardRecord($domain);
        }

        switch ($record[$offset]) {
            case "A":
                #if ($record[0] == "" || $record[0] == $domain) {
                if ($record[0] == "" || $record[0] == "$domain.") {
                    $this->removeTopRecord($domain);
                    $host = "";
                } else {
                    $host = preg_replace("/.$domain/", "", $record[0]);
                }

                #TODO: Add this to config.
                if (preg_match('/mailbackup/', $record[0]))
                    break; // skip antiquated mailbackup records

                $this->addRecord("/opt/psa/bin/dns --add " . $domain . " -a '" . $host . "' -ip " . $record[$offset + 1]);
                break;
            case "CNAME":
                $host = preg_replace("/.$domain/", "", $record[0]);
                $canonical = rtrim($record[$offset + 1], '.');
                $this->addRecord("/opt/psa/bin/dns --add " . $domain . " -cname '" . $host . "' -canonical " . $canonical);
                break;
            case "NS":
//                       if ($record[$offset + 1] != "ns1.X.nl." && $record[$offset + 1] != "ns2.X.nl." && $record[$offset + 1] != "ns3.X.nl.") {
//                                $this->removeNsRecord($domain);
//                                $canonical = rtrim($record[$offset + 1], '.');
//                                $this->addRecord("/opt/psa/bin/dns --add " . $domain . " -ns '' -nameserver " . $canonical . "\n");
//                        }
                break;
            case "TXT":
                if (preg_match("/" . $this->oldIPv4 . "/", $record[$offset + 1]) != false || preg_match("/DKIM/", $record[$offset + 1]) != false) { // skip SPF and DKIM
                    $this->other->Log("DNS->checkAndRemoveExisting", "TXT record matches old IP, skipping", false);
                    break;
                }
                $this->addRecord("/opt/psa/bin/dns --add " . $domain . " -txt " . $record[$offset + 1]);
                break;
            case "SRV":
                $this->checkSrvRecord($record);
                break;
            case "MX":
                if (count($record) <= 3) {
                    $mx = rtrim($record[$offset + 1], '.');
                    $priority = 0;
                } else {
                    $mx = rtrim($record[$offset + 2], '.');
                    $priority = $record[$offset + 1];
                }

                if (preg_match("/mailbackup/", $mx))
                    break; // skip antiquated mailbackup records

                if ($mx == $domain) {
                    $this->other->Log("DNS->checkAndRemoveExisting", "MX remains the same", false);
                } else {
                    $this->removeMxRecord($domain);
                    if (!preg_match("/\./", $mx) && $mx[(count($mx) - 1)] != ".") {
                        $mx = $mx . "." . $domain;
                    }
                    $this->addRecord("/opt/psa/bin/dns --add " . $domain . " -mx '' -mailexchanger " . $mx . " -priority " . $priority);
                }
                break;
            default:
                break;
        }
    }

    public function getDNSChanges($zoneFile, $oldIPv4) {
        /* TODO: Fix IPv6 records. We should iterate through oldIPv4 / oldIPv6 and replace them with newIps */
        
        $this->oldIPv4 = $oldIPv4;

        $domain = preg_replace("/.db$/", "", $zoneFile);
        $domain = preg_replace("/.*\//", "", $domain);        
        
        $zone = $this->parseDNS($zoneFile);
        
        #$this->pleskGetIP();
        foreach ($zone as $record) {
            
            if (count($record) < 3) // te weinig
                continue;

            if (count($record) > 2 && preg_match('/SOA/', $record[1]) || preg_match('/::1/', $record[2])) {
                continue;
            }

            /* TODO: Check if this works properly. newIps[0] looks to me as only first IP address is replaced. What if this is an Ipv6 record? */
            if (count($record) > 3) {
                if ($record[3] == $this->oldIPv4) {
                    $record[3] = $this->newIPv4;
                }
                if ($record[3] == $this->oldIPv6) {
                    $record[3] = $this->newIPv6;
                }
            } else {
                if ($record[2] == $this->oldIPv4) {
                    $record[2] = $this->newIPv4;
                };
                if ($record[2] == $this->oldIPv6) {
                    $record[2] = $this->newIPv6;
                };
            }
            #var_dump($record); 
            $this->checkAndRemoveExisting($record, $domain);
        }

        $returnValues = array_merge($this->removed, $this->toadd);
        
        $this->toadd = array();
        $this->removed = array();

        $this->updateNS($domain);
        return $returnValues;
    }

}
