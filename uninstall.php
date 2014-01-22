<?php

// uninstall routine


// get github plugin repos
$plugins = get_plugins();
foreach ( $plugins as $plugin ) {
	$repo = new Github_Repo( $plugin["PluginURI"] );
	
	if ( $repo->is_valid() ) { // clear repo info
		$repo->delete_install_info();
		$repo->clear_cache();
	}
}


if ( ! is_multisite() ) {
	delete_option( 'github_access_token' );
} else if ( is_multisite() ) {
	delete_site_option( 'github_access_token'  );
}
