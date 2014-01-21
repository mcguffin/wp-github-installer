(function($){
	$(document).on('click','#test-token',function(){
		// test
		var $self = $(this);
		$('.token-test-response').remove();
		$.post(
			ajaxurl, 
			{
				'action': 'github-test-token',
				'accept' : 'text/html'
			}, 
			function(response) {
				// splice response into something
				$(response).insertAfter($self.closest('div.updated'));
				console.log(response);
			});
		return false;
	});
	$(document).on('click','#show-enter-token',function(){
		$('#enter-token').removeClass('hidden');
		$('#github_access_token').focus();
		$('#github_access_token').trigger( 'change' );
		return false;
	});
	$(document).on('change','#github_access_token',function(){
		$('.token-test-response').remove();
		if ( $(this).val() == '' || $(this).val().match(/([a-f0-9]+)/) ) {
			$("#github-options [type='submit']").removeAttr( 'disabled' );
		} else {
			$("#github-options [type='submit']").attr('disabled','disabled');
		}
	});
	$(document).ready(function() {
		$("#github-options [type='submit']").attr('disabled','disabled');
	});
	
})(jQuery);