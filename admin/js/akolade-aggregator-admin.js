(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

    $(function() {

        /**
		 * Adds repeating fields for network sites
         */
        $('.ak-add-new-element').on('click', function () {
            var currentIndex = parseInt($(this).attr('data-current-index'));

        	// Let's create new repeatable element
            var re = '<tr>';
				re += '<td>';
				re += '<input type="text"  class="form-input" name="akolade-aggregator[network_sites][' + currentIndex +'][title]"/>';
				re += '</td>';
				re += '<td>';
				re += '<input type="text"  class="form-input" name="akolade-aggregator[network_sites][' + currentIndex +'][url]"/>';
				re += '</td>';
				re += '<td>';
				re += '<input type="text"  class="form-input" name="akolade-aggregator[network_sites][' + currentIndex +'][access_token]"/>';
				re += '</td>';
				re += '</tr>';

            // Append the repeating element right before the "Add new" button
            $('.ak-network-sites').append(re);

            // Increase currentIndex by one
            $(this).attr('data-current-index', currentIndex + 1);
        });

        /**
		 * Generate new access token
         */
        $('.ak-generate-token').on('click', function () {
			$('#access_token').val(akGenerateRandomString())
        });

        /**
		 * Generate random 32 char string
		 *
         * @returns {string}
         */
        function akGenerateRandomString() {
        	var length = 32;
        	var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            var result = '';

            for (var i = length; i > 0; --i) result += chars[Math.floor(Math.random() * chars.length)];

            return result;
        }

        /**
		 * Confirm delete action
         */
        $('.delete-action').click( function( event ) {
            if( ! confirm( 'Are you sure you want to delete the item?' ) ) {
                event.preventDefault();
            }
        });

        /**
		 * Set selected value when option is chosen form drop-down in select field of filter form
		 * This js-fix is here because it wasn't working by default.
		 * TODO: find default solution
         */
        $('#channel-selector').change(function(){
            var channel = $(this).find("option:selected").attr('value');
            var url = window.location.href;
            window.location.href = addParam(url, 'channel', channel);
        });

        $('#post-type-selector').change(function(){
        	var postType = $(this).find("option:selected").attr('value');
        	var url = window.location.href;
            window.location.href = addParam(url, 'post_type', postType);
        });

        function addParam(currentUrl,key,val) {
            var url = new URL(currentUrl);
            url.searchParams.set(key, val);
            return url.href;
        }
    });

})( jQuery );
