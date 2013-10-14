/***********************************************
 * SparkOptions Javascript
 * 
 * @author Chris Mavricos, Sevenspark http://sevenspark.com
 * @version 1.0
 * Last modified 2012-02-08
 * 
 ***********************************************/

jQuery(document).ready(function($){
	
	var DEBUG = false;
	
	$('.spark-panel').hide().first().show();
	
	$('.spark-nav ul li a').click(function(e){
		e.preventDefault();
		
		$('#current-panel-id').val( $(this).attr('href').substr(7) ); //chop off #spark-
		
		$('.spark-nav ul li a.current').removeClass('current');
		$(this).addClass('current');
		
		var $target = $( $(this).attr('href') );
		$('.spark-panel').css('minHeight', 0).stop().slideUp(function(){
			$(this).css('minHeight', '');
		});
		$target.stop().css('minHeight', 0).slideDown(function(){
			$(this).css( { height : '', padding: '', minHeight : '' } );
		});
		
	});
	$('.spark-nav ul li a[href="#spark-'+$('#current-panel-id').val()+'"]').click();
	
	/* Input Sliding Interface */
	$('.spark-admin-op input[type="checkbox"], #wpmega-demo .spark-admin-op input[type="radio"]')
		.each(function(k, el){
			var tog = $(el).is(':checked') ? 'on' : 'off';
			var $toggle = $('<label class="spark-toggle-onoff '+tog+'" for="'+$(el).attr('id')+
								'"><span class="spark-toggle-inner"><span class="spark-toggle-on">On</span><span class="spark-toggle-mid"></span><span class="spark-toggle-off">Off</span></span></label>');
					
			switch($(el).attr('type')){
			
				case 'checkbox':
			
					$(el).after($toggle);
					$(el).hide();
					
					$toggle.click(function(){
						
						//console.log($(el).is(':checked') ? 'checked' : 'not checked');
						
						if($(el).is(':checked')){
							//console.log('checked');
							var $this = $(this);
							$this.find('.spark-toggle-inner').animate({
								'margin-left'	:	'-51px'
							}, 'normal', function(){
								$this.removeClass('on').addClass('off');
							});
							$(el).attr('checked', false);
						}
						else{
							//console.log('not checked');
							var $this = $(this);
							$this.find('.spark-toggle-inner').animate({
								'margin-left'	:	'0px'
							}, 'normal', function(){
								$this.removeClass('off').addClass('on');
							});
							$(el).attr('checked', true);
						}
						
						return false;	//stops the label click from reversing the check, which is necessary in IE
					});
					break;
					
				case 'radio' :
					var $label = $(el).next('label');
					var labelText = $label.text();
					$label.hide();
					//console.log(labelText);
					
					$(el).after('<span class="spark-tog-label">'+labelText+'</span>');
					$(el).after($toggle);				
					$(el).hide();
					
					$toggle.click(function(){
						if($(this).prev().is(':checked')){
							//Do nothing, it's double clicking a radio button
						}
						else{
							
							var oldID = $('input[name="'+$(el).attr('name')+'"]:checked').attr('id');
							
							//turn on
							var $this = $(this);
							$this.find('.spark-toggle-inner').animate({
								'margin-left'	:	'0px'
							}, 'normal', function(){
								$this.removeClass('off').addClass('on');
							});
							//$this.prev().attr('checked', true);
							$(el).attr('checked', true);
							
							//turn off the old
							$('label[for="'+oldID+'"] .spark-toggle-inner').animate({
								'margin-left'	:	'-51px'
							}, 'normal', function(){
								$(this).parent('label').removeClass('on').addClass('off');
							})
							.siblings('input[type="radio"]').attr('checked', false);
						}
						return false;
					});
					break;
				}
			});
	
	
});


