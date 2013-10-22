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
		//               https://api.github.com/repos/mcguffin/wp-access-areas/commits
		return sprintf( 'https://api.github.com/repos/%s/%s/commits' , $this->user , $this->slug );
	}
	
	function get_latest_commit() {
		vaR_dump($this->get_commits_url());
		$result = wp_remote_post($this->get_commits_url());
		if ( ! is_wp_error($result) ) {
			var_dump( $result );
		} else {
			return $result;
		}
	}
	
	
}
