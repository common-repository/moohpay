<?php

defined('ABSPATH') or die('Direct Access Forbidden.');
defined('MOOHPAY_PLUGIN_DIR') or die('Used in wrong context.');


function moohpay_admin_init() {
	register_setting('moohpay', 'moohpay_options');
	add_settings_section(
		'moohpay_section_account',
		__('Account Connection', 'moohpay'),
		function($args){
			echo '<p id="'.esc_attr($args['id']).'">'.__('To connect your MoohPay™ account, click the connection button and enter your e-mail address and password.', 'moohpay').'</p>';
		},
		'moohpay'
	);
	add_settings_field(
		'moohpay_field_accesskey',
		__('Your Account', 'moohpay'),
		function($args){
			$options = get_option('moohpay_options');
			$accesskey = $options[$args['option']];
			$user_info = $accesskey ? array_combine(array('uid', 'accesskey', 'email'), explode('_', $accesskey, 3)) : array();
?>
			<input type="hidden" name="moohpay_options[<?php echo esc_attr($args['option']) ?>]" value="<?php echo esc_attr($accesskey) ?>">
			<?php if($accesskey): ?>
				<p class="notice notice-success">
					<?php echo __('You are <strong>connected</strong> with MoohPay™:', 'moohpay'); ?><br>
					<strong><?php echo esc_html($user_info['email']) ?></strong>
				</p>
				<input id="moohpay-connect" type="button" class="button" value="<?php echo __('Change Account', 'moohpay') ?>">
			<?php else: ?>
				<p class="notice notice-error">
					<?php echo __('You are <strong>not connected</strong> with MoohPay™.', 'moohpay'); ?>
				</p>
				<input id="moohpay-connect" type="button" class="button" value="<?php echo __('Connect Now', 'moohpay') ?>">
			<?php endif; ?>
			<script>
			(function($){
				!$ && alert('Could not load MoohPay plugin: jQuery not loaded.');
				var overlay, dialog;
				$('#moohpay-connect').click(function(){
					overlay = $('<div/>');
					overlay.css({
						'background-color': '#000',
						'opacity': 0.5,
						'position': 'fixed',
						'left': 0,
						'top': 0,
						'bottom': 0,
						'right': 0,
						'z-index': 300000
					});
					overlay.appendTo('body');
					try {
						document.activeElement.blur();
					}catch(e) {}
					dialog = $('<iframe src="<?php echo MOOHPAY_CONNECT_URL ?>"/>');
					dialog.css({
						'border': 'none',
						'border-radius': '4px',
						'box-shadow': 'rgba(0,0,0,0.3) 0 0 200px',
						'overflow': 'auto',
						'width': '100vw',
						'height': '100vh',
						'max-width': '480px',
						'max-height': '340px',
						'position': 'fixed',
						'left': '50%',
						'top': '50%',
						'transform': 'translate(-50%, -50%)',
						'background-color': '#fff',
						'z-index': 300001
					});
					dialog.appendTo('body');
				});
				window.addEventListener('message', function(event){
					if(event.origin!=='https://www.moohpay.com') return;
					dialog.remove();
					var data;
					try {
						data = $.parseJSON(event.data);
					}catch(e){}
					if(!data) return alert('Error: Could not handle connection request. Please reload this page and try again.');
					if(data.uid && data.accesskey && data.email) {
						$('input[name="moohpay_options[<?php echo $args['option'] ?>]"]').val(data.uid+'_'+data.accesskey+'_'+data.email);
						$('#submit').click();
					}else{
						overlay.remove();
					}
				}, false);
			})(jQuery);
			</script>
<?php
		},
		'moohpay',
		'moohpay_section_account',
		array(
			'option' => 'accesskey'
		)
	);
}
add_action('admin_init', 'moohpay_admin_init');
 
function moohpay_admin_menu() {
	add_menu_page(
		'MoohPay™',
		'MoohPay™',
		'manage_options',
		'moohpay',
		function(){
			if(isset($_GET['settings-updated'])) {
				add_settings_error('moohpay_messages', 'moohpay_message', __('Settings Saved', 'moohpay'), 'updated');
			}
			settings_errors('moohpay_messages');
?>
			<div class="wrap">
				<h1><div style="font-family: 'moohpay' !important; line-height: 0; padding: 40px 0; font-size: 200px;">&#xe902;</div></h1>
				<form action="options.php" method="post">
<?php
					settings_fields('moohpay');
					do_settings_sections('moohpay');
					submit_button('Save Settings');
?>
				</form>
			</div>
<?php
		}
	);
}
add_action('admin_menu', 'moohpay_admin_menu');
