jQuery(document).ready(function($) {

    jQuery("#ms-pro-meta-link-wrap").appendTo('#screen-meta-links');

    // Enable the correct options for this slider type
    var switchType = function(slider) {
        jQuery('.metaslider .option:not(.' + slider + ')').attr('disabled', 'disabled').parents('tr').hide();
        jQuery('.metaslider .option.' + slider).removeAttr('disabled').parents('tr').show();

        // make sure that the selected option is available for this slider type
        if (jQuery('.effect option:selected').attr('disabled') === 'disabled') {
            jQuery('.effect option:enabled:first').attr('selected', 'selected');
        }

        // make sure that the selected option is available for this slider type
        if (jQuery('.theme option:selected').attr('disabled') === 'disabled') {
            jQuery('.theme option:enabled:first').attr('selected', 'selected');
        }

        // slides - set red background on incompatible slides
        jQuery('.metaslider .slide:not(.' + slider + ')').css('background', '#FFD9D9');
        jQuery('.metaslider .slide.' + slider).css('background', '');
    };

    // enable the correct options on page load
    switchType(jQuery('.metaslider .select-slider:checked').attr('rel'));

    // handle slide libary switching
    jQuery('.metaslider .select-slider').click(function() {
        switchType(jQuery(this).attr('rel'));
    });

    // return a helper with preserved width of cells
    var helper = function(e, ui) {
        ui.children().each(function() {
            jQuery(this).width(jQuery(this).width());
        });
        return ui;
    };

    // drag and drop slides, update the slide order on drop
    jQuery(".metaslider .left table tbody").sortable({
        helper: helper,
        handle: 'td.col-1',
        stop: function() {
            jQuery(".metaslider .left table").trigger('updateSlideOrder');
        }
    });

    // bind an event to the slides table to update the menu order of each slide
    jQuery('.metaslider .left table').live('updateSlideOrder', function(event) {
        jQuery('tr', this).each(function() {
            jQuery('input.menu_order', jQuery(this)).val(jQuery(this).index());
        });
    });

    // show the confirm dialogue
    jQuery(".confirm").live('click', function() {
        return confirm(metaslider.confirm);
    });

    $('.useWithCaution').change(function(){
        if(!this.checked) {
            return alert(metaslider.useWithCaution);
        }
    });

    // show the confirm dialogue
    jQuery(".toggle").live('click', function(e) {
        e.preventDefault();
        jQuery(this).next('.message').toggle();
    });

    // helptext tooltips
    jQuery(".metaslider .tipsy-tooltip").tipsy({className: 'msTipsy', live: true, delayIn: 500, html: true, fade: true, gravity: 'e'});
    jQuery(".metaslider .tipsy-tooltip-top").tipsy({live: true, delayIn: 500, html: true, fade: true, gravity: 'se'});

    // Select input field contents when clicked
    jQuery(".metaslider .shortcode input").click(function() {
        this.select();
    });

    // show the spinner while slides are being added
    function checkPendingRequest() {
        if (jQuery.active > 0) {
            jQuery(".metaslider .spinner").show();
            jQuery(".metaslider input[type=submit]").attr('disabled', 'disabled');
        } else {
            jQuery(".metaslider .spinner").hide();
            jQuery(".metaslider input[type=submit]").removeAttr('disabled');
        }

        setTimeout(checkPendingRequest, 1000); 
    }

    checkPendingRequest();

    // return lightbox width
    var getLightboxWidth = function() {
        var width = parseInt(jQuery('input.width').val(), 10) + 'px';

        if (jQuery('#carouselMode').is(':checked')) {
            width = '75%';
        }
        
        return width;
    };

    // return lightbox height
    var getLightboxHeight = function() {
        var height = parseInt(jQuery('input.height').val(), 10);

        if (!isNaN(height)) {
            height = height + 80 + 'px'
        } else {
            height = '70%';
        }

        return height;
    };

    // IE10 treats placeholder text as the actual value of a textarea
    // http://stackoverflow.com/questions/13764607/html5-placeholder-attribute-on-textarea-via-jquery-in-ie10
    var fixIE10PlaceholderText = function() {
        jQuery("textarea").each(function() {
            if (jQuery(this).val() == jQuery(this).attr('placeholder')) {
                jQuery(this).val('');
            }
        });
    }

    // AJAX save & preview
    jQuery(".metaslider form").find("input[type=submit]").click(function(e) {
        e.preventDefault();

        // update slide order
        jQuery(".metaslider .left table").trigger('updateSlideOrder');

        fixIE10PlaceholderText();

        // get some values from elements on the page:
        var the_form = jQuery(this).parents("form");
        var data = the_form.serialize();
        var url = the_form.attr('action');
        var button = e.target;

        jQuery(".metaslider .spinner").show();
        jQuery(".metaslider input[type=submit]").attr('disabled', 'disabled');

        jQuery.ajax({   
            type: "POST",
            data : data,
            cache: false,
            url: url,
            success: function(data) {
                // update the slides with the response html
                $(".metaslider .left tbody").html($(".metaslider .left tbody", data).html());
                
                fixIE10PlaceholderText();

                if (button.id === 'preview') {
                    jQuery.colorbox({
                        iframe: true,
                        href: metaslider.iframeurl + "?slider_id=" + jQuery(button).data("slider_id"),
                        transition: "elastic",
                        innerHeight: getLightboxHeight(),
                        innerWidth: getLightboxWidth(),
                        scrolling: false,
                        fastIframe: false
                    });
                }
            }   
        });
    });
});