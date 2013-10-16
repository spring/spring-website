if(typeof(oldIE) == 'undefined') var oldIE = false;

$(document).ready(function() {
    // detect browser
    var browser = (navigator.userAgent) ? navigator.userAgent : '';
    if(typeof(clrIE) == 'boolean')
    {
        browser = 'ie';
    }
    else
    {
        browser = (browser.indexOf('Opera') >= 0) ? (
            (browser.indexOf('Opera Mini/') > 0) ? 'opera-mini' : 'opera') : (
            (browser.indexOf('Gecko/') > 0) ? 'mozilla' : (
                (browser.indexOf('WebKit/') > 0) ? 'webkit' : (
                    (browser.indexOf('MSIE') > 0) ? 'ie' : 'unknown'
                )
            )
        );
    }
    $('body').addClass('browser-' + browser + ((oldIE) ? ' old-ie transform' : ''));

    // transformations
    if(!oldIE)
    {
        setTimeout("$('body').addClass('transform');", 500);
        $(window).load(function() { $('body').addClass('transform'); });
    }

    // navigation
    $('div.nav-extra').not('div.nav-extra-footer').each(function()
    {
        var count = 0;
        $(this).find('a').each(function() {
            if(count > 0) $(this).before(' &bull; ');
            count ++;
        });
        if(!count) $(this).css('display', 'none');
    });
    
    $('#footer div.nav-links > a').each(function(i)
    {
        if(i > 0) $(this).before(' &bull; ');
    });
    
    $('.responsive-menu-nojs .responsive-menu').each(function()
    {
    	var $this = $(this),
    		link = $this.children('a'),
    		parent = $this.parents('.responsive-menu-nojs');
    	if (!link.length || !parent.length) return;
    	parent.removeClass('responsive-menu-nojs');
    	link.add(parent.find('.responsive-menu-hide')).click(function() { parent.toggleClass('responsive-menu-visible'); });
    });

    // swap title and buttons in posts and wrap them in div
    $('.postbody > .profile-icons:first-child + h3').each(function() {
        var $this = $(this);
        $this.prev().wrapAll('<div class="post-header" />');
        $this.prev().prepend($this);
    });

    // clear logo width/height
    $('#logo img').attr({
    	width	: '',
    	height	: ''
    });

    // clear divs
    $('#page-body, #footer').append('<div class="clear"></div>');
    $('.cp-mini:last').after('<div class="clear"></div>');
    
    // remove extra lines
    $('#page-body > hr, #cp-main > hr, #page-body > form > hr').remove();
    
    // unread posts
    $('dl.icon').each(function()
    {
        var bg = $(this).css('background-image');
        if(bg.length && bg.indexOf('_unread') > 0)
        {
            $(this).parents('li:first').addClass('unread');
        }
        else if(bg.length && bg.indexOf('forum_link') > 0)
        {
            $(this).parents('li:first').addClass('forum-link');
        }
    });
    
    // topic title
    $('body.section-viewtopic #page-body > h2:first').addClass('title');
    
    // index: reported/unapproved topics
    $('li.row a img').each(function()
    {
        if(this.src.indexOf('icon_topic_unapproved') > 0)
        {
            $(this).parents('li.row:first').addClass('unapproved');
        }
    });
    $('dd.lastpost a img').each(function()
    {
        if(this.src.indexOf('icon_topic_unapproved') > 0 || this.src.indexOf('icon_topic_reported') > 0)
        {
            var prev = $(this).parents('dl.icon:first').find('dt');
            if(!prev.length) return;
            if(!prev.find('div.extra').length)
            {
                prev.prepend('<div class="extra"></div>');
            }
            prev = prev.find('div.extra');
            $(this).parent('a').appendTo(prev);
        }
    });
    
    // remove rounded block within rounded block
    $('div.panel div.post, div.panel ul.topiclist, div.panel table.table1, div.panel dl.panel').parents('div.panel').addClass('panel-wrapper');
    
    // tabs
    $('#tabs, #navigation, #minitabs').each(function()
    {
        var last = false,
            count = 0;
        $('li', $(this)).each(function(i)
        {
            if(i == 0) $(this).addClass('first');
            last = $(this);
            count ++;
        });
        if(count < 2)
        {
            $(this).hide();
        }
        else
        {
            if(last !== false) last.addClass('last');
            $(this).find('hr').remove();
            $(this).parents('form').css('display', 'inline');
            $(this).append('<div class="clear"></div>');
            $(this).find('a').each(function()
            {
                if(!$('span', this).length)
                {
                    $(this).html('<span>' + $(this).html() + '</span>');
                }
            });
        }
    });
    $('#navigation').parents('.panel').removeClass('panel').addClass('cp-panel');
    
    // control panel: remove empty boxes
    $('#cp-main .panel').each(function()
    {
        var inner = $(this).find('.inner:first');
        if(!inner.length) return;
        if(inner.children().length < 2)
        {
            $(this).hide();
        }
    });
    
    // fix right side margin
    $('#page-body > p.rightside').each(function()
    {
        var next = $(this).next();
        if(next.is('p') && !next.hasClass('rightside')) next.css('margin-top', 0);
    });
    
    // pm post
    $('.post > div, .panel > div').addClass('inner');
    
    // emulate multiple backgrounds
    if(oldIE)
    {
        $('#header').wrapInner('<div class="hdr1"></div>');
        $('#footer').wrapInner('<div class="hdr1"><div class="hdr2"></div></div>');
        $('body').not('.simple').find('#page-body').wrap('<div id="page-body1"><div id="page-body2"></div></div>');
        $('#page-header').wrapInner('<div class="hdr1"><div class="hdr2"><div class="hdr3"><div class="hdr4"><div class="hdr5"></div></div></div></div></div>');
        $('div.panel > .inner').addClass('inner-panel');
        $('div.forabg, div.forumbg, div.panel-wrapper').not('.cp-panel').addClass('old-ie-wrap-1').wrapInner('<div class="hdr1-1"><div class="hdr1-2"><div class="hdr1-3"><div class="hdr1-4"><div class="hdr1-5"></div></div></div></div></div>');
        $('div.post, .panel, .cp-mini, ul.topiclist li').not('.header, .panel-wrapper').addClass('old-ie-wrap-2').wrapInner('<div class="hdr2-1"><div class="hdr2-2"><div class="hdr2-3"><div class="hdr2-4"><div class="hdr2-5"><div class="hdr2-6"><div class="hdr2-last"></div></div></div></div></div></div></div>');
    }

    // search box
    $('div.search-box input').focus(function() { $(this).parents('.search-box').addClass('focus'); }).blur(function() { $(this).parents('.search-box').removeClass('focus'); })

    // header search box
    $('#search-box form').submit(function() { var value = $('#search-box input:text').val(); return (value == laSearchMini || value == '') ? false : true; });
    $('#search-box input:text').focus(function() { 
        if(this.value == laSearchMini) this.value = '';
        $('#search-box').addClass('focused');
    }).blur(function() { 
        if(this.value == '') this.value = laSearchMini;
        $('#search-box').removeClass('focused');
    });

	// shorten long links in posts
	$('a.postlink').each(function() {
		var $this = $(this);
		
		if ($this.children().length)
		{
			return;
		}
		
		var html = $this.html();
		if (html.length > 50 && html.indexOf('://') > 0 && html.indexOf(' ') < 0)
		{
			$this.html(html.substr(0, 39) + ' ... ' + html.substr(-10));
		}
	});

    // resize big images
    function imageClicked(event)
    {
    	var $this = $(this);
    	if ($this.hasClass('zoomed-in'))
		{
			$this.removeClass('zoomed-in').css('max-width', $(this).attr('data-max-width') + 'px');
		}
		else
		{
			$this.addClass('zoomed-in').css('max-width', '');
		}
    }
    function zoomClicked(event)
    {
		imageClicked.apply($(this).prev().get(0), arguments);
		event.stopPropagation();
    }
	function resizeImage(width)
	{
		var $this = $(this);
		$this.wrap('<span class="zoom-container" />').attr('data-max-width', width).css({
			'max-width': width + 'px',
			cursor: 'pointer'
			}).addClass('zoom').click(imageClicked).after('<span class="zoom-image" />').next().click(zoomClicked);
	}
    function checkImage()
    {
		var maxWidth = Math.floor(this.parentNode.clientWidth - 10);
		if (this.width > maxWidth)
		{
			resizeImage.call(this, maxWidth);
		}
    }
    $('.postbody img').each(function() {
    	var $this = $(this);
    	if ($this.closest('a').length)
    	{
    		return;
		}
		if (this.complete)
		{
			checkImage.call(this);
		}
		else
		{
			$this.load(checkImage);
		}
	});
    
    // old browser warning
    function hasCookie(search)
    {
        var cookie = document.cookie.split(';');
        search += '=';
        for(var i=0; i<cookie.length; i++)
        {
            var c = cookie[i];
            while (c.charAt(0)==' ') c = c.substring(1,c.length);
            if (c.indexOf(search) == 0) return true;
        }
        return false;
    }
    if(oldIE && imagesetLang && !hasCookie('oldie'))
    {
        $('body').prepend('<div id="old-browser" style="display: none;"></div>');
        $('#old-browser').load(imagesetLang + '/oldie.txt', function() { $('#old-browser').slideDown(); });
    }
});
