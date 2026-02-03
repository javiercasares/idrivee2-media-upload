/**
 * IDrivee2 Media Upload - Admin JavaScript.
 *
 * Provides enhanced user experience for the settings page.
 *
 * @package iDrivee2Media
 * @since   0.3.0
 */

(function ($) {
	$( document ).ready(
		function () {
			// Add confirmation to delete buttons.
			$( 'button[name="idrivee2_delete_test"]' ).on(
				'click',
				function (e) {
					var fileName = $( this ).closest( 'form' ).find( 'input[name="test_file"]' ).val();
					if ( ! confirm( 'Are you sure you want to delete ' + fileName + ' from S3?' ) ) {
						e.preventDefault();
						return false;
					}
				}
			);

			// Scroll to notice after page reload (if notice exists).
			if ( $( '.notice' ).length ) {
				$( 'html, body' ).animate(
					{
						scrollTop: $( '.notice' ).first().offset().top - 50
					},
					500
				);
			}
		}
	);
})( jQuery );
