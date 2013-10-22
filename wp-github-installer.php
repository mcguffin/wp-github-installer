<?php
/*
Plugin Name: WordPress GitHub Installer
Plugin URI: https://github.com/mcguffin/wp-github-installer
Description: Install and update plugins from public github repositories.
Version: 0.0.1
Author: Jörn Lund
Author URI: https://github.com/mcguffin/
Text Domain: github
*/


/*
To Do:
- AutoUpdate


*/


class GitHub_Installer {
	
	function __construct() {
		add_filter('plugin_row_meta',array($this,'plugin_meta'),10,4);
		add_action( 'upgrader_process_complete' , array( &$this , 'after_plugin_upgrade' ) , 10 , 3 );
		// do this only on 
		/*
		add_action('load-update.php' , array( &$this,'fake_update' ));
		/*/
		add_filter('site_transient_update_plugins',array(&$this,'filter_github_plugins'));
		//*/
		add_filter( 'install_plugins_tabs', array(&$this,'install_plugins_tabs') );
		add_action('install_plugins_github' , array(&$this,'install_github_plugin') );
		add_filter('plugins_api',array(&$this,'github_download_api'),10,3);
	}
	
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
			<label class="screen-reader-text" for="pluginzip"><?php _e('Plugin url'); ?></label>
			<input type="text" class="regular-text code" id="pluginzipurl" name="github-plugin-url" />
			<?php submit_button( __( 'Install Now' ), 'button', 'install-github-plugin', false ); ?>
		</form>
	<?php
	}
	
	function github_download_api( $result , $action , $args ) {
		if ($action == 'plugin_information' && isset($_REQUEST['source']) ) {
			if ( ! $url = esc_url($_POST['github-plugin-url'],array('http','https')) )
				return new WP_Error('no_url_specified',__('You did not specify a proper URL.'));
			
			// check for 
			$repo = new GitHub_Repo( $_POST['github-plugin-url'] );
			if ( $repo->is_valid() )
				$result = (object) array(
					'name' => __('GitHub plugin','github'),
					'slug' => $repo->get_plugin_slug( ),
					'version' => '',
					'homepage' =>  $repo->get_repository_url( ),
					'download_link' => $repo->get_download_url( ),
				);
		}
		return $result;
	}
	
	
	
	
	
	function fake_update() {
		add_filter('site_transient_update_plugins',array(&$this,'filter_github_plugins'));
	}
	
	function is_github_plugin( $plugin_file , $plugin_data = null ){
		if ( is_null( $plugin_data ) )
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, true);
		return false !== strpos($plugin_data['PluginURI'],'//github.com/');
	}
	
	function plugin_meta($plugin_meta, $plugin_file, $plugin_data, $status){
		if ( $this->is_github_plugin( $plugin_file, $plugin_data ) ) {
			
			$repo = new GitHub_Repo($plugin_data['PluginURI']);
			array_unshift($plugin_meta,'<img src="https://github.com/favicon.ico" /> ' . substr($repo->get_installed_sha(),0,10) );
		}
		
		return $plugin_meta;
	}
	function filter_github_plugins( $current ) {
		
		foreach ( $current->checked as $plugin_file => $version ) {
			if ( file_exists(WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, true);
				if ( $this->is_github_plugin( $plugin_file,$plugin_data ) ) {
					$repo = new GitHub_Repo($plugin_data['PluginURI']);
					$commit = $repo->get_latest_commit();
					if ( ! is_wp_error( $commit ) ) {
						if ( $commit->sha != $repo->get_installed_sha()) {
							$current->checked[$plugin_file] = $repo->get_installed_sha();
							$current->response[$plugin_file] = (object) array(
								'id' => '',
								'slug' => $repo->get_plugin_slug(),
								'new_version' => $commit->sha,
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
	
	function after_plugin_upgrade( $upgrader , $args , $plugin_file ) {
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, true);
		$repo = new GitHub_Repo($plugin_data['PluginURI']);
		$repo->set_installed_sha();
	}

}

require_once dirname(__FILE__).'/inc/class-github-repo.php';

$github_installer = new GitHub_Installer();