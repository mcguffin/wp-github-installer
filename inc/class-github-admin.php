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
		}
	}
	
	// --------------------------------------------------
	// Menu
	// --------------------------------------------------
	function create_network_menu() {
		add_submenu_page( 'settings.php' , __('GitHub','github'), __('GitHub','github'), 'install_plugins', 'github_installer', array(&$this,'settings_page'));
		add_action( 'admin_init', array( &$this , 'register_settings' ) );
	}
	
	function create_menu() { // @ admin_menu
		add_options_page(__('GitHub','github'), __('From the Bot','github'), 'install_plugins', 'github_installer', array(&$this,'settings_page'));
		add_action( 'admin_init', array( &$this , 'register_settings' ) );
	}
	
	
	function register_settings() { // @ admin_init
		register_setting( 'github_access_token', 'github_installer' );
		add_settings_section('github_main', __('GitHub API-Access','github'), '__return_false', 'github_installer');
		add_settings_field('plugin_text_string', __('GitHub access token','github'), array( &$this , 'setting_hide_undisclosed'), 'github_installer', 'github_main');
	}
	
	static function settings_page() {
		?>
		<div class="wrap">
			<h2><?php _e('GitHub Installer','fromthebot') ?></h2>
			
			<form method="post">
				<?php 
					if ( isset( $_POST['github_access_token'] ) && wp_verify_nonce($_POST['_wpnonce'],'github_installer-options') ) {
						if ( is_multisite() && is_network_admin() ) {
							update_site_option( 'github_access_token', $_POST['github_access_token'] );
						} else {
							update_option( 'github_access_token', $_POST['github_access_token'] );
						}
					}
					
					?><pre><?php
					settings_fields( 'github_installer' );
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
	static function setting_hide_undisclosed() {
		if ( is_multisite() && is_network_admin() )
			$from_bot = get_site_option( 'github_access_token' );
		else 
			$from_bot = get_option( 'github_access_token' );
		?>
		<input type="text" name="github_access_token" id="github_access_token" value="<?php echo $from_bot ?>" />
		<?php
	}
	
}

GitHub_Admin::instance();
