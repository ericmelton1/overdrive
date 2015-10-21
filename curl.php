<?php
/*
* Author: Ojas Ojasvi
* Released: September 25, 2007
* Description: An example of the disguise_curl() function in order to grab contents from a website while remaining fully camouflaged by using a fake user agent and fake headers.
*/

// disguises the curl using fake headers and a fake user agent.
function disguise_curl($url, $cookie=null, $post=null, $headers=null,$delete=null) {
	$curl = curl_init();

		// Setup headers - I used the same headers from Firefox version 2.0.0.6
		// below was split up because php.net said the line was too long. :/
		$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,application/json,";
		$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
		$header[] = "Cache-Control: max-age=0";
		$header[] = "Connection: keep-alive";
		$header[] = "Keep-Alive: 300";
		$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$header[] = "Accept-Language: en-us,en;q=0.5";
		$header[] = "Pragma: "; // browsers keep this blank.
		
	if (!empty($headers)){
		foreach($headers as $h) {
			$header[]=$h;
		}
	}
	
	if (!empty($cookie)){
			curl_setopt($curl, CURLOPT_COOKIE,$cookie);
	}
	
	if (!empty($post)){
		if (is_array($post)){
			curl_setopt($curl,CURLOPT_POST,count($post));
			curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));
		} else {
			curl_setopt($curl,CURLOPT_POST,1);
			curl_setopt($curl,CURLOPT_POSTFIELDS,$post);
		}
	}
	
	if (!empty($delete)){
		 curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
	}
	
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.11) Gecko/20101012 Firefox/3.6.11 (.NET CLR 3.5.30729)');
	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
	curl_setopt($curl, CURLOPT_AUTOREFERER, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_TIMEOUT, 5);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); 

	$return = curl_exec($curl); // execute the curl command
	$httpResponseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if($httpResponseCode == 404 OR $httpResponseCode == 401) {
		$return=$httpResponseCode;
	}
	curl_close($curl); // close the connection

	return $return; // and finally, return $return	
}
?>
