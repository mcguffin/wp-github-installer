<?php




class GitHub_Repo {
	private $user	= false;
	private $slug	= false;
	private $branch = false;
	private $api;
	
	private $repo_data;
	private $branches;
	private $transient_key;
	
	function __construct( $github_url ) {
		$access_token  = GitHub_Installer::instance( )->get_access_token();
		$this->api = GitHub_Api::instance( $access_token );
		
		$matches = array();
		if ( preg_match('/https?:\/\/github\.com\/([a-z0-9-]+)\/([a-z0-9-]+)/is' , $github_url , $matches ) ) {
			$this->user = $matches[1];
			$this->slug = $matches[2];
		} else if (  preg_match('/git@github.com:([a-z0-9-]+)\/([a-z0-9-]+)/is' , $github_url , $matches )  ) {
			$this->user = $matches[1];
			$this->slug = $matches[2];
		}
		if ( $this->is_valid() ) {
			$this->transient_key	= sprintf( "githubl-%s",md5( $this->user.'/'.$this->slug ) );

			// get remote data
			$this->repo_data	= $this->api->get_repo($this->user,$this->slug);
			$this->branches		= $this->api->get_repo_branches($this->user,$this->slug);
			$this->branch		= $this->get_install_info()->name;
		}
	}
	
	function set_branch( $branch = false ) {
		if ( $this->get_branch( $branch ) )
			$this->branch = $branch;
	}
	
	function is_valid() {
		return $this->user && $this->slug;
	}

	function get_download_url( ) {
		return $this->api->get_repo_zip_url( $this->user , $this->slug , $this->branch );
	}
	function get_plugin_slug( ) {
		return $this->slug;
	}
	
	
	function get_repository_url( ) {
		return $this->repo_data->html_url;
	}

	function set_install_info() {
		$commit = $this->get_latest_commit(); // contains sha, 
		$commit->updated_at = strtotime( $this->repo_data->updated_at );
		set_site_transient( $this->transient_key , $commit , 0 );
	}
	function get_install_info() {
		if ( $commit = get_site_transient( $this->transient_key ) )
			return $commit;
		else 
			return (object) array(
				'name' => $this->repo_data->default_branch,
				'updated_at' => 0,
				'commit' => (object) array(
					'sha' => 0,
					'url' => '',
				),
			);
	}
	function delete_install_info() {
		if ( get_site_transient( $this->transient_key ) )
			delete_site_transient( $this->transient_key );
	}
	function clear_cache() {
		$this->api->clear_cache( $this->user , $this->slug );
	}
	
	function get_latest_commit( ) {
		$branch = $this->get_install_info()->name;
		return $this->get_branch( $branch );
	}
	function get_branches( ) {
		if ( $this->is_valid() )
			return $this->branches;
		return false;
	}
	function get_repo_data( ) {
		if ( $this->is_valid() )
			return $this->repo_data;
		return false;
	}
	function get_branch( $branch_slug = false ) {
		if ( ! $branch_slug )
			$branch_slug = $this->repo_data->default_branch;
		if ( ! $branch_slug || ! $this->branches )
			return false;
		foreach ( $this->branches as $branch ) {
			if ( $branch_slug == $branch->name )
				return $branch;
		}
		return false;
	}
}
