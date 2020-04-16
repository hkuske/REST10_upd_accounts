<?php
$in_file = "Accounts.csv";

$base_url = "http://localhost/demo930ent/rest/v10";
$username = "jim";
$password = "jim";
$migrator = "1"; //PROD

ini_set('max_execution_time', 0);
$script_start = time();
$time_start = time();
$DEBUG = "";

//////////////////////////////////////////////////////////
//Login - POST /oauth2/token
//////////////////////////////////////////////////////////

$login_url = $base_url . "/oauth2/token";
$logout_url = $base_url . "/oauth2/logout";

$oauth2_token_arguments = array(
    "grant_type" => "password",
    //client id/secret you created in Admin > OAuth Keys
    "client_id" => "sugar",
    "client_secret" => "",
    "username" => $username,
    "password" => $password,
    "platform" => "kuske"
);

$oauth2_token_response = call($login_url, '', 'POST', $oauth2_token_arguments);
$DEBUG .= print_r($oauth2_token_response,true) . "</br>\n";
$time_max = $oauth2_token_response->expires_in - 60;

//////////////////////////////////////////////////////////
//READ CSV file and update Account data
//////////////////////////////////////////////////////////

$row = 0;

if (($handle = fopen($in_file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 1000, ';', '"')) !== FALSE) {

		if ((time()-$time_start)>$time_max) {
            call($logout_url, '', 'POST', $oauth2_token_arguments);
			$oauth2_token_response = call($login_url, '', 'POST', $oauth2_token_arguments);
			$DEBUG .= print_r($oauth2_token_response,true) . "</br>\n";
            $time_start = time();
		}
		
		$row++;

//EXIT			
//		if ($row > 3) die();	// STOP for TEST
//EXIT

		$num = count($data);
        $DEBUG .= "$num|$row|";
        for ($c=0; $c < $num; $c++) {
            $DEBUG .= $data[$c] . "|";
        }
		$DEBUG .= "|</br>\n";		


//Header
		if ($row == 1) continue;	

        $erp_id = $data[1]; //key
		$erp_name =  $data[0];
		$erp_zip =  $data[9];
		$erp_city = $data[7];
		$erp_street = $data[6];
		$erp_country = $data[10];
			
        //////////////////////////////////////////////////////////   			
		//Search account record - GET /<module>/
        //////////////////////////////////////////////////////////   			
		$url = $base_url . '/Accounts';

		$search_arguments = array(
		    "filter" => array(
			               array(
						      "erp_id_c" => $erp_id					   
						   )
						),
			"max_num" => 1,
			"offset" => 0,
			"fields" => "id",
		);
		$DEBUG .= print_r($search_arguments,true) . "##</br>";
		$search_response = call($url, $oauth2_token_response->access_token, 'GET', $search_arguments);
//		$DEBUG .= print_r($search_response,true) . "##</br>";
		
		if (count($search_response->records) > 0) { //UPDATE
			foreach($search_response->records as $account) {
				$account_id = $account->id;
				
				$DEBUG .= "UPDATE ACCOUNT " . $account_id. " ##</br>";
				
				//////////////////////////////////////////////////////////   			
				//Update account record - PUT /<module>/:record
				//////////////////////////////////////////////////////////   			
				$url = $base_url . "/Accounts/" . $account_id;
				
				$account_arguments2 = array(
					"billing_address_postalcode" => $erp_zip,
					"billing_address_city" => $erp_city,
					"billing_address_street" => $erp_street,
					"billing_address_country" => $erp_country,
				);
				$DEBUG .= "UPDATE $erp_id ##</br>";	
				$DEBUG .= print_r($account_arguments2,true) . "##</br>";									
				$account_response2 = call($url, $oauth2_token_response->access_token, 'PUT', $account_arguments2);
//				$DEBUG .= print_r($account_response2,true) . "##</br>";									
			
			}
		} else { //CREATE
			$url = $base_url . "/Accounts";
			
			$account_arguments2 = array(
			    "name" => $erp_name,
				"erp_id_c" => $erp_id,
				"billing_address_postalcode" => $erp_zip,
				"billing_address_city" => $erp_city,
				"billing_address_street" => $erp_street,
				"billing_address_country" => $erp_country,
			);
			$DEBUG .= "CREATE $erp_id ##</br>";	
			$DEBUG .= print_r($account_arguments2,true) . "##</br>";									
			$account_response2 = call($url, $oauth2_token_response->access_token, 'POST', $account_arguments2);
//			$DEBUG .= print_r($account_response2,true) . "##</br>";									
		}
        echo $DEBUG; $DEBUG="";						
    }
    fclose($handle);
}

$script_runtime = time()-$script_start;
$DEBUG .= "TIME needed: ".$script_runtime."<br>\n";
echo $DEBUG; $DEBUG="";


//////////////////////////////////////////////////////////
// END OF MAIN
//////////////////////////////////////////////////////////


/**
 * Generic function to make cURL request.
 * @param $url - The URL route to use.
 * @param string $oauthtoken - The oauth token.
 * @param string $type - GET, POST, PUT, DELETE. Defaults to GET.
 * @param array $arguments - Endpoint arguments.
 * @param array $encodeData - Whether or not to JSON encode the data.
 * @param array $returnHeaders - Whether or not to return the headers.
 * @return mixed
 */
function call(
    $url,
    $oauthtoken='',
    $type='GET',
    $arguments=array(),
    $encodeData=true,
    $returnHeaders=false
)
{
    $type = strtoupper($type);

    if ($type == 'GET')
    {
        $url .= "?" . http_build_query($arguments);
    }

    $curl_request = curl_init($url);

    if ($type == 'POST')
    {
        curl_setopt($curl_request, CURLOPT_POST, 1);
    }
    elseif ($type == 'PUT')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "PUT");
    }
    elseif ($type == 'DELETE')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "DELETE");
    }

    curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($curl_request, CURLOPT_HEADER, $returnHeaders);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYHOST, 0);  // wichtig
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);  // wichtig
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);

    if (!empty($oauthtoken)) 
    {
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, array("oauth-token: {$oauthtoken}","Content-Type: application/json"));
    }
    else
    {
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    }

    if (!empty($arguments) && $type !== 'GET')
    {
        if ($encodeData)
        {
            //encode the arguments as JSON
            $arguments = json_encode($arguments);
        }
        curl_setopt($curl_request, CURLOPT_POSTFIELDS, $arguments);
    }

    $result = curl_exec($curl_request);
	
    if ($returnHeaders)
    {
        //set headers from response
        list($headers, $content) = explode("\r\n\r\n", $result ,2);
        foreach (explode("\r\n",$headers) as $header)
        {
            header($header);
        }

        //return the nonheader data
        return trim($content);
    }

    curl_close($curl_request);

    //decode the response from JSON
    $response = json_decode($result);

    return $response;
}
?>