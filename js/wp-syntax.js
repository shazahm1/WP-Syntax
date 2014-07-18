jQuery(document).ready(function($)
{
	$('.wp_syntax').bind(
	{
		mouseover: function()
		{
			var w = $(this).find('table').outerWidth();
			var hw = $(document).width() - $(this).offset().left - 20;
			
			/*
			 * Test code.
			 */
			/*var left, top;
			left = $(this).offset().left;
			top = $(this).offset().top;
			
			$(this)
				.appendTo('body')
				.css({
				'position': 'absolute',
				'left': left + 'px',
				'top': top + 'px'
			});
			*/
			
			if(w > $(this).outerWidth())
				$(this).css({'position':'relative', 'z-index':'9999', 'box-shadow':'5px 5px 5px #888', 'width':(w > hw ? hw : w)+'px'});
		},
		mouseout: function()
		{
			//$(this).removeAttr('style');
		},
		dblclick: function()
		{
			//Create text area on top of code on double click
			//This can make copying of the code easier
			
			jthis = $(this);
			if (!jthis.data('hasTextArea')) {
				var code = jthis.find(".theCode").html();
				var ta = $('<textarea spellcheck="false"/>');
				ta.html(code);
				
				var pre = jthis.find('.code > pre');
				ta.css('font-family', pre.css('font-family'));
				ta.css('font-size', pre.css('font-size'));
				ta.css('line-height', pre.css('line-height'));
				
				ta.css('height', "100%");
				ta.css('width', "100%");
				
				ta.css('position','absolute');
				ta.css('top', 0);
				ta.css('left',0);
				ta.css('margin', 0);
				ta.css('padding-left', pre.css('padding-left'));
				ta.css('padding-top', pre.css('padding-top'));
				ta.css('border','0px');
				
				ta.css('resize','none');
				ta.css('outline','none');
				
				ta.focusout(function(){
					ta.remove();
					jthis.data('hasTextArea',false);
				});
				
				//readjust position and size if using line numbers
				var line_numbers = jthis.find(".line_numbers");
				if (line_numbers.length != 0) {
					ta.css('left',line_numbers.width()+"px");
					ta.css('width', jthis.width()-line_numbers.width()+"px");
				}
				
				ta.appendTo(jthis);
				
				ta.select();
				ta.focus();
				
				jthis.data('hasTextArea',true);
			
			}
		}
	});
});