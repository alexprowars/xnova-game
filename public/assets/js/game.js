var isMobile = /Android|Mini|webOS|iPhone|iPad|iPod|BlackBerry/i.test(navigator.userAgent);

function ShowHiddenBlock (id)
{
    $('#'+id).toggle();
}

function load (url)
{
	application.$router.push(url, function() {},
	function() {
		application.loadPage(url);
	});
}

$(document).ready(function()
{
	var body = $('body');

	body.on('mouseenter', '.tooltip:not(.tooltipstered)', function()
	{
		var maxWidth = null;
		var _this = $(this);

		if (_this.data('width') !== undefined)
			maxWidth = parseInt(_this.data('width'));

		_this.tooltipster({
			delay: 100,
			distance: 0,
			maxWidth: maxWidth,
			contentAsHTML: true,
			interactive: _this.hasClass('sticky'),
			functionInit: function(instance)
			{
				if (_this.hasClass('script'))
					instance.content(eval(_this.data('content')));
				else if (typeof _this.data('content') === "undefined")
					instance.content(_this.find('.tooltip-content'));
				else
					instance.content(_this.data('content'));
			}
		}).tooltipster('open');
	})
	.on('click', 'a', function(e)
	{
		var el = $(this);
		var url = el.attr('href');

		if (!url || el.hasClass('window') || url.indexOf('#') === 0)
			return false;

		if (url.indexOf('javascript') === 0 || url.indexOf('mailto') === 0 || url.indexOf('#') >= 0 || el.attr('target') === '_blank')
			return true;
		else
		{
			e.preventDefault();

			load(url);
		}

		return false;
	})
	.on('click', '.main-content form[class!=noajax] input[type=submit], .main-content form[class!=noajax] button[type=submit]', function(e)
	{
		e.preventDefault();

		var button = $(this);
		var form = button.closest('form');

		form.append($('<input/>', {type: 'hidden', name: button.attr('name'), value: button.attr('value')}));
		form.submit();
	})
	.on('submit', '.main-content form[class!=noajax]', function(e)
	{
		e.preventDefault();

		var form = $(this);

		application.loader = true;

		var formData = new FormData(this);

		$.ajax({
		    url: form.attr('action'),
		    data: formData,
		    type: 'post',
			dataType: 'json',
		    contentType: false,
		    processData: false,
			success: function (result) {
				application.applyData(result.data);
			},
			error: function() {
				alert('Что-то пошло не так!? Попробуйте еще раз');
			},
			complete: function()
			{
				$('#tooltip').hide();
				application.loader = false;
			}
		});
	})
	.on('submit', '.jconfirm-dialog form', function(e)
	{
		e.preventDefault();

		application.loader = true;

		var formData = new FormData(this);
		formData.append('popup', 'Y');

		$.ajax({
			url: $(this).attr('action'),
			type: 'post',
			data: formData,
			dataType: 'json',
			processData: false,
			contentType: false,
			success: function (result)
			{
				if (result.data.message.length > 0 && result.data.message !== '')
				{
					result.data.message.forEach(function(item)
					{
						$.toast({
							text: item.message,
							icon: item.type
						});
					})
				}
				else if (result.data.html !== '')
				{
					$('.jconfirm-content').html(result.data.html);

					TextParser.parseAll();
				}
			},
			error: function() {
				alert('Что-то пошло не так!? Попробуйте еще раз');
			},
			complete: function() {
				application.loader = false;
			}
		})
	})
	.on('change', 'input.checkAll', function()
	{
		var checked = $(this).is(':checked');

		$(this).closest('form').find('input[type=checkbox]').each(function()
		{
			$(this).prop('checked', checked);
		});
	})
	.on('click', '.popup-user', function(e)
	{
		e.preventDefault();

		showWindow('', $(this).attr('href'))
	});

	if (typeof swipe !== 'undefined' && !navigator.userAgent.match(/(\(iPod|\(iPhone|\(iPad)/))
	{
		body.swipe(
		{
			swipeLeft: function()
			{
				if ($('.menu-sidebar').hasClass('active'))
					$('.menu-toggle').click();
				else
					$('.planet-toggle').click();
			},
			swipeRight: function()
			{
				if ($('.planet-sidebar').hasClass('active'))
					$('.planet-toggle').click();
				else
					$('.menu-toggle').click();
			},
			threshold: 100,
			excludedElements: ".table-responsive",
			fallbackToMouseEvents: false,
			allowPageScroll: "auto"
		});
	}
});

function showWindow (title, url, width)
{
	if (isMobile)
		return window.location.href = url.split('ajax').join('').split('popup').join('');

	if (width === undefined)
		width = 600;

	$.dialog({
		title: '',
		theme: 'dialog',
		useBootstrap: false,
		boxWidth: width,
		backgroundDismiss: true,
		animation: 'opacity',
		closeAnimation: 'opacity',
		animateFromElement: false,
		draggable: false,
		content: function ()
		{
			return $.ajax({
				url: url,
				type: 'get',
				data: {'popup': 'Y'},
				success: function (result)
				{
					this.setTitle(result.data.title);
					this.setContent(result.data.html);
				}.bind(this)
			});
		}
	});
}

function closeWindow()
{
	jconfirm.instances.forEach(function(item) {
		item.close();
	});
}