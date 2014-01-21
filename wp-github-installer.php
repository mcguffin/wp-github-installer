<?php
/*
Plugin Name: WordPress GitHub Installer
Plugin URI: https://github.com/mcguffin/wp-github-installer
Description: Install and update plugins from public github repositories.
Version: 0.0.1
Author: Jörn Lund
Author URI: https://github.com/mcguffin/
Text Domain: github
Network: true
*/


/*
To Do:
- AutoUpdate


*/


class GitHub_Installer {
	private static $instance;
	
	public static function instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self();
		return self::$instance;
	}
	
	private function __construct() {
		load_plugin_textdomain( 'my-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		if ( ! is_multisite() && is_admin() ) {
			add_option( 'github_access_token' , '' , '' , 'yes' );
		} else if ( is_multisite() && is_network_admin() ) {
			add_site_option( 'github_access_token' , '' , '' , 'yes' );
		}
		
		add_action('plugins_loaded', array(&$this,'loaded'));
		
		
		// will add github notes to plugin meta
		add_filter('plugin_row_meta',array($this,'plugin_meta'),10,4);
		
		// will add update notices to github plugins
		add_filter('site_transient_update_plugins',array(&$this,'filter_github_plugins'));


		// will add and render the github plugin install tab
		add_filter( 'install_plugins_tabs', array(&$this,'install_plugins_tabs') );
		add_action('install_plugins_github' , array(&$this,'install_github_plugin') );
		// add scripts to github tab
		add_action( 'load-plugin-install.php' , array( &$this , 'load_plugin_install' ) );


		// store current installed info
		add_action( 'upgrader_process_complete' , array( &$this , 'after_plugin_upgrade' ) , 10 , 2 );

		add_filter('plugins_api',array(&$this,'github_download_api'),10,3);
		
		// rename downloaded source package to proper WP plugin name
		add_filter( 'upgrader_source_selection' , array(&$this,'source_selection') , 10 , 3 );
		
		
		
		add_action( 'wp_ajax_get-github-repo-branches' , array( &$this , 'ajax_get_repo_branches' ) );
	}
	function loaded(){
		load_plugin_textdomain( 'github', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	}
	
	function get_access_token() {
		$token = false;
		if ( is_multisite() )
			$token = get_site_option( 'github_access_token' );
		else 
			$token = get_option( 'github_access_token' );

		if ( $token )
			return $token;
			
		return false;
	}
	
	/*
	GitHub install tab
	*/
	function install_plugins_tabs( $tabs ) {
		$tabs['github'] = __('GitHub','github');
		return $tabs;
	}
	function install_github_plugin( $paged ) {
	?>
		<h4><?php _e('Install a plugin from GitHub'); ?></h4>
		<p class="install-help"><?php _e('Enter the github URL in the form <code>https://github.com/github-username/plugin-name.git</code>.','github'); ?></p>
		<form method="post" class="wp-github-form" action="<?php echo self_admin_url('update.php?action=install-plugin&source=github'); ?>">
			<?php wp_nonce_field( 'install-plugin_'); ?>
			<label for="github-plugin-url"><?php _e('Plugin url'); ?></label>
				<input type="text" class="regular-text code" id="github-plugin-url" name="github-plugin-url" />
			<?php submit_button( __( 'Install Now' ), 'button', 'install-github-plugin', false , array( 'disabled' => 'disabled' , 'id' => 'github-plugin-submit' ) ); ?>
		</form>
	<?php
	}
	
	/*
	GitHub install tab script
	*/
	function load_plugin_install() {
		if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'github' ) {
			wp_enqueue_script( 'github-install-page' , plugins_url('js/install.js',__FILE__) , array('jquery') , 1 , false );
			wp_localize_script( 'github-install-page', 'github_l10n', array(
				'invalid_repo_name' => __('Not a valid Repository name','github'),
				'no_repo_data' => __('No such Repository','github'),
				'select_branch' => __('Select Branch: ','github'),
				'default_suffix' => __('(Default)','github'),
				'default_master' => __('(Master)','github'),
				'default_master_suffix' => __('(Default, Master)','github'),
				'private_suffix' => __('(private)','github'),
				'no_description' => __('(no description)','github'),
			) );
		}
	}
	
	/*
	GitHub install tab ajax callback
	*/
	function ajax_get_repo_branches() {
		// returns false or commit list
		$repo = new Github_Repo( $_POST['data'] );
		header('Content-Type: application/json');
		$response = (object) array(
			'repo_data'=>$repo->get_repo_data(),
			'branches'=>$repo->get_branches(),
		);
		die(json_encode( $response ));
	}
	
	
	function github_download_api( $result , $action , $args ) {
		$result = false;
		if ($action == 'plugin_information' && isset($_REQUEST['source']) ) {
			if ( ! $url = esc_url($_POST['github-plugin-url'],array('http','https')) )
				return new WP_Error('no_url_specified',__('You did not specify a proper URL.'));
			
			$repo = new GitHub_Repo( $url );
			
			if ( $branch = sanitize_title($_POST['github-plugin-branch']) )
				$repo->set_branch( $branch );
			
			// check for 
			if ( $repo->is_valid() )
				$result = (object) array(
					'name' => $repo->get_plugin_slug( ),//__('GitHub plugin','github'),
					'slug' => $repo->get_plugin_slug( ),
					'version' => $repo->get_branch( $branch )->commit->sha,
					'homepage' =>  $repo->get_repository_url( ),
					'download_link' => $repo->get_download_url( ),
				);
		}
		return $result;
	}
	
	
	
	
	
	function is_github_plugin( $plugin_file , $plugin_data = null ){
		if ( is_null( $plugin_data ) )
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, true);
		return false !== strpos($plugin_data['PluginURI'],'//github.com/');
	}
	
	function plugin_meta($plugin_meta, $plugin_file, $plugin_data, $status){
		if ( $this->is_github_plugin( $plugin_file, $plugin_data ) ) {
			$repo = new GitHub_Repo( $plugin_data['PluginURI'] );
			array_unshift($plugin_meta,'<img src="https://github.com/favicon.ico" /> ' . substr($repo->get_install_info()->commit->sha,0,10) );
		}
		
		return $plugin_meta;
	}
	function filter_github_plugins( $current ) {
		if ( isset( $current->checked ) ) {
			foreach ( $current->checked as $plugin_file => $version ) {
				if ( file_exists(WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, true);
					if ( $this->is_github_plugin( $plugin_file,$plugin_data ) ) {
						$repo 	= new GitHub_Repo($plugin_data['PluginURI']);
						$commit = $repo->get_latest_commit();
						$info	= $repo->get_install_info();
						if ( $commit->commit->sha != $info->commit->sha ) {
							$current->checked[$plugin_file] = $info->commit->sha;
							$current->response[$plugin_file] = (object) array(
								'id' => '',
								'slug' => $repo->get_plugin_slug(),
								'new_version' => $commit->commit->sha,
								'url' => $plugin_data['PluginURI'],
								'package' => $repo->get_download_url( ),
							);
						}
					}
				}
			}
		}
		return $current;
	}
	
	function source_selection($source, $remote_source, $wp_upgrader ) {
		if ( is_a($wp_upgrader->skin,'Plugin_Upgrader_Skin' ) )
			$plugin_slug = dirname( $wp_upgrader->skin->plugin );
		else if ( is_a( $wp_upgrader->skin , 'Plugin_Installer_Skin' ) )
			$plugin_slug = $wp_upgrader->skin->api->slug;
//		else: Bulk_Plugin_Upgrader_Skin

		$new_name = trailingslashit( trailingslashit($remote_source) . $plugin_slug );
		$rename_result = rename( $source , $new_name );
		/*
		?><pre><?php var_dump($rename_result,$source, $remote_source, $new_name, $wp_upgrader); exit(); ?></pre><?php
		//*/
		if ( $rename_result )
			return $new_name;
		else 
			return $source;
	}
	function after_plugin_upgrade( $upgrader , $args ) {
		extract($args);
		if ( ! isset( $plugins ) && isset( $plugin ) ) 
			$plugins = (array) $plugin;
		if ( isset( $plugins ) )
			foreach ( $plugins as $plugin ) {
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin, false, true);
				$repo = new GitHub_Repo($plugin_data['PluginURI']);
				if ( $repo->is_valid() )
					$repo->set_install_info();
			}
	}

}

GitHub_Installer::instance();

require_once dirname(__FILE__).'/inc/class-github-api.php';
require_once dirname(__FILE__).'/inc/class-github-repo.php';
require_once dirname(__FILE__).'/inc/class-github-admin.php';

