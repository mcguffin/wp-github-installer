(function($){
	$(document).on('change','#github-plugin-url',function(){
		var val = $(this).val(), self = this, 
			$plugin_info, $spinner;
		
		$('#github-plugin-submit').attr('disabled','disabled');
		$('#github-plugin-info').remove();
		
		if ( val.match(/(git@github.com:|https?:\/\/github\.com\/)([a-z0-9-]+)\/([a-z0-9-]+)/) ) {
			
			$plugin_info = $('<div id="github-plugin-info"></div>')
				.appendTo('form.wp-github-form');
			$spinner = $('<span class="spinner" style="display:block;float:none;" />')
				.appendTo( $plugin_info );
			
			$.post(
				ajaxurl, 
				{
					'action': 'get-github-repo-branches',
					'data':   val
				}, 
				function(response) {
					var $label, $select, branch_name, repo_name, repo_desc;

					
					$spinner.remove();
					if ( response && response.branches && response.repo_data ) {
						repo_name = response.repo_data.name;
						if ( response.repo_data.private )
							repo_name += ' '+github_l10n.private_suffix;
						repo_desc = response.repo_data.description || github_l10n.no_description
						$('<h3>'+repo_name+'</h3>').appendTo( $plugin_info );
						$('<p>'+repo_desc+'</p>').appendTo( $plugin_info );
						
						if ( response.branches.length > 1 ) {
							$select = $('<select id="github-plugin-branch" name="github-plugin-branch" />');
							console.log($select);
							for (var i=0;i<response.branches.length;i++) {
								branch_name = response.branches[i].name;
								
								if ( branch_name == response.repo_data.default_branch && branch_name == response.repo_data.master_branch )
									branch_name += ' ' + github_l10n.default_master_suffix;
								else if ( branch_name == response.repo_data.default_branch )
									branch_name += ' ' + github_l10n.default_suffix;
								else if ( branch_name == response.repo_data.master_branch )
									branch_name += ' ' + github_l10n.master_suffix;
									
								$('<option value="'+response.branches[i].name+'">'+branch_name+'</option>')
									.appendTo($select);
							}
						
							$label = $('<hr /><label for="github-plugin-branch">'+github_l10n.select_branch+'</label>')
								.appendTo($plugin_info);
							// append and select default
							$select
								.appendTo($plugin_info)
								.val(response.repo_data.default_branch);
						}
						$('#github-plugin-submit').removeAttr('disabled');
					} else {
						// 404 or such
						$('<div class="error"><p>'+github_l10n.no_repo_data+'</p></div>').appendTo($plugin_info);
					}
				}
			);
		} else {
			// bad name
			$('<div class="error"><p>'+github_l10n.invalid_repo_name+'</p></div>').appendTo($plugin_info);

		}
	});
	
	$(document).ready(function(){$('#github-plugin-url').trigger('change');});
})(jQuery);