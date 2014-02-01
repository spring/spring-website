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
				var slide_ids = [];

				selection.map(function(attachment) {
					attachment = attachment.toJSON();
					slide_ids.push(attachment.id);
				});

				var data = {
					action: 'create_image_slide',
					slider_id: window.parent.metaslider_slider_id,
					selection: slide_ids,
					_wpnonce: metaslider_image.addslide_nonce
				};

				jQuery.post(metaslider.ajaxurl, data, function(response) {
					jQuery(".metaslider .left table").append(response);
					jQuery(".metaslider .left table").trigger('resizeSlides');
				});
			});

			file_frame.open();

			// Remove the Media Library tab (media_upload_tabs filter is broken in 3.6)
			jQuery(".media-menu a:contains('Media Library')").remove();

			if (!window.parent.metaslider_pro_active) {
				jQuery(".media-menu a:contains('YouTube')").addClass('disabled');
				jQuery(".media-menu a:contains('Vimeo')").addClass('disabled');
				jQuery(".media-menu a:contains('Post Feed')").addClass('disabled');
				jQuery(".media-menu a:contains('Layer Slide')").addClass('disabled');
			}
		});

	});

}(jQuery));