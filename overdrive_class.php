<?php
/*
Key points:
-Rather than authenticate each time the discovery API is used, this class stores the authentication token for an hour in a text file ($overdrive_auth_token_file) that needs to be created (and made writable) on your server.
-Patron authentication tokens (for placing holds, checking out, etc.) are stored in a cookie on the patron's browser (see $_COOKIE['od_circulation_token']).
-The class uses curl to make the API requests (see require_once('curl.php'); below). You'll need the curl function included in the same GitHub repository. 
*/
class overdrive_api {

	public $id;
	public $api;
	public $username=NULL;
	public $password=NULL;
	public $post_data=NULL;
	public $od_circulation_authenticated = false;
	public $od_discovery_authenticated = false;	
	public $headers=array();
	private $overdrive_client_key='';
	private $overdrive_client_secret='';
	public $overdrive_collection_id=''; //default collection id is consortium
	private $overdrive_consortium_id='';
	private $overdrive_advantage_id='';
	private $overdrive_ils_name='';
	private $overdrive_website_id='';
	private $overdrive_library_id=''; 
	private $overdrive_auth_token_file='c:\inetpub\wwwroot\pac\cache\overdrive_auth_token.txt'; //path to the overdrive authentication token (needs to be writable)
	private $discovery_api_domain='api.overdrive.com';
	private $circulation_api_domain='patron.api.overdrive.com';
	protected static $_instance = NULL;
	
	//general authentication
	public function __construct ($api=null,$username=null,$password=null) {
			$this->api=$api;
			$this->username=$username;
			$this->password=$password;
			$this->_authenticate();
	}
	
	private function _authenticate(){
		require_once('curl.php');
		if ($this->api!='circulation'){
			if (filemtime($this->overdrive_auth_token_file)<=strtotime("-59 minutes")){
				//connection info
				$headers=array();
				$headers[] = "Host: oauth.overdrive.com";
				$headers[] = "Authorization: Basic ".base64_encode($this->overdrive_client_key.':'.$this->overdrive_client_secret);
				$headers[] = "Content-Type: application/x-www-form-urlencoded;charset=UTF-8";		
				$post_data=array('grant_type'=>'client_credentials');

				$auth=json_decode(disguise_curl('https://oauth.overdrive.com/token',null,$post_data,$headers),true);
				if (!empty($auth['access_token'])){ //the auth token for discovery functions is the same regardless of the patron requesting; store the auth token locally to avoid making a new call for each requestor
						$fh = fopen($this->overdrive_auth_token_file, 'w');
						fwrite($fh, $auth['access_token']);
						fclose($fh);
				}
			} else {
				$auth['access_token']=file_get_contents($this->overdrive_auth_token_file);
			}
			if (!empty($auth['access_token'])){
				$this->od_discovery_authenticated=true;
			}
		}
		if ($this->api=='circulation'){
			if (empty($_COOKIE['od_circulation_token']) OR ((!empty($_SESSION['overdrive_username']) && $_SESSION['overdrive_username']!=$this->username) OR (!empty($_SESSION['overdrive_password']) && $_SESSION['overdrive_password']!=$this->password))){			
				//connection info
				$headers=array();
				$headers[] = "Host: oauth-patron.overdrive.com";
				$headers[] = "Authorization: Basic ".base64_encode($this->overdrive_client_key.':'.$this->overdrive_client_secret);
				$headers[] = "Content-Type: application/x-www-form-urlencoded;charset=UTF-8";		
				$post_data=array();
				$post_data['grant_type']='password';
				$post_data['username']=$this->username;
				$post_data['password']=$this->password;
				$post_data['password_required']='true';
				$post_data['scope']='websiteid:'.$this->overdrive_website_id.' ilsname:'.$this->overdrive_ils_name;					

				$auth=json_decode(disguise_curl('https://oauth-patron.overdrive.com/patrontoken',null,$post_data,$headers),true);
				if (!empty($auth['access_token'])){
					setcookie("od_circulation_token", $auth['access_token'], time() +3540);
					$_SESSION['overdrive_username']=$this->username;
					$_SESSION['overdrive_password']=$this->password;		
				} else {
					setcookie("od_circulation_token",0, time() +3540);
				}
			} else {
				$auth['access_token']=$_COOKIE['od_circulation_token'];
			}		
			if (!empty($auth['access_token'])){
				$this->od_circulation_authenticated=true;
			}
		}
		
		if ($this->api=='circulation' && $this->od_circulation_authenticated){
			//common headers for circulation calls (can be overwritten within individual functions)
			$headers=array();
			$headers[] = "Authorization: Bearer ". $auth['access_token'];
			$headers['host'] = "Host: patron.api.overdrive.com";
		} else if ($this->api=='discovery' && $this->od_discovery_authenticated){
			//common headers for discovery calls (can be overwritten within individual functions)
			$headers=array();
			$headers[] = "Host: api.overdrive.com";
			$headers[] = "Authorization: Bearer ". $auth['access_token'];
			$headers[] = "User-Agent: CLL Browser";		
			$headers[] = "X-Forwarded-For: 69.66.252.252";
		}
		
		if (!empty($headers)){
			$this->headers=$headers;
		}
		
	}
	/*				Discovery Functions			*/
	function library_account(){
		$headers=$this->headers;
		$i=1;
		while ($i<3){
			$data=json_decode(disguise_curl('https://'.$this->discovery_api_domain.'/v1/libraries/'.$this->overdrive_library_id,null,null,$headers),true);
			if($this->_error_check($data)){
				break;
			}
			$i++;
		}		
		return $data;
	}
	
