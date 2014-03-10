jQuery(document).ready(function($) {

	$('.wplikebtns').buttonset();
	$('#rl_fb_overlay_color_input').wpColorPicker();

	$(document).on('click', '#rl-fb-modal-yes', function(event) {
		$('#rl-fb-show-overlay-yes').trigger('click');
		$('#rl-fb-show-overlay-no').button('disable');
		$('#rl-fb-show-overlay-yes').button('disable');
		$('#rl-fb-show-close-button-no').trigger('click');
		$('#rl-fb-show-close-button-no').button('disable');
		$('#rl-fb-show-close-button-yes').button('disable');
		$('#rl-fb-enable-escape-button-no').trigger('click');
		$('#rl-fb-enable-escape-button-no').button('disable');
		$('#rl-fb-enable-escape-button-yes').button('disable');
		$('#rl-fb-hide-on-overlay-click-no').trigger('click');
		$('#rl-fb-hide-on-overlay-click-no').button('disable');
		$('#rl-fb-hide-on-overlay-click-yes').button('disable');
		$('#rl-fb-hide-on-content-click-no').trigger('click');
		$('#rl-fb-hide-on-content-click-no').button('disable');
		$('#rl-fb-hide-on-content-click-yes').button('disable');
	});

	$(document).on('click', '#rl-fb-modal-no', function(event) {
		$('#rl-fb-show-overlay-no').button('enable');
		$('#rl-fb-show-overlay-yes').button('enable');
		$('#rl-fb-show-overlay-yes').trigger('click');
		$('#rl-fb-show-close-button-no').button('enable');
		$('#rl-fb-show-close-button-yes').button('enable');
		$('#rl-fb-show-close-button-no').trigger('click');
		$('#rl-fb-enable-escape-button-no').button('enable');
		$('#rl-fb-enable-escape-button-yes').button('enable');
		$('#rl-fb-enable-escape-button-no').trigger('click');
		$('#rl-fb-hide-on-overlay-click-no').button('enable');
		$('#rl-fb-hide-on-overlay-click-yes').button('enable');
		$('#rl-fb-hide-on-overlay-click-no').trigger('click');
		$('#rl-fb-hide-on-content-click-no').button('enable');
		$('#rl-fb-hide-on-content-click-yes').button('enable');
		$('#rl-fb-hide-on-content-click-no').trigger('click');
	});

	$('#rl_pp_opacity_span').slider({
		value: rlArgs.opacity_pp,
		min: 0,
		max: 100,
		step: 1,
		orientation: 'horizontal',
		slide: function(e, ui) {
			$('#rl_pp_opacity_input').attr('value', ui.value);
			$('#rl_pp_opacity_span').attr('title', ui.value);
		}
	});

	$('#rl_fb_overlay_opacity_span').slider({
		value: rlArgs.opacity_fb,
		min: 0,
		max: 100,
		step: 1,
		orientation: 'horizontal',
		slide: function(e, ui) {
			$('#rl_fb_overlay_opacity_input').attr('value', ui.value);
			$('#rl_fb_overlay_opacity_span').attr('title', ui.value);
		}
	});

	$(document).on('change', '#rl-pp-slideshow-yes, #rl-pp-slideshow-no', function(event) {
		if($(this).val() === 'yes') {
			$('#rl_pp_slideshow_delay').fadeIn(300);
		} else {
			$('#rl_pp_slideshow_delay').fadeOut(300);
		}
	});

	$(document).on('change', '#rl-sb-hide-bars-yes, #rl-sb-hide-bars-no', function(event) {
		if($(this).val() === 'yes') {
			$('#rl_sb_hide_bars_delay').fadeIn(300);
		} else {
			$('#rl_sb_hide_bars_delay').fadeOut(300);
		}
	});

	$(document).on('change', '#rl-enable-custom-events-yes, #rl-enable-custom-events-no', function(event) {
		if($(this).val() === 'yes') {
			$('#rl_custom_events').fadeIn(300);
		} else {
			$('#rl_custom_events').fadeOut(300);
		}
	});

	$(document).on('click', 'input#reset_rl_configuration', function(event) {
		return confirm(rlArgs.resetScriptToDefaults);
	});

	$(document).on('click', 'input#reset_rl_settings', function(event) {
		return confirm(rlArgs.resetSettingsToDefaults);
	});
});