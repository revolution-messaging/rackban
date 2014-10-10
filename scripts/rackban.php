<?php

class Rackban {

    /**
     *
     * Send a command to Rackspace Load Balancer API to ban/unban IPs
     * 
     * @author Oliver Northam <oliver@sidigital.co>
     * @link https://github.com/sidgtl/rackban
     **/
    
    // Your Rackspace cloud account ID
    private $accountId = "12345678";
    
    // Your Rackspace username
    private $username = "exampleuser";
    
    // Your Rackspace API key
    private $apiKey = "kh45kh345k34k345h3k45h";
    
    // Your Rackspace load balancer ID
    private $loadBalancer = array("123456");
    
    // Your Racspace region (ord, dfw, iad, lon, syd, hkg)
    private $region = "lon";
    
    // No need to edit the below
    private $token = false;
    private $curlHeader = array(
        'Content-Type: application/json'
    );

    /*
     * Sends a DENY rule to the Rackspace load balancer
     * 
     * @returns true on success, false on failure
     */
    public function ban($ip) {
        
        // If no token has been defined, get one
        if (!$this->token && !$this->getToken()) {
            throw new Exception("Failed to get token");
        }

        // Grab a preconfigured cURL client
        $ch = $this->getCurlClient();

        // Set POST data for our DENY request
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(array(
                'address' => $ip,
                'type' => 'DENY'
        ))));
        
        $result = true;
        
        foreach($this->loadBalancer as $loadBalancer) {
            // Set the defualt url
            curl_setopt($ch, CURLOPT_URL, "https://{$this->region}.loadbalancers.api.rackspacecloud.com/v1.0/{$this->accountId}/loadbalancers/{$loadBalancer}/accesslist");
            
            // Do request
            curl_exec($ch);
            
            // A 200/202 HTTP code verfies the addition of the IP anything else is a failure
            if (!in_array(curl_getinfo($ch, CURLINFO_HTTP_CODE), array(200, 202))) {
                error_log('Failed to ban IP:'.$ip.' on load balancer:'.$loadBalancer);
                $result = false;
            }
        }
        
        return $result;
    }

    /*
     * Finds an existing rule for the specified address and deletes it
     * 
     * @returns true on success, false on failure
     */
    public function unBan($ip) {
        
        // If no token has been defined, get one
        if (!$this->token && !$this->getToken()) {
            throw new Exception("Failed to get token");
        }
        
        // Grab a preconfigured cURL client
        $ch = $this->getCurlClient();
        
        // Set CURLOPT_POST to false
        curl_setopt($ch, CURLOPT_POST, false);
        
        $acls = array();
        
        foreach($this->loadBalancer as $loadBalancer) {
            // Set the defualt url
            curl_setopt($ch, CURLOPT_URL, "https://{$this->region}.loadbalancers.api.rackspacecloud.com/v1.0/{$this->accountId}/loadbalancers/{$loadBalancer}/accesslist");
            
            // Do request
            $result = curl_exec($ch);
            
            if (!$result) {
                // cURL command failed
                echo 'Failed to curl ACL for load balancer: '.$loadBalancer."\n";
            } else {
                try {
                    $resultJson = json_decode($result);
                } catch(Exception $e) {
                    echo 'JSON decode of access list for load balancer '.$loadBalancer.' threw error: '.$e->message."\n";
                }
                if (!$resultJson || !isset($resultJson->accessList)) {
                    // No access list or JSON data found
                    echo 'Failed to decode JSON ACL for load balancer: '.$loadBalancer."\n";
                } else {
                    $acls[$loadBalancer] = $resultJson->accessList;
                }
            }
        }
        
        $result = true;
        
        foreach($acls as $loadBalancer => $acl) {
            // Loop through each item in the access list
            foreach ($acl as $listing) {
                // Match the accessList item with the requested IP
                if ($listing->address == $ip) {
                    // Change URL to define the accessList ID
                    curl_setopt($ch, CURLOPT_URL, "https://{$this->region}.loadbalancers.api.rackspacecloud.com/v1.0/{$this->accountId}/loadbalancers/{$loadBalancer}/accesslist/{$listing->id}");
                    
                    // Change request to DELETE
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                    
                    // Execute the command
                    curl_exec($ch);
                    
                    // A 200/202 HTTP code verfies the deletion of the IP anything else is a failure
                    if (!in_array(curl_getinfo($ch, CURLINFO_HTTP_CODE), array(200, 202))) {
                        // fail if one of the load balancers is still banning the IP
                        echo 'Failed to unban IP:'.$ip.' on load balancer:'.$loadBalancer."\n";
                        $result = false;
                    }
                }
            }
        }
        
        return $result;
    }

    private function getCurlClient($loadBalancer) {
        
        // Define a cURL client
        $ch = curl_init();

        // Will return the response, if false it print the response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Will this be an authenticated request?
        if ($this->token) {
            $this->curlHeader[] = "X-Auth-Token: {$this->token}";
        }

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->curlHeader);

        return $ch;
    }

    private function getToken() {
        
        // Grab a preconfigured cURL client
        $ch = $this->getCurlClient();

        // Override the URL to the token endpoint
        curl_setopt($ch, CURLOPT_URL, "https://identity.api.rackspacecloud.com/v2.0/tokens");

        // Set POST to true
        curl_setopt($ch, CURLOPT_POST, true);

        // Set POST data for authenticaton 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            "auth" => array(
                "RAX-KSKEY:apiKeyCredentials" => array(
                    "username" => $this->username,
                    "apiKey" => $this->apiKey
                )
            )
        )));

        // Do request
        $result = curl_exec($ch);

        if ($result) {
            $resultJson = json_decode($result);

            // Look for our required decided JSON data
            if ($resultJson && isset($resultJson->access->token->id)) {
                
                // Set the token for this session
                $this->token = $resultJson->access->token->id;
                
                return true;
            }
        }

        return false;
    }

}

if (isset($argv[1]) && isset($argv[2])) {
    $rackBan = new Rackban();

    if ($argv[1] == "ban") {
        if($rackBan->ban($argv[2])) {
           echo "\nSuccess!\n\n"; 
        } else {
            echo "\nFailure!\n\n";
        }
    } elseif ($argv[1] == "unban") {
        if($rackBan->unBan($argv[2])) {
            echo "\nSuccess!\n\n"; 
        } else {
            echo "\nFailure!\n\n";
        }
    } else {
        echo "\nNot a valid command";
    }
} else {
    echo "\nRackban - By Oliver Northam at Si digital (https://github.com/sidgtl)\n\n";
    echo "Commands:\n";
    echo "ban <ip> - Adds an IP to your defined load balancer node\n";
    echo "unban <ip> - Removes an IP to your defined load balancer node\n\n";
}