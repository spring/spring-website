/**
 * Meta Slider
 */
(function ($) {
	$(function () {
		var file_frame;
		
		jQuery('.metaslider .add-slide').on('click', function(event){
			event.preventDefault();

			// If the media frame already exists, reopen it.
			if ( file_frame ) {
				file_frame.open();
				return;
			}

			// Create the media frame.
			file_frame = wp.media.frames.file_frame = wp.media({
				multiple: 'add',
				frame: 'post',
				library: {type: 'image'}
			});

			// When an image is selected, run a callback.
			file_frame.on('insert', function() {
				var selection = file_frame.state().get('selection');

				selection.map( function( attachment ) {

					attachment = attachment.toJSON();

					var data = {
						action: 'create_image_slide',
						slide_id: attachment.id,
						slider_id: window.parent.metaslider_slider_id
					};

					jQuery.post(ajaxurl, data, function(response) {
						jQuery(".metaslider .left table").append(response);
					});
				});
			});

			file_frame.open();

			// Remove the Media Library tab (media_upload_tabs filter is broken in 3.6)
			jQuery(".media-menu  a:contains('Media Library')").remove();
		});
	});

}(jQuery));