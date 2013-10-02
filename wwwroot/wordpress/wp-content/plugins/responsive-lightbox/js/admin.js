jQuery(document).ready(function($) {

	$('.wplikebtns').buttonset();
	$('#rl_pp_opacity_span').slider({
		value: rlArgs.opacity,
		min: 0,
		max: 100,
		step: 1,
		orientation: 'horizontal',
		slide: function(e, ui) {
			$('#rl_pp_opacity_input').attr('value', ui.value);
		}
	});

	$(document).on('change', '#rl-slideshow-yes, #rl-slideshow-no', function(event) {
		if($('#rl-slideshow-yes:checked').val() === 'yes') {
			$('#rl_pp_slideshow_delay').fadeIn(300);
		} else if($('#rl-slideshow-no:checked').val() === 'no') {
			$('#rl_pp_slideshow_delay').fadeOut(300);
		}
	});

	$(document).on('change', '#rl-hide-bars-yes, #rl-hide-bars-no', function(event) {
		if($('#rl-hide-bars-yes:checked').val() === 'yes') {
			$('#rl_sw_hide_bars_delay').fadeIn(300);
		} else if($('#rl-hide-bars-no:checked').val() === 'no') {
			$('#rl_sw_hide_bars_delay').fadeOut(300);
		}
	});

	$(document).on('click', 'input#reset_rl_configuration', function(event) {
		return confirm(rlArgs.resetScriptToDefaults);
	});
});