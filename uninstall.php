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
