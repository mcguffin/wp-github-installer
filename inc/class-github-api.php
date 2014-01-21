<?php




class GitHub_API {
	const API_URL = 'https://api.github.com/';
	const REPO_EXPIRATION = 86400; // on day
	private static $instance;
	
	// runtime
	private $access_token;
	private $ratelimit_limit = 0;
	private $ratelimit_remaining = 0;
	private $ratelimit_reset = 0;
	
	// error handling
	public $errors = array();
	
	public static function instance( $access_token = false ) {
		if ( ! isset( self::$instance ) )
			self::$instance = new GitHub_Api( $access_token );
		return self::$instance;
	}
	
	private function __construct( $access_token = false ) {
		$this->access_token = $access_token;
	}
	
	function get_repo( $user , $repo ) {
		$url = $this->get_api_url('repos/%s/%s' , $user , $repo );
		return $this->get_cached_api_response( $url );
	}
	function get_repo_branches( $user , $repo ) {
		$url = $this->get_api_url('repos/%s/%s/branches' , $user , $repo );
		return $this->get_cached_api_response( $url );
	}	
	function get_repo_zip_url( $user , $repo , $branch = false ) {
		if ( $branch )
			$url = $this->get_api_url('repos/%s/%s/zipball/%s' , $user , $repo , $branch );
		else 
			$url = $this->get_api_url('repos/%s/%s/zipball' , $user , $repo );
		
		return $url;
	}
	
	
	function get_repo_zip_response( $user , $repo , $branch = false , &$response = null ) {
		$url = $this->get_repo_zip_url( $user , $repo , $branch );
		$this->get_api_response( $url , $response );
		var_dump($response);
		return $response;
		$zip_url = false;
		if ( ! $branch && ( $repo_data = $this->get_repo( $user , $repo ) ) ) {
			// get branch from master_branch
			$branch = $repo_data->master_branch;
		}
		if ( $branch ) {
			$url = $this->get_api_url('repos/%s/%s/zipball/%s' , $user , $repo , $branch );
			$data = $this->get_cached_api_response( $url , true );
			var_dump($data);
			if ( $data['Location'] )
				$zip_url = $data['Location'];
		}
		return $zip_url;
	}
	
	function clear_cache( $user , $repo ) {
		foreach ( array(
				$this->get_api_url('repos/%s/%s' , $user , $repo ),
				$this->get_api_url('repos/%s/%s/branches' , $user , $repo ),
			) as $url ) {
			
			$site_transient_key = sprintf("github-%s",md5($url));
			delete_site_transient( $site_transient_key );
		}
	}
	
	
	// root dir: /repos/:owner/:repo/contents/
	// zip: /repos/:owner/:repo/zip/:branch
	
	private function get_api_url( $skeleton ) {
		$args = func_get_args();
		$args[0] = self::API_URL.$skeleton;
		$url = call_user_func_array( 'sprintf' , $args );
		if ( $this->access_token )
			$url .= '?access_token='.$this->access_token;
		return $url;
	}
	private function get_cached_api_response( $url ) {
		$site_transient_key = sprintf("github-%s",md5($url));
		
		if ( !( $response_data = get_site_transient( $site_transient_key ) ) ) {
			if ( $response_data = $this->get_api_response( $url , $headers ) )
				set_site_transient( $site_transient_key , $response_data , self::REPO_EXPIRATION );
		}
		return $response_data;
	}
	private function get_api_response( $url , &$response = null ) {
		// get response
		
		$response_data = false;
		// we are either allowed to make calls ($this->ratelimit_remaining > 0) or we don't know ($this->ratelimit_reset = 0 < time()).
		if ( $this->ratelimit_remaining || $this->ratelimit_reset < time() ) {
		
			$response = wp_remote_get( $url );
			
			if ( ! is_wp_error($response) && $response['response']['code'] == 200 ) {
				// update ratelimit
				if ( isset( $response['headers']['x-ratelimit-limit'] ) ) {
					$this->ratelimit_limit		= $response['headers']['x-ratelimit-limit'];
					$this->ratelimit_remaining	= $response['headers']['x-ratelimit-remaining'];
					$this->ratelimit_reset		= $response['headers']['x-ratelimit-reset'];
				}
				$response_data = json_decode($response['body']);

			} else if ( is_wp_error($response) ) {
				$errors[] = $response;
			} else {
				// $errors[] = new WP_Error( ... );
				// handle other error cases
				// a) ratelimit exceeded
				// b) 404 ....
				// c) invalid access token
				/*
				vaR_dump($result['headers']['x-ratelimit-remaining']);
				if ( ! $result['headers']['x-ratelimit-remaining'] ) {
					$expire = max( intval($result['headers']['x-ratelimit-reset']) - time() , $expire );
					vaR_dump(intval($result['headers']['x-ratelimit-reset']) - time() , $result['headers']['x-ratelimit-reset'] , time() , $expire);
				}
				$result = new WP_Error($result['response']['code'],$result['response']['message']);
				*/
			}
		}
		return $response_data;
	}
	
	
}
