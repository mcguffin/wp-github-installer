<?php




class GitHub_Repo {
	private $user;
	private $slug;
	
	function __construct( $url ) {
		$matches = array();
		
		if ( preg_match('/https?:\/\/github\.com\/([a-z0-9-]+)\/([a-z0-9-]+)/is' , $url , $matches ) ) {
			$this->user = $matches[1];
			$this->slug = $matches[2];
		} else if (  preg_match('/git@github.com:([a-z0-9-]+)\/([a-z0-9-]+)/is' , $url , $matches )  ) {
			$this->user = $matches[1];
			$this->slug = $matches[2];
		}
		/*
		URL formats:
		
		https://github.com/mcguffin/wp-access-areas.git
		https://github.com/mcguffin/wp-access-areas
		
		
		
		git@github.com:mcguffin/wp-access-areas.git
		
		*/
	}
	function is_valid() {
		return $this->user && $this->slug;
	}

	function get_download_url( ) {
		return sprintf( 'https://github.com/%s/%s/archive/master.zip' , $this->user , $this->slug );
	}
	function get_plugin_slug( ) {
		return $this->slug;
	}
	function get_repository_url( ) {
		return sprintf( 'https://github.com/%s/%s' , $this->user , $this->slug );
	}
	function get_commits_url() {
		return sprintf( 'https://api.github.com/repos/%s/%s/commits' , $this->user , $this->slug );
	}
	function set_installed_sha() {
		$transient_name = "github_repo_local-{$this->user}-{$this->slug}";
		$commit = $this->get_latest_commit();
		set_transient( $transient_name , $commit->sha );
	}
	function get_installed_sha() {
		$transient_name = "github_repo_local-{$this->user}-{$this->slug}";
		if ( $sha = get_transient( $transient_name ) )
			return $sha;
		else 
			return false;
	}
	
	function get_latest_commit(  ) {
		$transient_name = "github_repo_remote-{$this->user}-{$this->slug}";
		if ( $commit = get_transient( $transient_name ) ) {
			$result = $commit;
		} else {
			$response = wp_remote_get( $this->get_commits_url() );
			$expire = 60*60*6;
			if ( ! is_wp_error($response) && $response['response']['code'] == 200 ) {
				$commits = json_decode($response['body']);
				$commit = $commits[0];
				unset($commits);
				$result = $commit;
			} else if ( is_wp_error($response)) {
				$result = $response;
			} else {
				vaR_dump($result['headers']['x-ratelimit-remaining']);
				if ( ! $result['headers']['x-ratelimit-remaining'] ) {
					$expire = max( intval($result['headers']['x-ratelimit-reset']) - time() , $expire );
					vaR_dump(intval($result['headers']['x-ratelimit-reset']) - time() , $result['headers']['x-ratelimit-reset'] , time() , $expire);
				}
				$result = new WP_Error($result['response']['code'],$result['response']['message']);
			}
			set_transient( $transient_name , $result, $expire );
		}
		return $result;
	}
	
	
}
