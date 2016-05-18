<?php

// A key can be any alphanumeric string.
// Insert the appropriate "key" => "hostname" values below.
$hosts = array(
	"***Insert Random Key1 Here***" => "ddns1",
	"***Insert Random Key2 Here***" => "ddns2",
	"***Insert Random Key3 Here***" => "ddns3",
	"***Insert Random Keyn Here***" => "ddnsn"
);

// Check the calling client has a valid auth key.
if (empty($_GET['auth'])) {
	die("Authentication required\n");
} elseif (!array_key_exists($_GET['auth'], $hosts)) {
	die("Invalid auth key\n");
}

// Update these values with your own information.
$apiKey		= "CloudFlareApiKey";				// Your CloudFlare API Key.
$myDomain	= "example.com";				// Your domain name.
$emailAddress	= "CloudFlareAccountEmailAddress";		// The email address of your CloudFlare account.

// These values do not need to be changed.
$hostname	= $hosts[$_GET['auth']];			// The hostname that will be updated.
$ddnsAddress	= $hostname.".".$myDomain;			// The fully qualified domain name.
$ip		= $_SERVER['REMOTE_ADDR'];			// The IP of the client calling the script.
$baseUrl	= 'https://api.cloudflare.com/client/v4/zones';	// The URL for the CloudFlare API.

// Array with the headers needed for every request
$headers = array(
	"X-Auth-Email: ".$emailAddress,
	"X-Auth-Key: ".$apiKey,
	"Content-Type: application/json"
);

// Prints errors and messages and kills the script
fucntion print_err_msg() {
	global $data;
	
	if (!empty($data->errors)) {
		echo "Errors:\n";
		print_r($data->errors);
		echo "\n";
	}
	if (!empty($data->messages)) {
		echo "Messages:\n";
		print_r($data->messages);
		echo "\n";
	}
	die();
}

// Determine protocol version and set record type.
if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)){
	$type = 'AAAA';
} else{
	$type = 'A';
}

// Build the request to fetch the zone ID.
// https://api.cloudflare.com/#zone-list-zones
$url = $baseUrl.'?name='.urlencode($myDomain);

// Send the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$data = json_decode($result);

// Continue if the request succeeded.
if ($data->success == true) {
	// Extract the zone ID (if it exists)
	if (!empty($data->result)) {
		$zoneID = $data->result[0]->id;
	} else {
		die("Zone ".$myDomain." doesn't exist\n");
	}

// Print error message if the request failed.
} else {
	print_err_msg();
}

// Build the request to fetch the record ID.
// https://api.cloudflare.com/#dns-records-for-a-zone-list-dns-records
$url = $baseUrl.'/'.$zoneID.'dns_records';
$url .= '?type='.$type;
$url .= '&name='.urlencode($ddnsAddress);

// Send the request
$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);

$data = json_decode($result);

// Continue if the request succeeded.
if ($data->success == true) {
	// Extract the record ID (if it exists) for the subdomain we want to update.
	$rec_exists = false;					// Assume that the record doesn't exist.
	if (!empty($data->result) {
			$rec_exists = true;			// If this runs, it means that the record exists.
			$id = = $data->result[0]->id;
			$cfIP = $data->result[0]->content;	// The IP Cloudflare has for the subdomain.
	}

// Print error message if the request failed.
} else {
	print_err_msg();
}

// Create a new record if it doesn't exist.
if(!$rec_exists){
	// Build the request to create a new DNS record.
	// https://api.cloudflare.com/#dns-records-for-a-zone-create-dns-record
	$fields = array(
		'a' => urlencode('rec_new'),
		'tkn' => urlencode($apiKey),
		'email' => urlencode($emailAddress),
		'z' => urlencode($myDomain),
		'type' => urlencode($type),
		'name' => urlencode($hostname),
		'content' => urlencode($ip),
		'ttl' => urlencode ('1')
	);

	$data = send_request();
	
	// Print success/error message.
	if ($data->result == "success") {
		echo $ddnsAddress."/".$type." record successfully created\n";
	} else {
		echo $data->msg."\n";
	}
	
// Only update the entry if the IP addresses do not match.
} elseif($ip != $cfIP){
	// Build the request to update the DNS record with our new IP.
	// https://api.cloudflare.com/#dns-records-for-a-zone-update-dns-record
	$fields = array(
		'a' => urlencode('rec_edit'),
		'tkn' => urlencode($apiKey),
		'id' => urlencode($id),
		'email' => urlencode($emailAddress),
		'z' => urlencode($myDomain),
		'type' => urlencode($type),
		'name' => urlencode($hostname),
		'content' => urlencode($ip),
		'service_mode' => urlencode('0'),
		'ttl' => urlencode ('1')
	);

	$data = send_request();
	
	// Print success/error message.
	if ($data->result == "success") {
		echo $ddnsAddress."/".$type." successfully updated to ".$ip."\n";
	} else {
		echo $data->msg."\n";
	}
} else {
	echo $ddnsAddress."/".$type." is already up to date\n";
}
