<?php




class GitHub_Admin {
	private static $instance;
	
	public static function instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self();
		return self::$instance;
	}
	
	private function __construct( ) {
		if ( ! is_multisite() && is_admin() ) {
			add_action( 'admin_menu', array( &$this , 'create_menu' ));
		} else if ( is_multisite() && is_network_admin() ) {
			add_action( 'network_admin_menu', array( &$this , 'create_network_menu' ));
			add_action('network_admin_edit_update_github_settings' , array( &$this , 'save_network_settings' ) );
		}
		add_action( 'admin_init', array( &$this , 'register_settings' ) );
		
		add_action('load-settings_page_github_installer' , array(&$this,'load_options_page') );

		add_action( 'wp_ajax_github-test-token' , array( &$this , 'ajax_test_access_token' ) );
	}
	
	function load_options_page() {
		wp_enqueue_script( 'github-options-page' , plugins_url('js/options.js',dirname(__FILE__)) , array('jquery') , 1 , false );
	}
	
	// --------------------------------------------------
	// Menu
	// --------------------------------------------------
	function create_network_menu() {
		add_submenu_page( 'settings.php' , __('GitHub','github'), __('GitHub','github'), 'install_plugins', 'github_installer', array(&$this,'settings_page'));
	}
	
	function create_menu() { // @ admin_menu
		add_options_page(__('GitHub Installer','github'), __('GitHub Installer','github'), 'install_plugins', 'github_installer', array(&$this,'settings_page'));
	}
	
	
	function register_settings() { // @ admin_init
		register_setting( 'github_access_token', 'github_installer' , array( &$this , 'check_access_token' ) );
		add_settings_section('github_main_section', __('GitHub API-Access','github'), '__return_false', 'github_installer');
		add_settings_field('github_access_token', __('GitHub access token','github'), array( &$this , 'input_access_token'), 'github_installer', 'github_main_section');
	}
	
	static function settings_page() {
		$action = is_multisite() && is_network_admin() ? 'edit.php?action=update_github_settings' : 'options.php';
		$network = is_multisite() && is_network_admin();
		
		?>
		<div class="wrap">
			<h2><?php _e('GitHub Installer','fromthebot') ?></h2>
			
			<form id="github-options" method="post" action="<?php echo $action ?>">
				<?php 
					/*
					if ( isset( $_POST['github_access_token'] ) && wp_verify_nonce($_POST['_wpnonce'],'github_installer-options') ) {
						if ( is_multisite() && is_network_admin() ) {
							update_site_option( 'github_access_token', $_POST['github_access_token'] );
						} else {
							update_option( 'github_access_token', $_POST['github_access_token'] );
						}
					}
					*/
					if ( $network )
						wp_nonce_field('set_github_access_token','_update_at_nonce');
					?><pre><?php
					settings_fields( 'github_access_token' );
					?></pre><?php
				?>
				<?php do_settings_sections( 'github_installer' );  ?>
				<?php 
					// create personal access token: https://github.com/settings/applications
					$help_class = array('github-help');
					if ( get_option('github_access_token') )  
						$help_class[] = 'hidden';
				?>
				<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				
			</form>
		</div>
		<?php
	}
	static function input_access_token() {
		$access_token = GitHub_Installer::instance()->get_access_token();
		$placeholder = __('Access Token','github');
		if ( $access_token ) {
			?><div class="updated"><p><?php _e('An access token has already been set. <a id="test-token" href="#">Test it</a> or <a id="show-enter-token" href="#">enter another token</a>.' , 'github' ); ?></p></div><?php
			?><div id="enter-token" class="hidden"><?php
		} else {
			?><div class="error"><p><?php 
				printf(__('To go round GitHub\'s API restrictions allowing only 60 calls per hour and to access your private repositories, you should <a href="%s">get an access token</a>.<br /><a href="%s">Still confused?</a>', 'github') ,
					'https://github.com/settings/applications',
					'https://help.github.com/articles/creating-an-access-token-for-command-line-use' );
			?></p></div><?php
		}
		?>
		<input type="text" name="github_access_token" class="regular-text code" id="github_access_token" placeholder="<?php  ?>" value="" />
		<p class="description"><?php
			_e('Enter GitHub Access Token. ','github');
			if ( $access_token )
				_e('Leave blank to remove existing token.','github');
		?></p><?php

		if ( $access_token ) {
			?></div><?php
		}
	}
	function check_access_token( $input ) {
		if ( preg_match("/^([0-9a-f]+)$/i",$input) ) { //  6b01497117b617e84f236e806e1f541ffc6f7119
			$api = GitHub_Api::instance( );
			$result = $api->test_access_token( $input );
			if ( $result !== true ) {
				add_settings_error( 'github_access_token' , 'github-access-token' , __( 'Invalid token.', 'github' ) , 'error');
				return '';
			}
		} else if ( $input !== '' ) {
			add_settings_error( 'github_access_token' , 'github-access-token' , __( 'Invalid token format.', 'github' ) , 'error');
			return '';
		}
		return $input;
	}
	
	function save_network_settings() {
		check_admin_referer('set_github_access_token','_update_at_nonce');
		if ( !current_user_can('manage_network_options')  ) {
			wp_die(__('Insufficient privileges'));
		}
		$token = $this->check_access_token( $_POST['github_access_token'] );
		if ( $token !== false )
			update_site_option( 'github_access_token' , $token );

		wp_redirect(add_query_arg(array('page' => 'github_installer', 'updated' => 'true'), network_admin_url('settings.php')));
		exit();
	}

	/*
	GitHub install tab ajax callback
	*/
	function ajax_test_access_token() {
		header('Content-Type: text/html');
		$access_token = GitHub_Installer::instance()->get_access_token();
		if ( false === $this->check_access_token( $access_token ) ) {
			?><div class="error token-test-response"><p><?php
				printf(__('Invalid Token. You should <a href="%s">fetch a working one</a>. <a href="%s">Please help!</a>','github'),
					'https://github.com/settings/applications',
					'https://help.github.com/articles/creating-an-access-token-for-command-line-use' );
			?></p></div><?php
		} else {
			?><div class="updated token-test-response"><p><?php
				printf(__('Cool, it works! No reason to stay here any longer. Go ahead <a href="%s">and grab some plugins!</a>','github'),
					'https://github.com/search?q=wordpress+plugin&search_target=global');
			?></p></div><?php
		}
		die('');
	}
}

GitHub_Admin::instance();
