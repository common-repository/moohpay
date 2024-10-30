(function($) {
	var lang = ($('html').attr('lang')+'').substr(0, 2);
	var __ = function(text) {
		var map = {
			'de': {
				'Connect': 'Verbinden',
				'Not connected': 'Nicht verbunden',
				'Loading Products': 'Lade Produkte',
				'Insert Product': 'Produkt einfügen',
				'Product': 'Produkt',
				'Insert As': 'Einfügen als',
				'Embedded Sales Page': 'Integrierte Verkaufs-Seite',
				'Product Box': 'Produkt-Box',
				'Simple Product Box': 'Einfache Produkt-Box',
				'Link': 'Link',
				'There were no products found.': 'Es wurden keine Produkte gefunden.',
				'Cancel': 'Abbrechen'
			}
		};
		if(map[lang] && map[lang][text]) return map[lang][text];
		return text;
	};
	tinymce.PluginManager.add('moohpay', function(editor, url){
		editor.addButton('moohpay', {
			text: 'MoohPay™',
			icon: 'moohpay',
			onclick: function() {
				if(!moohpay.accesskey) {
					editor.windowManager.open({
						title: 'MoohPay™',
						body: [{
							type: 'container',
							html: '<div style="text-align: center;">'+__('Not connected')+'.<br></div>'
						}],
						buttons: [{
							text: __('Connect'),
							classes: 'primary',
							style: 'min-width: 100px; text-align: center;',
							onclick: function(){
								editor.windowManager.close(this);
								window.location.href = $('#adminmenu .toplevel_page_moohpay > a').attr('href');
							}
						},{
							text: __('Cancel'),
							style: 'min-width: 100px; text-align: center;',
							onclick: 'close'
						}]
					});
					return;
				}
				var loadingDialog = editor.windowManager.open({
					title: 'MoohPay™',
					body: [{
						type: 'container',
						html: '<div style="text-align: center;"><img src="'+moohpay.wpUrl+'/wp-admin/images/wpspin_light-2x.gif" style="height: 2em;"><div style="margin-top: 0.5em;">'+__('Loading Products')+'…</div></div>'
					}],
					buttons: []
				});
				moohpay.request.list_products(function(products){
					editor.windowManager.close(loadingDialog);
					if(products && products.length) {
						var values = [];
						var map = {};
						for(var i=0; i<products.length; i++) {
							values.push({text: products[i].name, value: products[i].productid});
							map[products[i].productid] = products[i];
						}
						editor.windowManager.open({
							title: 'MoohPay™ – '+__('Insert Product'),
							width: 400,
							height: 120,
							body: [{
								type: 'listbox',
								name: 'productid',
								label: __('Product'),
								values: values
							},{
								type: 'listbox',
								name: 'type',
								label: __('Insert As'),
								values: [
									{text: __('Embedded Sales Page'), value: 'embed'},
									{text: __('Product Box'), value: 'box'},
									{text: __('Simple Product Box'), value: 'simplebox'},
									{text: __('Link'), value: 'link'}
								]
							}],
							onsubmit: function(event) {
								switch(event.data.type) {
									case 'link':
										editor.insertContent('<a href="' + map[event.data.productid].url + '">'+map[event.data.productid].name+'</a>');
										break;
									case 'embed':
									case 'box':
									case 'simplebox':
										editor.insertContent('[moohpay product="' + event.data.productid + '" type="'+event.data.type+'"]');
										break;
								}
							}
						});
					}else{
						editor.windowManager.open({
							title: 'MoohPay™',
							body: [{
								type: 'container',
								html: __('There were no products found.')
							}],
							buttons: [{
								text: __('Cancel'),
								classes: 'primary',
								style: 'min-width: 100px; text-align: center;',
								onclick: 'close'
							}]
						});
					}
				});
			}
		});
	});
})(jQuery);