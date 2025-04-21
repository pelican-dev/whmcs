<?php

/**
MIT License

Copyright (c) 2018-2019 Stepan Fedotov <stepan@crident.com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
**/

if(!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use Illuminate\Database\Capsule\Manager as Capsule;

function pelican_GetHostname(array $params) {
    $hostname = $params['serverhostname'];
    if ($hostname === '') throw new Exception('Could not find the panel\'s hostname - did you configure server group for the product?');

    // For whatever reason, WHMCS converts some characters of the hostname to their literal meanings (- => dash, etc) in some cases
    foreach([
        'DOT' => '.',
        'DASH' => '-',
    ] as $from => $to) {
        $hostname = str_replace($from, $to, $hostname);
    }

    if(ip2long($hostname) !== false) $hostname = 'http://' . $hostname;
    else $hostname = ($params['serversecure'] ? 'https://' : 'http://') . $hostname;

    return rtrim($hostname, '/');
}

function pelican_API(array $params, $endpoint, array $data = [], $method = "GET", $dontLog = false) {
    $url = pelican_GetHostname($params) . '/api/application/' . $endpoint;

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    curl_setopt($curl, CURLOPT_USERAGENT, "Pelican-WHMCS");
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_POSTREDIR, CURL_REDIR_POST_301);
    curl_setopt($curl, CURLOPT_TIMEOUT, 5);

    $headers = [
        "Authorization: Bearer " . $params['serverpassword'],
        "Accept: application/json",
    ];

    if($method === 'POST' || $method === 'PATCH') {
        $jsonData = json_encode($data);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
        array_push($headers, "Content-Type: application/json");
        array_push($headers, "Content-Length: " . strlen($jsonData));
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($curl);
    $responseData = json_decode($response, true);
    $responseData['status_code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if($responseData['status_code'] === 0 && !$dontLog) logModuleCall("Pelican-WHMCS", "CURL ERROR", curl_error($curl), "");

    curl_close($curl);

    if(!$dontLog) logModuleCall("Pelican-WHMCS", $method . " - " . $url,
        isset($data) ? json_encode($data) : "",
        print_r($responseData, true));

    return $responseData;
}

function pelican_Error($func, $params, Exception $err) {
    logModuleCall("Pelican-WHMCS", $func, $params, $err->getMessage(), $err->getTraceAsString());
}

function pelican_MetaData() {
    return [
        "DisplayName" => "Pelican",
        "APIVersion" => "1.1",
        "RequiresServer" => true,
    ];
}

function pelican_ConfigOptions() {
    return [
        "cpu" => [
            "FriendlyName" => "CPU Limit (%)",
            "Description" => "Amount of CPU to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "disk" => [
            "FriendlyName" => "Disk Space (MiB)",
            "Description" => "Amount of Disk Space to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "memory" => [
            "FriendlyName" => "Memory (MiB)",
            "Description" => "Amount of Memory to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "swap" => [
            "FriendlyName" => "Swap (MiB)",
            "Description" => "Amount of Swap to assign to the created server.",
            "Type" => "text",
            "Size" => 10,
        ],
        "tags" => [
            "FriendlyName" => "Node Tags",
            "Description" => "Comma-separated list of tags to use for node selection. Leave empty for any node.",
            "Type" => "text",
            "Size" => 25,
        ],
        "dedicated_ip" => [
            "FriendlyName" => "Dedicated IP",
            "Description" => "Assign dedicated ip to the server (optional)",
            "Type" => "yesno",
        ],
        "egg_id" => [
            "FriendlyName" => "Egg ID",
            "Description" => "ID of the Egg for the server to use.",
            "Type" => "text",
            "Size" => 10,
        ],
        "io" => [
            "FriendlyName" => "Block IO Weight",
            "Description" => "Block IO Adjustment number (10-1000)",
            "Type" => "text",
            "Size" => 10,
            "Default" => "500",
        ],
        "port_ranges" => [
            "FriendlyName" => "Port Ranges",
            "Description" => "Port ranges in format start-end,start-end (e.g. 2001-2020,27015-27025). Will auto-increment within ranges until free port found.",
            "Type" => "text",
            "Size" => 25,
        ],
        "startup" => [
            "FriendlyName" => "Startup",
            "Description" => "Custom startup command to assign to the created server (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "image" => [
            "FriendlyName" => "Image",
            "Description" => "Custom Docker image to assign to the created server (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "databases" => [
            "FriendlyName" => "Databases",
            "Description" => "Client will be able to create this amount of databases for their server (optional)",
            "Type" => "text",
            "Size" => 10,
        ],
    	"server_name" => [
            "FriendlyName" => "Server Name",
            "Description" => "The name of the server as shown on the panel (optional)",
            "Type" => "text",
            "Size" => 25,
        ],
        "oom_killer" => [
            "FriendlyName" => "Enable OOM Killer",
            "Description" => "Should the Out Of Memory Killer be enabled (optional)",
            "Type" => "yesno",
        ],
        "backups" => [
            "FriendlyName" => "Backups",
            "Description" => "Client will be able to create this amount of backups for their server (optional)",
            "Type" => "text",
            "Size" => 10,
        ],
        "allocations" => [
            "FriendlyName" => "Allocations",
            "Description" => "Client will be able to create this amount of allocations for their server (optional)",
            "Type" => "text",
            "Size" => 10,
        ],
    ];
}

function pelican_TestConnection(array $params) {
    $solutions = [
        0 => "Check module debug log for more detailed error.",
        401 => "Authorization header either missing or not provided.",
        403 => "Double check the password (which should be the Application Key).",
        404 => "Result not found.",
        422 => "Validation error.",
        500 => "Panel errored, check panel logs.",
    ];

    $err = "";
    try {
        $response = pelican_API($params, 'nodes');

        if($response['status_code'] !== 200) {
            $status_code = $response['status_code'];
            $err = "Invalid status_code received: " . $status_code . ". Possible solutions: "
                . (isset($solutions[$status_code]) ? $solutions[$status_code] : "None.");
        } else {
            if($response['meta']['pagination']['count'] === 0) {
                $err = "Authentication successful, but no nodes are available.";
            }
        }
    } catch(Exception $e) {
        pelican_Error(__FUNCTION__, $params, $e);
        $err = $e->getMessage();
    }

    return [
        "success" => $err === "",
        "error" => $err,
    ];
}

function random($length) {
    if (class_exists("\Illuminate\Support\Str")) {
        return \Illuminate\Support\Str::random($length);
    } else if (function_exists("str_random")) {
        return str_random($length);
    } else {
        throw new \Exception("Unable to find a valid function for generating random strings");
    }
}

function pelican_GenerateUsername($length = 8) {
    $returnable = false;
    while (!$returnable) {
        $generated = random($length);
        if (preg_match('/[A-Z]+[a-z]+[0-9]+/', $generated)) {
            $returnable = true;
        }
    }
    return $generated;
}

function pelican_GetOption(array $params, $id, $default = NULL) {
    $options = pelican_ConfigOptions();

    $friendlyName = $options[$id]['FriendlyName'];
    if(isset($params['configoptions'][$friendlyName]) && $params['configoptions'][$friendlyName] !== '') {
        return $params['configoptions'][$friendlyName];
    } else if(isset($params['configoptions'][$id]) && $params['configoptions'][$id] !== '') {
        return $params['configoptions'][$id];
    } else if(isset($params['customfields'][$friendlyName]) && $params['customfields'][$friendlyName] !== '') {
        return $params['customfields'][$friendlyName];
    } else if(isset($params['customfields'][$id]) && $params['customfields'][$id] !== '') {
        return $params['customfields'][$id];
    }

    $found = false;
    $i = 0;
    foreach(pelican_ConfigOptions() as $key => $value) {
        $i++;
        if($key === $id) {
            $found = true;
            break;
        }
    }

    if($found && isset($params['configoption' . $i]) && $params['configoption' . $i] !== '') {
        return $params['configoption' . $i];
    }

    return $default;
}

function pelican_GetAvailablePorts(array $params, array $ranges) {
    // Get all nodes that match the tags
    $tags = pelican_GetOption($params, 'tags');
    $tags = !empty($tags) ? explode(',', $tags) : [];
    
    // Build query string for the request
    $queryParams = [
        'memory' => (int) pelican_GetOption($params, 'memory', 0),
        'disk' => (int) pelican_GetOption($params, 'disk', 0),
        'cpu' => (int) pelican_GetOption($params, 'cpu', 0),
    ];

    // Add tags as query parameters if they exist
    if (!empty($tags)) {
        foreach ($tags as $tag) {
            $queryParams['tags[]'] = $tag;
        }
    }

    // Build the query string
    $queryString = http_build_query($queryParams);
    
    // Make the API call with query parameters
    $nodesResult = pelican_API($params, 'nodes/deployable?' . $queryString, [], 'GET');

    if ($nodesResult['status_code'] !== 200) {
        throw new Exception('Failed to get deployable nodes, received error code: ' . $nodesResult['status_code'] . '. ' . 
            (isset($nodesResult['errors'][0]['detail']) ? $nodesResult['errors'][0]['detail'] : ''));
    }

    // Log the deployable nodes
    logModuleCall("Pelican-WHMCS", "Deployable Nodes", "Nodes", print_r($nodesResult, true));

    $availablePorts = [];
    $portsFound = 0;
    $maxPortsNeeded = count($ranges); // We need one port from each range

    // For each node, check port availability using the allocations from the deployable nodes response
    foreach ($nodesResult['data'] as $node) {
        if ($portsFound >= $maxPortsNeeded) break; // Stop if we found all needed ports

        $nodeId = $node['attributes']['id'];
        
        // Get all allocations for this node to check port availability
        $allocationsResult = pelican_API($params, 'nodes/' . $nodeId . '/allocations', [], 'GET');
        if ($allocationsResult['status_code'] !== 200) {
            continue; // Skip this node if we can't get allocations
        }

        // Log the allocations for this node
        logModuleCall("Pelican-WHMCS", "Node Allocations", "Node ID: " . $nodeId, print_r($allocationsResult, true));

        // Create a map of used ports from the node's allocations
        $usedPorts = [];
        if (isset($allocationsResult['data'])) {
            foreach ($allocationsResult['data'] as $allocation) {
                // Only mark ports as used if they are actually assigned (assigned = 1)
                if (isset($allocation['attributes']['assigned']) && $allocation['attributes']['assigned'] == 1) {
                    $usedPorts[$allocation['attributes']['port']] = true;
                }
            }
        }

        // Log the used ports
        logModuleCall("Pelican-WHMCS", "Used Ports", "Node ID: " . $nodeId, print_r($usedPorts, true));

        // Check each range for available ports
        foreach ($ranges as $rangeIndex => $range) {
            if ($portsFound >= $maxPortsNeeded) break; // Stop if we found all needed ports
            
            list($start, $end) = explode('-', $range);
            $start = (int) $start;
            $end = (int) $end;

            // Log the range being checked
            logModuleCall("Pelican-WHMCS", "Checking Range", "Range: " . $start . "-" . $end, "");

            // Find first available port in range
            for ($port = $start; $port <= $end; $port++) {
                if (!isset($usedPorts[$port])) {
                    $availablePorts[] = [
                        'node_id' => $nodeId,
                        'ip' => $allocationsResult['data'][0]['attributes']['ip'],
                        'port' => $port
                    ];
                    $portsFound++;
                    
                    // Log the found port
                    logModuleCall("Pelican-WHMCS", "Found Port", "Port: " . $port . " on Node: " . $nodeId, "");
                    
                    break; // Found a port in this range, move to next range
                }
            }
        }
    }

    // If we didn't find enough ports, throw an exception
    if ($portsFound < $maxPortsNeeded) {
        throw new Exception('Could not find enough available ports. Found ' . $portsFound . ' of ' . $maxPortsNeeded . ' needed ports.');
    }

    // Add debug logging
    logModuleCall("Pelican-WHMCS", "Port Range Debug", "Ranges", print_r($ranges, true));
    logModuleCall("Pelican-WHMCS", "Port Range Debug", "Available Ports", print_r($availablePorts, true));

    return $availablePorts;
}

function pelican_CreateAccount(array $params) {
    try {
        $serverId = pelican_GetServerID($params);
        if(isset($serverId)) throw new Exception('Failed to create server because it is already created.');

        // Process port ranges
        $portRanges = pelican_GetOption($params, 'port_ranges');
        $ranges = !empty($portRanges) ? explode(',', $portRanges) : [];
        $validRanges = [];
        
        // Log the raw port ranges
        logModuleCall("Pelican-WHMCS", "Raw Port Ranges", "Port Ranges", print_r($portRanges, true));
        logModuleCall("Pelican-WHMCS", "Exploded Ranges", "Ranges", print_r($ranges, true));
        
        foreach ($ranges as $range) {
            $range = trim($range);
            if (empty($range)) {
                logModuleCall("Pelican-WHMCS", "Empty Range", "Skipping empty range", "");
                continue;
            }
            
            if (preg_match('/^\d+-\d+$/', $range)) {
                $validRanges[] = $range;
                logModuleCall("Pelican-WHMCS", "Valid Range", "Added range: " . $range, "");
            } else {
                logModuleCall("Pelican-WHMCS", "Invalid Range", "Invalid range format: " . $range, "");
            }
        }
        
        // Log the valid ranges
        logModuleCall("Pelican-WHMCS", "Valid Ranges", "Valid Ranges", print_r($validRanges, true));

        $availablePorts = [];
        if (!empty($validRanges)) {
            $availablePorts = pelican_GetAvailablePorts($params, $validRanges);
            if (empty($availablePorts)) {
                throw new Exception('No available ports found in the specified ranges');
            }
        }

        $userResult = pelican_API($params, 'users/external/' . $params['clientsdetails']['id']);
        if($userResult['status_code'] === 404) {
            $userResult = pelican_API($params, 'users?filter[email]=' . urlencode($params['clientsdetails']['email']));
            if($userResult['meta']['pagination']['total'] === 0) {
                $userResult = pelican_API($params, 'users', [
                    'username' => pelican_GetOption($params, 'username', pelican_GenerateUsername()),
                    'email' => $params['clientsdetails']['email'],
                    'first_name' => $params['clientsdetails']['firstname'],
                    'last_name' => $params['clientsdetails']['lastname'],
                    'external_id' => (string) $params['clientsdetails']['id'],
                ], 'POST');
            } else {
                foreach($userResult['data'] as $key => $value) {
                    if($value['attributes']['email'] === $params['clientsdetails']['email']) {
                        $userResult = array_merge($userResult, $value);
                        break;
                    }
                }
                $userResult = array_merge($userResult, $userResult['data'][0]);
            }
        }

        if($userResult['status_code'] === 200 || $userResult['status_code'] === 201) {
            $userId = $userResult['attributes']['id'];
        } else {
            throw new Exception('Failed to create user, received error code: ' . $userResult['status_code'] . '. Enable module debug log for more info.');
        }

        $eggId = pelican_GetOption($params, 'egg_id');

        $eggData = pelican_API($params, 'eggs/' . $eggId . '?include=variables');
        if($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');

        $environment = [];
        foreach($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
            $attr = $val['attributes'];
            $var = $attr['env_variable'];
            $default = $attr['default_value'];
            $friendlyName = pelican_GetOption($params, $attr['name']);
            $envName = pelican_GetOption($params, $attr['env_variable']);

            if(isset($friendlyName)) $environment[$var] = $friendlyName;
            elseif(isset($envName)) $environment[$var] = $envName;
            else $environment[$var] = $default;
        }

        $name = pelican_GetOption($params, 'server_name', pelican_GenerateUsername() . '_' . $params['serviceid']);
        $memory = pelican_GetOption($params, 'memory');
        $swap = pelican_GetOption($params, 'swap');
        $io = pelican_GetOption($params, 'io');
        $cpu = pelican_GetOption($params, 'cpu');
        $disk = pelican_GetOption($params, 'disk');
        $tags = pelican_GetOption($params, 'tags');
        $tags = !empty($tags) ? explode(',', $tags) : [];
        $dedicated_ip = pelican_GetOption($params, 'dedicated_ip') ? true : false;
        $image = pelican_GetOption($params, 'image', $eggData['attributes']['docker_image']);
        $startup = pelican_GetOption($params, 'startup', $eggData['attributes']['startup']);
        $databases = pelican_GetOption($params, 'databases');
        $allocations = pelican_GetOption($params, 'allocations');
        $backups = pelican_GetOption($params, 'backups');
        $oom_killer = pelican_GetOption($params, 'oom_killer') ? true : false;

        // If we found a specific node with available ports, use it
        $nodeId = !empty($availablePorts) ? $availablePorts[0]['node_id'] : null;
        
        // Use the available ports we already found
        $portRange = [];
        $addAllocations = [];
        
        if (!empty($availablePorts)) {
            // First port becomes the primary port range
            $portRange[] = (string)$availablePorts[0]['port'];
            
            // Get allocation IDs for additional ports
            $allocationsResult = pelican_API($params, 'nodes/' . $nodeId . '/allocations', [], 'GET');
            if ($allocationsResult['status_code'] === 200 && isset($allocationsResult['data'])) {
                foreach ($allocationsResult['data'] as $allocation) {
                    for ($i = 1; $i < count($availablePorts); $i++) {
                        if ($allocation['attributes']['port'] == $availablePorts[$i]['port']) {
                            $addAllocations[] = $allocation['attributes']['id'];
                            break;
                        }
                    }
                }
            }
        }

        $serverData = [
            'name' => $name,
            'user' => (int) $userId,
            'egg' => (int) $eggId,
            'docker_image' => $image,
            'startup' => $startup,
            'oom_killer' => $oom_killer,
            'limits' => [
                'memory' => (int) $memory,
                'swap' => (int) $swap,
                'io' => (int) $io,
                'cpu' => (int) $cpu,
                'disk' => (int) $disk,
            ],
            'feature_limits' => [
                'databases' => $databases ? (int) $databases : null,
                'allocations' => count($availablePorts), // Set allocation limit to match number of ports
                'backups' => (int) $backups,
            ],
            'deploy' => [
                'tags' => $tags,
                'dedicated_ip' => $dedicated_ip,
                'port_range' => $portRange,
            ],
            'environment' => $environment,
            'start_on_completion' => true,
            'external_id' => (string) $params['serviceid'],
        ];

        // If we found a specific node with available ports, use it
        if ($nodeId) {
            $serverData['node_id'] = $nodeId;
        }

        // Add debug logging for server creation
        logModuleCall("Pelican-WHMCS", "Server Creation Debug", "Server Data", print_r($serverData, true));

        $server = pelican_API($params, 'servers?include=allocations', $serverData, 'POST');

        if($server['status_code'] === 400) {
            $error = isset($server['errors'][0]['detail']) ? $server['errors'][0]['detail'] : 'Unknown error';
            throw new Exception('Failed to create server: ' . $error);
        }
        if($server['status_code'] !== 201) throw new Exception('Failed to create the server, received the error code: ' . $server['status_code'] . '. Enable module debug log for more info.');

        // If we have additional allocations, add them using the build endpoint
        if (!empty($addAllocations)) {
            $buildData = [
                'allocation' => $server['attributes']['allocation'],
                'memory' => (int) $memory,
                'swap' => (int) $swap,
                'io' => (int) $io,
                'cpu' => (int) $cpu,
                'disk' => (int) $disk,
                'feature_limits' => [
                    'databases' => $databases ? (int) $databases : null,
                    'backups' => (int) $backups,
                ],
                'add_allocations' => $addAllocations
            ];
            
            $buildResult = pelican_API($params, 'servers/' . $server['attributes']['id'] . '/build', $buildData, 'PATCH');
            if ($buildResult['status_code'] !== 200) {
                logModuleCall("Pelican-WHMCS", "Failed to add additional allocations", "Build Data", print_r($buildData, true));
                logModuleCall("Pelican-WHMCS", "Build Result", "Result", print_r($buildResult, true));
            }
        }

        unset($params['password']);

        // Get IP & Port and set on WHMCS "Dedicated IP" field
        $_IP = $server['attributes']['relationships']['allocations']['datas'][0]['attributes']['ip'];
        $_Port = $server['attributes']['relationships']['allocations']['data'][0]['attributes']['port'];
        
        // Check if IP & Port field have value. Prevents ":" being added if API error
        if (isset($_IP) && isset($_Port)) {
            try {
                $query = Capsule::table('tblhosting')->where('id', $params['serviceid'])->where('userid', $params['userid'])->update(array('dedicatedip' => $_IP . ":" . $_Port));
            } catch (Exception $e) { 
                return $e->getMessage() . "<br />" . $e->getTraceAsString(); 
            }
        }

        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => '',
            'password' => '',
        ]);
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

// Function to allow backwards compatibility with death-droid's module
function pelican_GetServerID(array $params, $raw = false) {
    $serverResult = pelican_API($params, 'servers/external/' . $params['serviceid'], [], 'GET', true);
    if($serverResult['status_code'] === 200) {
        if($raw) return $serverResult;
        else return $serverResult['attributes']['id'];
    } else if($serverResult['status_code'] === 500) {
        throw new Exception('Failed to get server, panel errored. Check panel logs for more info.');
    }

    if(Capsule::schema()->hasTable('tbl_pelicanproduct')) {
        $oldData = Capsule::table('tbl_pelicanproduct')
            ->select('user_id', 'server_id')
            ->where('service_id', '=', $params['serviceid'])
            ->first();

        if(isset($oldData) && isset($oldData->server_id)) {
            if($raw) {
                $serverResult = pelican_API($params, 'servers/' . $oldData->server_id);
                if($serverResult['status_code'] === 200) return $serverResult;
                else throw new Exception('Failed to get server, received the error code: ' . $serverResult['status_code'] . '. Enable module debug log for more info.');
            } else {
                return $oldData->server_id;
            }
        }
    }
}

function pelican_SuspendAccount(array $params) {
    try {
        $serverId = pelican_GetServerID($params);
        if(!isset($serverId)) throw new Exception('Failed to suspend server because it doesn\'t exist.');

        $suspendResult = pelican_API($params, 'servers/' . $serverId . '/suspend', [], 'POST');
        if($suspendResult['status_code'] !== 204) throw new Exception('Failed to suspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pelican_UnsuspendAccount(array $params) {
    try {
        $serverId = pelican_GetServerID($params);
        if(!isset($serverId)) throw new Exception('Failed to unsuspend server because it doesn\'t exist.');

        $suspendResult = pelican_API($params, 'servers/' . $serverId . '/unsuspend', [], 'POST');
        if($suspendResult['status_code'] !== 204) throw new Exception('Failed to unsuspend the server, received error code: ' . $suspendResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pelican_TerminateAccount(array $params) {
    try {
        $serverId = pelican_GetServerID($params);
        if(!isset($serverId)) throw new Exception('Failed to terminate server because it doesn\'t exist.');

        $deleteResult = pelican_API($params, 'servers/' . $serverId, [], 'DELETE');
        if($deleteResult['status_code'] !== 204) throw new Exception('Failed to terminate the server, received error code: ' . $deleteResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    // Remove the "Dedicated IP" Field on Termination
    try {
        $query = Capsule::table('tblhosting')->where('id', $params['serviceid'])->where('userid', $params['userid'])->update(array('dedicatedip' => ""));
    } catch (Exception $e) { return $e->getMessage() . "<br />" . $e->getTraceAsString(); }

    return 'success';
}

function pelican_ChangePassword(array $params) {
    try {
        if($params['password'] === '') throw new Exception('The password cannot be empty.');

        $serverData = pelican_GetServerID($params, true);
        if(!isset($serverData)) throw new Exception('Failed to change password because linked server doesn\'t exist.');

        $userId = $serverData['attributes']['user'];
        $userResult = pelican_API($params, 'users/' . $userId);
        if($userResult['status_code'] !== 200) throw new Exception('Failed to retrieve user, received error code: ' . $userResult['status_code'] . '.');

        $updateResult = pelican_API($params, 'users/' . $serverData['attributes']['user'], [
            'username' => $userResult['attributes']['username'],
            'email' => $userResult['attributes']['email'],
            'first_name' => $userResult['attributes']['first_name'],
            'last_name' => $userResult['attributes']['last_name'],

            'password' => $params['password'],
        ], 'PATCH');
        if($updateResult['status_code'] !== 200) throw new Exception('Failed to change password, received error code: ' . $updateResult['status_code'] . '.');

        unset($params['password']);
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => '',
            'password' => '',
        ]);
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pelican_ChangePackage(array $params) {
    try {
        $serverData = pelican_GetServerID($params, true);
        if($serverData['status_code'] === 404 || !isset($serverData['attributes']['id'])) throw new Exception('Failed to change package of server because it doesn\'t exist.');
        $serverId = $serverData['attributes']['id'];

        $memory = pelican_GetOption($params, 'memory');
        $swap = pelican_GetOption($params, 'swap');
        $io = pelican_GetOption($params, 'io');
        $cpu = pelican_GetOption($params, 'cpu');
        $disk = pelican_GetOption($params, 'disk');
        $databases = pelican_GetOption($params, 'databases');
        $allocations = pelican_GetOption($params, 'allocations');
        $backups = pelican_GetOption($params, 'backups');
        $oom_killer = pelican_GetOption($params, 'oom_killer') ? true : false;
        $updateData = [
            'allocation' => $serverData['attributes']['allocation'],
            'memory' => (int) $memory,
            'swap' => (int) $swap,
            'io' => (int) $io,
            'cpu' => (int) $cpu,
            'disk' => (int) $disk,
            'oom_killer' => $oom_killer,
            'feature_limits' => [
                'databases' => (int) $databases,
                'allocations' => (int) $allocations,
                'backups' => (int) $backups,
            ],
        ];

        $updateResult = pelican_API($params, 'servers/' . $serverId . '/build', $updateData, 'PATCH');
        if($updateResult['status_code'] !== 200) throw new Exception('Failed to update build of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');

        $eggId = pelican_GetOption($params, 'egg_id');
        $eggData = pelican_API($params, 'eggs/' . $eggId . '?include=variables');
        if($eggData['status_code'] !== 200) throw new Exception('Failed to get egg data, received error code: ' . $eggData['status_code'] . '. Enable module debug log for more info.');

        $environment = [];
        foreach($eggData['attributes']['relationships']['variables']['data'] as $key => $val) {
            $attr = $val['attributes'];
            $var = $attr['env_variable'];
            $friendlyName = pelican_GetOption($params, $attr['name']);
            $envName = pelican_GetOption($params, $attr['env_variable']);

            if(isset($friendlyName)) $environment[$var] = $friendlyName;
            elseif(isset($envName)) $environment[$var] = $envName;
            elseif(isset($serverData['attributes']['container']['environment'][$var])) $environment[$var] = $serverData['attributes']['container']['environment'][$var];
            elseif(isset($attr['default_value'])) $environment[$var] = $attr['default_value'];
        }

        $image = pelican_GetOption($params, 'image', $serverData['attributes']['container']['image']);
        $startup = pelican_GetOption($params, 'startup', $serverData['attributes']['container']['startup_command']);
        $updateData = [
            'environment' => $environment,
            'startup' => $startup,
            'egg' => (int) $eggId,
            'image' => $image,
            'skip_scripts' => false,
        ];

        $updateResult = pelican_API($params, 'servers/' . $serverId . '/startup', $updateData, 'PATCH');
        if($updateResult['status_code'] !== 200) throw new Exception('Failed to update startup of the server, received error code: ' . $updateResult['status_code'] . '. Enable module debug log for more info.');
    } catch(Exception $err) {
        return $err->getMessage();
    }

    return 'success';
}

function pelican_LoginLink(array $params) {
    if($params['moduletype'] !== 'pelican') return;

    try {
        $serverId = pelican_GetServerID($params);
        if(!isset($serverId)) return;

        $hostname = pelican_GetHostname($params);
        echo '<a style="padding-right:3px" href="'.$hostname.'/admin/servers/' . $serverId . '/edit" target="_blank">[Go to Service]</a>';
        echo '<p style="float:right; padding-right:1.3%">[<a href="https://github.com/pelican-dev/whmcs/issues" target="_blank">Report A Bug</a>]</p>';
        # echo '<p style="float: right">[<a href="https://github.com/pelican-dev/whmcs/issues" target="_blank">Report A Bug</a>]</p>';
    } catch(Exception $err) {
        // Ignore
    }
}

function pelican_ClientArea(array $params) {
    if($params['moduletype'] !== 'pelican') return;

    try {
        $hostname = pelican_GetHostname($params);
        $serverId = pelican_GetServerID($params);
        if($serverId['status_code'] === 404 || !isset($serverId)) return [
            'templatefile' => 'clientarea',
            'vars' => [
                'serviceurl' => $hostname,
            ],
        ];

        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'serviceurl' => $hostname . '/server/' . $serverId,
            ],
        ];
    } catch (Exception $err) {
        // Ignore
    }
}