	function library_products($url){
		$headers=$this->headers;
		$i=1;
		while ($i<3){
			$data=json_decode(disguise_curl($url,null,null,$headers),true);
			if($this->_error_check($data)){
				break;
			}
			$i++;
		}		
		return $data;
	}
	
	function advantage_accounts(){
		$headers=$this->headers;
		$i=1;
		while ($i<3){
			$data=json_decode(disguise_curl('https://'.$this->discovery_api_domain.'/v1/libraries/'.$this->overdrive_library_id.'/advantageAccounts',null,null,$headers),true);
			if($this->_error_check($data)){
				break;
			}
			$i++;
		}		
		return $data;
	}
	function search($q){
		$headers=$this->headers;
		$i=1;
		while ($i<3){
			$data=json_decode(disguise_curl('https://'.$this->discovery_api_domain.'/v1/collections/'.$this->overdrive_collection_id.'/products?q='.$q,null,null,$headers),true);
			if($this->_error_check($data)){
				break;
			} 
			$i++;
		}		
		return $data;
	}
	
	function library_availability($id){
		$headers=$this->headers;
		$i=1;
		$adv_data['numberOfHolds']=0;
		while ($i<3){
			$data=json_decode(disguise_curl('https://'.$this->discovery_api_domain.'/v1/collections/'.$this->overdrive_collection_id.'/products/'.$id.'/availability',null,null,$headers),true);
			/*	
			if ($this->overdrive_collection_id==$this->overdrive_consortium_id){
				$adv_data=json_decode(disguise_curl('https://'.$this->discovery_api_domain.'/v1/collections/'.$this->overdrive_advantage_id.'/products/'.$id.'/availability',null,null,$headers),true);
			}
			*/
			if($this->_error_check($data)){
				break;
			} 
			$i++;
		}		
		$data['numberOfHolds']=$adv_data['numberOfHolds']+$data['numberOfHolds'];
		return $data;
	}

	function metadata_collection($offset=null){
		$headers=$this->headers;
		if (!empty($offset)){
			$offset='&offset='.$offset;
		} else {
			$offset='';
		}
		$i=1;
		while ($i<3){
		$data=json_decode(disguise_curl('https://'.$this->discovery_api_domain.'/v1/collections/'.$this->overdrive_collection_id.'/products/?limit=5&sort=dateadded:desc'.$offset,null,null,$headers),true);
			if($this->_error_check($data)){
				break;
			}
			$i++;
		}		
		return $data;
	}
	
	function metadata_item($id){
		$headers=$this->headers;
		$i=1;
		while ($i<3){
			$data=json_decode(disguise_curl('http://'.$this->discovery_api_domain.'/v1/collections/'.$this->overdrive_collection_id.'/products/'.$id.'/metadata',null,null,$headers),true);
			if($this->_error_check($data)){
				break;
			}
			$i++;
		}		
		return $data;
	}
	
	/*				Circulation Functions			*/
	function patron_information(){
		$headers=$this->headers;
		$data=json_decode(disguise_curl('http://'.$this->circulation_api_domain.'/v1/patrons/me',null,null,$headers),true);
		return $data;
	}
	
	//holds
	function patron_holds(){
		$headers=$this->headers;
		$data=json_decode(disguise_curl('http://'.$this->circulation_api_domain.'/v1/patrons/me/holds',null,null,$headers),true);	
		return $data;
	}
	
	function patron_place_hold($id,$email){
		$headers=$this->headers;
		$headers[]='Content-Type: application/json; charset=utf-8';
		$headers[]='Expect: 100-continue';
		$post_data['fields'][0]['name']='ReserveId';
		$post_data['fields'][0]['value']=$id;
		$post_data['fields'][1]['name']='EmailAddress';
		$post_data['fields'][1]['value']=$email;
		$post_data=json_encode($post_data);

		$data=json_decode(disguise_curl('http://'.$this->circulation_api_domain.'/v1/patrons/me/holds/',null,$post_data,$headers),true);
		return $data;
	}
	
	function patron_delete_hold($id){
		$headers=$this->headers;
		$data=json_decode(disguise_curl('http://'.$this->circulation_api_domain.'/v1/patrons/me/holds/'.$id,null,null,$headers,true),true);
			
		return $data;
	}
	
	//checkouts
	function patron_checkouts(){
		$headers=$this->headers;
		$data=json_decode(disguise_curl('http://'.$this->circulation_api_domain.'/v1/patrons/me/checkouts',null,null,$headers),true);
		return $data;
	}
	
	function patron_checkout($id,$format=''){
		$headers=$this->headers;
		$headers[]='Content-Type: application/json; charset=utf-8';
		$headers[]='Expect: 100-continue';	
		$post_data['fields'][0]['name']='reserveId';
		$post_data['fields'][0]['value']=$id;
		
		if (empty($format)){
			$headers['host'] = "Host: integration-patron.api.overdrive.com";	
		} else {
			$post_data['fields'][0]['name']='ReserveId';
			$post_data['fields'][1]['name']='FormatType';	
			$post_data['fields'][1]['value']=$format;	
			$format='/'.$id.'/formats';
		}
		
		$post_data=json_encode($post_data);
			
		$data=json_decode(disguise_curl('http://'.$this->circulation_api_domain.'/v1/patrons/me/checkouts'.$format,null,$post_data,$headers),true);
		return $data;
	}
	
	function patron_contentlink($url){
		$headers=$this->headers;
		$data=json_decode(disguise_curl($url,null,null,$headers),true);	
		return $data;
	}
	
	private function _error_check($data){ //needs work - currently only used for discovery functions
		if($data=='401' OR empty($data)){
			//re-authenticate
			$this->_authenticate();
			return false;
		} else {
			return true;
		}
	}
		
}
?>
