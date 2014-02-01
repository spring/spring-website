jQuery(document).ready(function($) {

	$(document).on('ready ajaxComplete', function() {
		if(rlArgs.script === 'swipebox') {
			$('a[rel*="'+rlArgs.selector+'"]').swipebox({
				useCSS: (rlArgs.animation === '1' ? true : false),
				hideBarsDelay: (rlArgs.hideBars === '1' ? parseInt(rlArgs.hideBarsDelay) : 0),
				videoMaxWidth: parseInt(rlArgs.videoMaxWidth)
			});
		} else if(rlArgs.script === 'prettyphoto') {
			$('a[rel*="'+rlArgs.selector+'"]').prettyPhoto({
				animation_speed: rlArgs.animationSpeed,
				slideshow: (rlArgs.slideshow === '1' ? parseInt(rlArgs.slideshowDelay) : false),
				autoplay_slideshow: (rlArgs.slideshowAutoplay === '1' ? true : false),
				opacity: rlArgs.opacity,
				show_title: (rlArgs.showTitle === '1' ? true : false),
				allow_resize: (rlArgs.allowResize === '1' ? true : false),
				default_width: parseInt(rlArgs.width),
				default_height: parseInt(rlArgs.height),
				counter_separator_label: rlArgs.separator,
				theme: rlArgs.theme,
				horizontal_padding: parseInt(rlArgs.horizontalPadding),
				hideflash: (rlArgs.hideFlash === '1' ? true : false),
				wmode: rlArgs.wmode,
				autoplay: (rlArgs.videoAutoplay === '1' ? true : false),
				modal: (rlArgs.modal === '1' ? true : false),
				deeplinking: (rlArgs.deeplinking === '1' ? true : false),
				overlay_gallery: (rlArgs.overlayGallery === '1' ? true : false),
				keyboard_shortcuts: (rlArgs.keyboardShortcuts === '1' ? true : false),
				social_tools: (rlArgs.social === '1' ? '<div class="pp_social"><div class="twitter"><a href="http://twitter.com/share" class="twitter-share-button" data-count="none">Tweet</a><script type="text/javascript" src="http://platform.twitter.com/widgets.js"></script></div><div class="facebook"><iframe src="http://www.facebook.com/plugins/like.php?locale=en_US&href='+location.href+'&amp;layout=button_count&amp;show_faces=true&amp;width=500&amp;action=like&amp;font&amp;colorscheme=light&amp;height=23" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:500px; height:23px;" allowTransparency="true"></iframe></div></div>' : ''),
				changepicturecallback: function(){},
				callback: function(){},
				ie6_fallback: true
			});
		} else if(rlArgs.script === 'fancybox') {
			$('a[rel*="'+rlArgs.selector+'"]').fancybox({
				modal: (rlArgs.modal === '1' ? true : false),
				overlayShow: (rlArgs.showOverlay === '1' ? true : false),
				showCloseButton: (rlArgs.showCloseButton === '1' ? true : false),
				enableEscapeButton: (rlArgs.enableEscapeButton === '1' ? true : false),
				hideOnOverlayClick: (rlArgs.hideOnOverlayClick === '1' ? true : false),
				hideOnContentClick: (rlArgs.hideOnContentClick === '1' ? true : false),
				cyclic: (rlArgs.cyclic === '1' ? true : false),
				showNavArrows: (rlArgs.showNavArrows === '1' ? true : false),
				autoScale: (rlArgs.autoScale === '1' ? true : false),
				scrolling: rlArgs.scrolling,
				centerOnScroll: (rlArgs.centerOnScroll === '1' ? true : false),
				opacity: (rlArgs.opacity === '1' ? true : false),
				overlayOpacity: parseFloat(rlArgs.overlayOpacity / 100),
				overlayColor: rlArgs.overlayColor,
				titleShow: (rlArgs.titleShow === '1' ? true : false),
				titlePosition: rlArgs.titlePosition,
				transitionIn: rlArgs.transitions,
				transitionOut: rlArgs.transitions,
				easingIn: rlArgs.easings,
				easingOut: rlArgs.easings,
				speedIn: parseInt(rlArgs.speeds),
				speedOut: parseInt(rlArgs.speeds),
				changeSpeed: parseInt(rlArgs.changeSpeed),
				changeFade: parseInt(rlArgs.changeFade),
				padding: parseInt(rlArgs.padding),
				margin: parseInt(rlArgs.margin),
				width: parseInt(rlArgs.videoWidth),
				height: parseInt(rlArgs.videoHeight)
			});
		} else if(rlArgs.script === 'nivo') {
			$.each($('a[rel*="'+rlArgs.selector+'"]'), function() {
				var match = $(this).attr('rel').match(new RegExp(rlArgs.selector+'\\[(gallery\\-(?:[\\da-z]{1,4}))\\]', 'ig'));

				if(match !== null) {
					$(this).attr('data-lightbox-gallery', match[0]);
				}
			});

			$('a[rel*="'+rlArgs.selector+'"]').nivoLightbox({
				effect: rlArgs.effect,
				keyboardNav: (rlArgs.keyboardNav === '1' ? true : false),
				errorMessage: rlArgs.errorMessage
			});
		}
	});

});