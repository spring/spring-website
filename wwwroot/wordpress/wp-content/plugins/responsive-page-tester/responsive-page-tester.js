if( typeof linchpin === 'undefined' ) { // create new linchpin object if one doesn't exist
	linchpin = {};
}

linchpin.tester = function($) {

	// Private Variables
    var default_url     = responsive_page_tester.current_page,
		scrollBarWidth  = 16,
		padding         = 20,
		sizes			= responsive_page_tester.sizes,

	// State of the plugin

		animating		= false,
		scrollBars		= false,
		deviceSize		= 0,
		viewport_h		= 0,
		header_h		= 0,
		ctrls_h			= 0,
		wp_toolbar_h	= 28,

	// Reused Elements

		$container	= null,
		$inner		= null,
		$sizes		= null,
		$iframes	= null;

	return {

		/**
		 * showLoader function.
		 *
		 * @access public
		 * @param id (mixed)
		 * @return void
		 */
		showLoader : function(id) {
			$('#' + id + ' img').fadeIn('slow');
		},

		/**
		 * hideLoader function.
		 *
		 * @access public
		 * @param id (mixed)
		 * @return void
		 */
		hideLoader : function(id) {
			$('#' + id + ' img').fadeOut('slow');
		},

		/**
		 * allFramesLoaded function.
		 *
		 * @access public
		 * @return (bool)
		 */
		allFramesLoaded : function() {

			var results = [],
				result  = false;

			$iframes.each(function(){
				if( !$(this).data('loaded') ){
					results.push(false);
				}
			});

			return result = (results.length > 0) ? false : true;
		},

		/**
		 * loadPage function.
		 *
		 * @access public
		 * @param $frame (jQuery Object)
		 * @param url (String)
		 * @return void
		 */
		loadPage : function($frame, url) {
			var $frames = $container.find('iframe').not($frame);

			url = linchpin.tester.sanitizeURL( url );

			$frames.not($frame).each(function(){
				linchpin.tester.showLoader( $(this).parent().attr('id') );
			}).data('loaded', false).attr('src', url);
		},

		/**
		 * resizeContainer function.
		 *
		 * @access public
		 * @return void
		 */
		resizeContainer : function() {
			//set slidable div width
			$inner.css('width', function(){
				var width = 0;
				$iframes.each(function(){
					var $this = $(this);
					if( $this.is(':visible') ) {
						width += $this.outerWidth() + 2 + padding;
					}
				});
				return width;
			});
		},

		/**
		 * sanitizeURL function.
		 * Pass in a url and this will do basic sanitizing to make sure our responsive get param
		 * is passed along to our iframes.
		 *
		 * @access public
		 * @param url (String)
		 * @return url (String)
		 */
		sanitizeURL : function( url ) {

			if ( url.substr(0,7) !== 'http://' && url.substr(0,8) !== 'https://' && url.substr(0, 7) !== 'file://' ) {
				url = 'http://' + url;
			}

			if( url.indexOf('rpt_responsive=true') === -1 ) {
				url += ( ( url.indexOf('?') !== -1 )? '&' : '?') + 'rpt_responsive=true';
			}

			return url;
		},

		/**
		 * buildSizeItem function.
		 * Build a toggle menu item for our display sizes
		 *
		 * @access public
		 * @param id (mixed)
		 * @param label (String)
		 * @param checked (Bool)
		 * @return html (String)
		 */
		buildSizeItem : function( id, label, checked ) {
			var html  = '<input id="size_' + id + '" class="size-select" type="checkbox" data-org_label="' + label + '" data-frame="f' + id + '" name="size_' + id + '" value="true" ' + ((checked === true)? 'checked' : '' ) + '>';
				html += '<label for="size_' + id + '">' + label + '</label>';

			return html;
		},

		/**
		 * buildDropdown function.
		 *
		 * @param $target (jQuery Object) the jQuery object we are attaching our dropdown to.
		 * @access public
		 * @return html $dropdown (jQuery Object)
		 */
		buildDropdown : function( $target ) {
			var html         = '<select id="resize_frame">',
				$dropdown    = null,
				org_width	 = parseInt( $target.attr('data-org_width') ),
				org_height   = parseInt( $target.attr('data-org_height') );

			for(var size in sizes) {
				html += '<option data-size="' + sizes[size].description + '" value="' + sizes[size].width + 'x' + sizes[size].height + '"'+ ( (sizes[size].width === org_width && sizes[size].height === org_height)? ' selected="selected"' : '') + '>' + sizes[size].width + 'x' + sizes[size].height + '</option>';
			}

			html += '</select>';

			$dropdown = $(html);

			$dropdown.on('change', function() {

				console.log($dropdown.parent() );

				$dropdown.parent().find('.content').html( linchpin.tester.buildLabel( $dropdown.val(), $dropdown.find('option:selected').attr('data-size') ) );

				animating = true;

				var size_ary = $dropdown.val().split('x'),
					bar_w	 = parseInt(size_ary[0]) + scrollBarWidth;

				$target.animate({width:bar_w + 2}, 500);

				if( 1 === deviceSize ) {
					$target.find('iframe').animate({width:bar_w}, 500, function() {
						animating = false;
						$(this).attr('width', bar_w);
					});

				} else {
					$inner.css('height', 'auto');
					$target.height( parseInt(size_ary[1]) + 2 + padding );

					$target.find('iframe').animate({width:bar_w, height:parseInt( size_ary[1] ) }, 500, function() {
						$(this).attr('width', bar_w).attr('height', size_ary[1]);
						animating = false;
					});
				}
			});

			return $dropdown;
		},

		/**
		 * buildFrame function.
		 *
		 * @access public
		 * @return void
		 */
		buildFrame : function( frame_obj ) {
			var html  = '<div id="f' + frame_obj.id + '" data-frameid="' + frame_obj.id + '" data-org_width="' + frame_obj.width + '" data-org_height="' + frame_obj.height + '" class="frame" style="width:' + (frame_obj.width + scrollBarWidth + 2) + 'px" data-desc="' + frame_obj.description + '">';
				html += '<h2 id="f' + frame_obj.id + '_header" class="frame-header"><span class="content">';
				html += linchpin.tester.buildLabel(frame_obj.width + 'x' + frame_obj.height, frame_obj.description);
				html += '</span><img src="' + responsive_page_tester.includes + '/images/wpspin.gif" /></h2>';
				html += '<iframe sandbox="allow-same-origin allow-forms allow-scripts" seamless width="' + (frame_obj.width + scrollBarWidth) + '" height="' + frame_obj.height + '"></iframe></div>';

			return html;
		},

		/**
		 * buildLabel Function.
		 * creates our label for usage within our frames containers
		 * @access public
		 * @param id (mixed)
		 * @param label (String)
		 * @param description (String)
		 * @return (String)
		 */
		buildLabel : function( label, description ) {
			var html = '<span>' + label + '</span> <span class="small">' + description + '</span>';

			return html;
		},

		/**
		 * toggleDeviceSizes function
		 *
		 * @param e [jQuery Event Object]
		 * @access public
		 * @return void
		 */
		toggleDeviceSizes : function(e) {
			deviceSize = parseInt( $container.find('input[name=rpt-devicesizes]:checked').val() );

			if( 1 === deviceSize ) {

				animating = true;

				$iframes.each(function() {

					var $this    = $(this);

					if( $this.is(':visible') ) {
						$this.stop().animate({'height': viewport_h}, 300, function() {
							$this.attr('height', viewport_h);
							animating = false;
						} );
					} else  {
						$this.height( viewport_h );
						$this.attr('height', viewport_h);
						animating = false;
					}
				});
			} else { // Devices Sizes

				animating = true;

				$('#responsive-page-tester-frames-inner').css('height', 'auto');

				$iframes.each(function() {

					var $this  = $(this),
						height = parseInt( $this.data('org_height') );
						dimensions = 0;

					if( $this.is(':visible') ) {

						if( $('#size_all').is(':checked') || $this.hasClass('oneSize') ) {
							dimensions = $('#resize_frame').find('option:selected').val(),
							dimensions_ary = dimensions.split('x');

							height = parseInt( dimensions_ary[1] );
						}

						$this.stop().animate({'height': height }, 500, function(){
							animating = false;
						});
					} else {
						$this.height( height );
						animating = false;
					}

				});
			}
		},

		/**
		 * toggleScroll function
		 *
		 * @access public
		 * @return void
		 */
		toggleScroll : function() {

			scrollBars     = $(this).is(':checked');
			scrollBarWidth = ( scrollBars ) ? 16 : 0;

			var sb_w = ( scrollBars ) ? 16 : -16,
				$this = $(this),
				icon = ( scrollBars) ? 'arrowthick-2-e-w' : 'arrowthickstop-1-e';

			$this.button( { icons: { primary: 'ui-icon-' + icon} } );

			$iframes.each(function(i,el) {
				var $el = $(el),
					$parent = $el.parent(),
					width = parseInt( $el.attr('width') ) + sb_w;

				$el.attr('width', width).width(width);

				$parent.width( $parent.width() + sb_w);
			});
		},

		/**
		 * clickOneSizeFitsAll function
		 * controls what happens when size_all is selected
		 * @return void
		 */
		clickOneSizeFitsAll : function( $this ) {

			var $size_selects	= $container.find('.size-select'),
				$frame_0 		= $('#f0'),
				$frame 			= $('#' + $this.attr('data-frame') );

			if( $this.is(':checked') ) {

				$size_selects.not( $this ).button('disable').button( { icons: {primary:'ui-icon-notice'} } );
				$container.find('.frame').not( $frame_0 ).hide();

				animating = true;

				$frame_0.stop().show().animate({'left': $(window).width() * 0.5 - $frame_0.outerWidth() * 0.5 }, 500,
				function() {

					$inner.css('width', 'auto');
					$(this).css({'margin':'0 auto', 'position':'relative', 'left':0, 'float':'none'});

				var $dropdown = linchpin.tester.buildDropdown( $frame_0 );

					$(this).prepend( $dropdown );

					animating = false;

				} ).css('position', 'absolute');

			} else {

				var tgt_height = ( deviceSize === 1 )? viewport_h : 320,
					first_size = null;

				for (var i in sizes) {
				    if (sizes.hasOwnProperty(i) && typeof(i) !== 'function') {
				        first_size = sizes[i];
				        break;
				    }
				}

				$frame_0.css({width:242 + scrollBarWidth + 'px',height:'auto', float:'left', margin:'0 20px 0 0'});
				$frame_0.find('iframe').css({width:240 + scrollBarWidth + 'px', height: tgt_height + 'px'});
				$frame_0.find('.content').html( linchpin.tester.buildLabel( first_size.width + 'x' + first_size.height, first_size.description ) );

				// only show the frames for ones that are enabled

				$size_selects.not( $this ).button('enable').button( { icons: {primary:'ui-icon-circle-plus'} } ).each(function() {
					var $this = $(this);

					if( $this.is(':checked') ) {

						$this.button( { icons: {primary:'ui-icon-circle-minus'} } );

						$('#' + $this.attr('data-frame') ).show().css({'position':'relative', 'left':'auto'});
					} else {
						$('#' + $this.attr('data-frame') ).hide();
					}
				});

				$('#resize_frame').remove();

			}
			linchpin.tester.resizeContainer();

			$('#size-toggle').buttonset('refresh');
		},

		appendDropdown : function() {

			animating = true;

			$container.find('.frame:visible').css('height', 'auto').each(function() {
				$(this).animate({'left': $(window).width() * 0.5 - $(this).outerWidth() * 0.5 }, 500, function() {

					var $dropdown = linchpin.tester.buildDropdown( $(this) );

					$(this).prepend($dropdown).addClass('oneSize');

					$inner.css({'width':'auto'});

					$(this).css({'margin':'0 auto', 'position':'relative', 'left':0, 'float':'none'});

					animating = false;
				} ).css('position', 'absolute');
			});

		},

		/**
		 * sizeSelect function
		 * controls what happens when a size is selected
		 * TODO this could probably be broken down into a few methods just so it
		 *      isn't so diesel size all click vs individual sizes
		 *
		 * @oaram w (Number) width
		 * @param h (Number) height
		 * @access public
		 * @return void
		 */
		sizeSelect : function(w, h) {

			if(animating) {
				return;
			}

			var $this			= $(this),
				$size_selects	= $container.find('.size-select'),
				$frame_0 		= $('#f0'),
				$frame 			= $('#' + $this.attr('data-frame') ),
				$orgSize		= null,
				icon			= 'minus'; // our default icon

			// CONTROL WHEN ONE SIZE FITS ALL IS TOGGLED

			if( $this.attr('id') === 'size_all' ) {
				linchpin.tester.clickOneSizeFitsAll( $this );
				return;
			}

			// CONTROL EVERYTHING ELSE

			if( $this.is(':checked') ) {
				$frame.show();
			} else {
				icon = 'plus';
				$frame.hide();
			}

			$this.button({ icons: { primary: 'ui-icon-circle-' + icon} });

			$sizes = $('.size-select:checked');

			if( $sizes.length === 1 ) {

				$sizes.button( { icons: {primary:'ui-icon-notice'} } );
				$sizes.button('disable').button('option', {'label':'One Size Fits All'}).addClass('orgSize');

				$( "#size_all" ).button( "destroy" ).next('label').appendTo( $('#rpt-hidden') );
				$( "#size_all" ).appendTo( $('#rpt-hidden') );

				$('#size-toggle').buttonset('refresh');

				linchpin.tester.appendDropdown();
			} else {

				// Only add in our toggle if it isn't there yet.

				if( $('#size-toggle').has( $( '#size_all' ) ).length == 0 ) {
					$('#size_all' ).prev('label').prependTo( $('#size-toggle') );
					$('#size_all' ).prependTo( $('#size-toggle') ).button({ icons: { primary: 'ui-icon-star' } });
					$('#size-toggle').prepend( $('.rpt-toggle-sizes-lbl') ).buttonset('refresh');
				}

				$sizes.not('#size_all').button({ icons: { primary: 'ui-icon-circle-minus' } });

				var frameHeight = 0;

				$container.find('.frame:visible').css({'position':'relative', 'left':'auto', 'height':'auto'});

				$sizes.removeAttr('disabled').parent().removeClass('disabled');

				// Reset our onesize fits all if we have one

				$('#resize_frame').remove();
				$('.orgSize').button('option', {label: $('.orgSize').attr('data-org_label') } ).removeClass('orgSize');

				var	$oneSize = $('.oneSize'),
				    oneSize_d  = $oneSize.attr('data-desc'),
					oneSize_w  = parseInt($oneSize.attr('data-org_width')),
					oneSize_h  = parseInt($oneSize.attr('data-org_height'));

					$oneSize.width( oneSize_w + scrollBarWidth + 2 )
							.css({'float':'left', 'margin':'0 20px 0 0'});

					$oneSize.find('.content').html( linchpin.tester.buildLabel( oneSize_w + 'x' + oneSize_h, oneSize_d ) );

					$oneSize.find('iframe').height( ( deviceSize === 1 )? viewport_h : oneSize_h ).width( oneSize_w + scrollBarWidth ).attr('width', oneSize_w + scrollBarWidth);

					$oneSize.removeClass('oneSize');

			}

			$('#size-toggle').buttonset('refresh');

			linchpin.tester.resizeContainer();
		},

		/**
		 * toggleActive
		 *
		 */

		toggleActive : function( e ) {
			$(this).toggleClass('rpt-active');
		},

		/**
		 * buildFrames function.
		 *
		 * @param dimensions (String) Width and Height dimensions of a one size fits all display
		 * @access public
		 * @return void
		 */
		buildFrames : function( dimensions ) {

			$('#responsive-page-tester-frames').remove();

			var frameHTML = '',
				contHTML  = '<div id="responsive-page-tester-frames"><div id="responsive-controls"><a href="http://linchpinagency.com/?utm_source=responsive-page-tester&utm_medium=tester-logo&utm_campaign=product" class="rpt-logo" target="_blank" rel="external"><img src="' + responsive_page_tester.logo_url + '" width="500" height="500" /></a><form method="post"><div id="rpt-devicesizes"><input id="rpt-accurate" type="radio" name="rpt-devicesizes" value="2" checked><label for="rpt-accurate">Device sizes</label><input id="rpt-normal" type="radio" name="rpt-devicesizes" value="1"><label for="rpt-normal">Width only</label></div><div id="rpt-more"><input id="rpt-scrollbar" type="checkbox" name="rpt-scrollbar" value="1" checked><label for="rpt-scrollbar">Visible Scrollbars</label></div><div id="size-toggle" class="right"><label class="rpt-toggle-sizes-lbl">Toggle Sizes: </label></div></form></div><div id="responsive-page-tester-frames-inner"></div><div id="rpt-hidden"></div></div>',
				sizeHTML  = linchpin.tester.buildSizeItem('all', 'One Size Fits All', false),
				size_count = 0;

			$container = $(contHTML);

			$('body').append($container);

			if( dimensions && dimensions != 'all' ) {

				frameHTML = linchpin.tester.buildFrame( sizes[dimensions] );

				$('#size-toggle').remove();

			} else {

				for(var size in sizes) {
					sizes[size].id = size_count++;
					frameHTML += linchpin.tester.buildFrame( sizes[size] );
					sizeHTML  += linchpin.tester.buildSizeItem( sizes[size].id, sizes[size].width + 'x' + sizes[size].height, true );
				}
			}

			$inner = $('#responsive-page-tester-frames-inner').append( frameHTML );

			$iframes = $container.find('iframe').each(function() {
				var $this = $(this);
				$this.data('org_height', $this.attr('height') );
			});

			linchpin.tester.loadPage( '', default_url);
			linchpin.tester.resizeContainer();

			$('#size-toggle').append( sizeHTML ).buttonset();

			if( dimensions && dimensions != 'all' ) {
				linchpin.tester.appendDropdown();
			}

			// selectors below all need optimization
			// all events below can probably be moved to individual methods instead of all being nested -aware

			$(document).on('click',  '#responsive-page-tester-frames input[name=rpt-devicesizes]', linchpin.tester.toggleDeviceSizes )
					   .on('change', '#responsive-page-tester-frames #rpt-scrollbar', linchpin.tester.toggleScroll)
					   .on('change', '#responsive-page-tester-frames .size-select', linchpin.tester.sizeSelect)
					   .on('click',  '.responsive-page-tester a', linchpin.tester.toggleActive)
					   .on('keyup', function(e) {
							if (e.keyCode === 27) { // esc press
								linchpin.tester.toggleResponsiveDisplay(null, true);
							}
					   });

			//when frame loads
			$iframes.load(linchpin.tester.iframeLoaded);

			$(window).resize(linchpin.tester.resizeWindow);

			linchpin.tester.resizeWindow();

			$('#rpt-devicesizes').buttonset({ icons: { primary: 'ui-icon-circle-check'} });
			$('#rpt-scrollbar').button({ icons: { primary: 'ui-icon-arrowthick-2-e-w'} });

			$('#size_all').button( { icons: {primary:'ui-icon-star'} } );
			$('.size-select').not($('#size_all')).button( { icons: {primary:'ui-icon-circle-minus'} } );
		},

		/**
		 * frameLoaded function
		 * called when a frame is loaded
		 * @access public
		 * @return void
		 */
		iframeLoaded : function(){

			var $this	= $(this),
				url		= default_url,
				error	= false;

			try{
				url = linchpin.tester.sanitizeURL( $this.contents().get(0).location.href ); // + '?rpt_responsive=true';
			} catch(e) {
				error = true;
				url = default_url;
			}

			//load other pages with the same URL
			if( linchpin.tester.allFramesLoaded() ){
				if(error) {
					linchpin.tester.loadPage('', url);
				} else {
					linchpin.tester.loadPage($this, url);
				}
			} else { //when frame loads, hide loader graphic
				error = false;
				linchpin.tester.hideLoader( $this.parent().attr('id') );

				$(this).data('loaded', true);
			}

			$this.contents().find('a').each(function() {
				var $this = $(this),
					 href = linchpin.tester.sanitizeURL( $this.attr('href') );

				$this.attr('href', href );
			});
		},

		/**
		 * resizeWindow function.
		 * on window resize, resize all of our frames
		 *
		 */
		resizeWindow : function() {

			ctrls_h = $('#responsive-controls').outerHeight();

			$inner.css('margin-top', wp_toolbar_h + ctrls_h );

			var $iframe = $container.find('.frame iframe');

				header_h   = $iframe.parent().find('.frame-header:first').outerHeight();
				viewport_h = $(window).height() - ( wp_toolbar_h + ctrls_h + header_h + padding );

			if( 1 === deviceSize ) {
				$iframe.height( viewport_h );
			}
		},

		/**
		 * toggleResponsiveDisplay function.
		 *
		 * @param size (String) If a size is passed we only build a one size fits all version for default
		 * @param kill (Boolean) let's you destory the display
		 * @access public
		 * @return void
		 */
		toggleResponsiveDisplay : function( dimensions, kill ) {

			if( !dimensions && ( $('body').children('#responsive-page-tester-frames').length > 0 || true === kill ) ) {
				$('.responsive-page-tester a:first').removeClass('rpt-active').text('Responsive');

				$container.remove();
			} else {
				$('.responsive-page-tester a:first').addClass('rpt-active').text('Responsive (esc)');

				linchpin.tester.buildFrames( dimensions );
			}

			return false;
		}
	};

}(jQuery);