<?php
/*
 * Plugin Name: MoohPay™
 * Version: 1.2.5
 * Description: Sell Like A Cowboy. Official MoohPay™ WordPress plug-in for selling any digital goods.
 * Author: MoohPay™
 * Author URI: https://www.moohpay.com/wordpress-plugin
 * License: GPLv3
 */

defined('ABSPATH') or die('Direct Access Forbidden.');

define('MOOHPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MOOHPAY_JS_BRIDGE_FILE', 'https://www.moohpay.com/site/inc/wp/moohpay-v1r06.min.js');
define('MOOHPAY_AJAX_RESPONDER', 'https://www.moohpay.com/ajax/WordPressPlugin_v1');
define('MOOHPAY_CONNECT_URL', 'https://www.moohpay.com/wp-connect');
define('MOOHPAY_IFRAME_BASE', 'https://www.moohpay.com/mstore/');
define('MOOHPAY_PLUGIN_URL', plugins_url('/', __FILE__));
define('MOOHPAY_SALESKEY_VAR', '_mpsk');
define('MOOHPAY_RESTRICTED_PAGE_PRIORITY', 90000);


// Add options page
require_once(MOOHPAY_PLUGIN_DIR.'options_page.php');

// Load text domain
function moohpay_load_textdomain() {
	load_plugin_textdomain('moohpay', false, dirname(plugin_basename(__FILE__)).'/languages');
}
add_action('init', 'moohpay_load_textdomain');

if(is_admin()) {

	// Back end

	function moohpay_admin_head() {
		?>
		<style>
		@font-face {
			font-family: 'moohpay';
			src: url('<?php echo MOOHPAY_PLUGIN_URL; ?>fonts/moohpay.eot?fqpq9a');
			src: url('<?php echo MOOHPAY_PLUGIN_URL; ?>fonts/moohpay.eot?fqpq9a#iefix') format('embedded-opentype'),
				url('<?php echo MOOHPAY_PLUGIN_URL; ?>fonts/moohpay.ttf?fqpq9a') format('truetype'),
				url('<?php echo MOOHPAY_PLUGIN_URL; ?>fonts/moohpay.woff?fqpq9a') format('woff'),
				url('<?php echo MOOHPAY_PLUGIN_URL; ?>fonts/moohpay.svg?fqpq9a#moohpay') format('svg');
			font-weight: normal;
			font-style: normal;
		}
		.toplevel_page_moohpay .dashicons-admin-generic:before,
		.mce-i-moohpay:before {
			font-family: 'moohpay';
			content: '\e900';
		}
		</style>
		<script src="<?php echo MOOHPAY_JS_BRIDGE_FILE; ?>"></script>
		<script>
		(function(){
			if(!window.moohpay) return;
			moohpay.wpUrl = '<?php bloginfo('wpurl'); ?>';
			<?php
			$moohpay_options = get_option('moohpay_options');
			if($moohpay_options && isset($moohpay_options['accesskey']) && $moohpay_options['accesskey']):
			?>
			moohpay.accesskey = '<?php echo $moohpay_options['accesskey']; ?>';
			<?php
			endif;
			?>
		})();
		</script>
		<?php
	}
	add_action('admin_head', 'moohpay_admin_head');

	function moohpay_mce_buttons($buttons) {
		array_push($buttons, 'moohpay');
		return $buttons;
	}
	add_filter('mce_buttons', 'moohpay_mce_buttons');
	
	function moohpay_mce_external_plugins($plugin_array) {
		$plugin_array['moohpay'] = MOOHPAY_PLUGIN_URL.'js/moohpay-tinymce.js';
		return $plugin_array;
	}
	add_filter('mce_external_plugins', 'moohpay_mce_external_plugins');

	function moohpay_render_meta_box($post) {
		global $pagenow;
		wp_nonce_field(basename(__FILE__), 'moohpay-nonce');
		$restrict = get_post_meta($post->ID, 'moohpay-restrict', true);
		$permalink = (!empty($pagenow) && in_array($pagenow, array('post-new.php'))) ? null : get_permalink($post->ID);
		$permalink_json = wp_json_encode($permalink);
		$html = '';
		$html.= '<p>'.__('Access Authorization', 'moohpay').': <strong style="color: '.($restrict ? '#c00;">'.__('Customers Only', 'moohpay') : '#6a0;">'.__('All Visitors', 'moohpay')).'</strong></p>';
		$html.= '<div id="moohpay-restrict-container" style="display: none;"><p><select id="moohpay-restrict" name="moohpay-restrict"><option value="">'.__('No Restriction', 'moohpay').'</option><optgroup label="'.__('Only for customer of', 'moohpay').'"><option value="" disabled>'.__('No products found.', 'moohpay').'</option></optgroup></select></p></div>';
		if(!$restrict) {
			$html.= '<div id="moohpay-restrict-info"><p><i>'.__('You can restrict access to this page to people who have purchased your product by selecting a product and saving the changes.', 'moohpay').'</i></p></div>';
		}
?>
<script>
jQuery(function($){
	var permalink = <?php echo $permalink_json ? $permalink_json : 'null'; ?>;
	moohpay.request.list_products(function(products){
		var select = $('#moohpay-restrict');
		if(products && products.length) {
			var optgroup = select.find('optgroup');
			optgroup.html('');
			for(var i=0; i<products.length; i++) {
				var product = products[i];
				var option = $('<option/>');
				option.attr('value', product.productid);
				option.attr('data-redirect', product.redirect);
				option.text('('+product.productid+') '+product.name);
				optgroup.append(option);
			}
			select.on('change', function(){
				$('#moohpay-restrict-info').slideUp();
				var val = select.val();
				if(val && permalink) {
					var option = select.find('option[value="'+val+'"]');
					var redirect = option.data('redirect');
					var redirect_match = false;
					var valid_msg = function(){
						$('#moohpay-sync-link').removeClass('invalid').addClass('valid').html('<div class="msg"><?php echo __('Product redirects here.', 'moohpay'); ?></div>');
					};
					var sync = $('#moohpay-sync-link');
					if(!sync.length) {
						sync = $('<div id="moohpay-sync-link"/>');
						$('#moohpay-restrict-container').after(sync);
						sync.on('click', function(event){
							var button = $(event.target).closest('input[type="button"]');
							if(!button.length) return;
							button.prop('disabled', true);
							var productid = select.val();
							moohpay.request.change_redirect(productid, permalink, function(){
								select.find('option[value="'+productid+'"]').attr('data-redirect', permalink);
								valid_msg();
							});
							return false;
						});
					}
					if(redirect && redirect.length>=permalink.length && redirect.substr(0, permalink.length)===permalink) {
						valid_msg();
					}else{
						sync.removeClass('valid').addClass('invalid');
						sync.html('<p class="msg">'+(redirect ? '<?php echo __('Product redirection link differs.', 'moohpay'); ?>' : '<?php echo __('Product redirection link not set.', 'moohpay'); ?>')+'</p><p class="help"><?php echo __('The product must have a redirection link, so that after it is bought the customer is lead to this page.', 'moohpay'); ?><br><?php echo __('We can fix that for you. Just press the button below.', 'moohpay'); ?></p>');
						sync.append('<div><input type="button" class="button button-secondary button-large" value="<?php echo __('Set redirection link to this page', 'moohpay'); ?>"></div>');
					}
				}
			});
<?php if($restrict): ?>
			select.val('<?php echo $restrict; ?>');
			select.trigger('change');
<?php endif; ?>
		}
		$('#moohpay-restrict-container').fadeIn('fast');
	});
});
</script>
<style>
#moohpay-sync-link .msg:before {
	display: inline-block;
	font: 400 20px/1 dashicons;
	speak: none;
	left: -1px;
	padding: 0 5px 0 0;
	position: relative;
	top: 0;
	text-decoration: none!important;
	vertical-align: top;
	-webkit-font-smoothing: antialiased;
	-moz-osx-font-smoothing: grayscale;
}
#moohpay-sync-link.invalid .msg {
	color: #f00;
}
#moohpay-sync-link.valid .msg:before {
	content: '\f147';
}
#moohpay-sync-link.invalid .msg:before {
	content: '\f153';
}
</style>
<?php
		echo $html;
	}
	function moohpay_add_meta_box() {
		add_meta_box('moohpay-meta-box', __('MoohPay™ Premium Page', 'moohpay'), 'moohpay_render_meta_box', 'page', 'side', 'high');
	}
	add_action('add_meta_boxes', 'moohpay_add_meta_box');
	function moohpay_save_meta_box($post_id, $post) {
		if(
			$post->post_type!=='page'
			|| empty($_POST['moohpay-nonce'])
			|| !wp_verify_nonce($_POST['moohpay-nonce'], basename(__FILE__))
			|| !current_user_can('edit_post', $post_id)
			|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		) return;
		$restrict = empty($_POST['moohpay-restrict']) ? null : $_POST['moohpay-restrict'];
		update_post_meta($post_id, 'moohpay-restrict', $restrict);
	}
	add_action('save_post', 'moohpay_save_meta_box', 10, 3);

}else{

	// Front end

	wp_enqueue_style('moohpay-front', plugins_url('/css/moohpay-frontend.css', __FILE__));
	function moohpay_front_scripts() {
		wp_enqueue_script('moohpay-front', MOOHPAY_JS_BRIDGE_FILE, array('jquery'), null, true);
	}
	add_action('wp_enqueue_scripts', 'moohpay_front_scripts');

	function moohpay_page_content_filter($content) {
		$post_id = get_the_ID();
		if(!$post_id || get_post_type($post_id)!=='page') return $content;
		$restrict = get_post_meta($post_id, 'moohpay-restrict', true);
		if(!$restrict || current_user_can('edit_post', $post_id)) return $content;
		$saleskey = null;
		if(!empty($_GET[MOOHPAY_SALESKEY_VAR])) {
			$saleskey = $_GET[MOOHPAY_SALESKEY_VAR];
		}
		if($saleskey && moohpay_api_check_saleskey($restrict, $saleskey)) {
			return $content;
		}
		return '<p class="moohpay-restricted-content"><strong>'.__('This page can only be accessed by customers who bought this product:', 'moohpay').'</strong></p>'.moohpay_shortcode(array('product' => $restrict, 'type' => 'box'));
	}
	add_filter('the_content', 'moohpay_page_content_filter', MOOHPAY_RESTRICTED_PAGE_PRIORITY);
	add_filter('get_the_content', 'moohpay_page_content_filter', MOOHPAY_RESTRICTED_PAGE_PRIORITY);

	function moohpay_page_excerpt_filter($excerpt) {
		$post_id = get_the_ID();
		if(!$post_id || get_post_type($post_id)!=='page') return $excerpt;
		$restrict = get_post_meta($post_id, 'moohpay-restrict', true);
		if(!$restrict) return $excerpt;
		return '<p class="moohpay-restricted-excerpt">'.__('This page can only be accessed by customers.', 'moohpay').'</p>';
	}
	add_filter('get_the_excerpt', 'moohpay_page_excerpt_filter', MOOHPAY_RESTRICTED_PAGE_PRIORITY);

}

class MoohPayIframe {
	public static $count = 0;
}

// [moohpay] shortcode
function moohpay_shortcode($atts) {
	if(!isset($atts['product'])) return '<br><div class="error">[MoohPay Error: No product defined]</div><br>';
	if(!isset($atts['type'])) $atts['type'] = 'box';
	switch($atts['type']) {
		case 'embed':
			MoohPayIframe::$count++;
			return '<p><div class="moohpay-product-embed" data-productid="'.esc_attr($atts['product']).'"><iframe src="'.MOOHPAY_IFRAME_BASE.esc_attr($atts['product']).'/embed?ifr='.MoohPayIframe::$count.'" data-ifr="'.MoohPayIframe::$count.'" scrolling="auto" allowtransparency="true" style="margin:0;padding:0;border:0;width:100%;height:0;"></iframe></div></p>';
		case 'box':
			return '<p><div class="moohpay-product-box" data-productid="'.esc_attr($atts['product']).'"></div></p>';
		case 'simplebox':
			return '<p><div class="moohpay-product-box" data-productid="'.esc_attr($atts['product']).'" data-no-image="1" data-no-description="1"></div></p>';
	}
}
add_shortcode('moohpay', 'moohpay_shortcode');

// Internal API connection
function moohpay_api_talk($params) {
	$moohpay_options = get_option('moohpay_options');
	if(!$moohpay_options || empty($moohpay_options['accesskey'])) return false;
	$params['accesskey'] = $moohpay_options['accesskey'];
	$response = wp_remote_post(MOOHPAY_AJAX_RESPONDER, array(
		'method' => 'POST',
		'timeout' => 10,
		'body' => $params
	));
	if(is_wp_error($response) || !is_array($response)) {
		return null;
	}else{
		return empty($response['body']) ? false : $response['body'];
	}
}
function moohpay_api_check_saleskey($productid, $sk) {
	$response = moohpay_api_talk(array(
		'action' => 'check_saleskey',
		'productid' => $productid,
		'saleskey' => $sk
	));
	return $response==='VALID';
}

// Widget
class MoohPay_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct('moohpay_widget', __('MoohPay™ Product', 'moohpay'), array(
			'classname' => 'moohpay_widget',
			'description' => __('Add a product to your sidebar or in an arbitrary widget area.', 'moohpay'),
		));
	}
	public function widget($args, $instance) {
		echo $args['before_widget'];
		if(!empty($instance['title'])) {
			echo $args['before_title'].apply_filters('widget_title', $instance['title']).$args['after_title'];
		}
		if(!empty($instance['productid'])) {
			echo '<div class="moohpay-product-box" data-productid="'.esc_attr($instance['productid']).'"';
			if(!empty($instance['no_border'])) echo ' data-no-border="1"';
			if(!empty($instance['no_image'])) echo ' data-no-image="1"';
			if(!empty($instance['no_author'])) echo ' data-no-author="1"';
			if(!empty($instance['no_description'])) echo ' data-no-description="1"';
			if(!empty($instance['align'])) echo ' data-align="'.esc_attr($instance['align']).'"';
			echo '></div>';
		}
		echo $args['after_widget'];
	}
	public function form($instance) {
		$moohpay_options = get_option('moohpay_options');
		if(!$moohpay_options || empty($moohpay_options['accesskey'])) {
			?>
			<p>
				<strong><?php _e('Not connected.', 'moohpay'); ?></strong><br>
				<a href="admin.php?page=moohpay"><?php _e('Click here to connect.', 'moohpay'); ?></a>
			</p>
			<?php
			return;
		}
		$response = wp_remote_post(MOOHPAY_AJAX_RESPONDER, array(
			'body' => array(
				'accesskey' => $moohpay_options['accesskey'],
				'action' => 'list_products'
			)
		));
		$products = null;
		if($response && !empty($response['body'])) {
			$products = @json_decode($response['body'], true);
		}
		if($products && is_array($products)):
			$title = !empty($instance['title']) ? $instance['title'] : '';
			$productid = !empty($instance['productid']) ? $instance['productid'] : '';
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php _e( esc_attr( 'Title:' ) ); ?></label><br>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'productid' ) ); ?>"><?php _e( 'Product:', 'moohpay' ); ?></label><br>
				<select id="<?php echo esc_attr( $this->get_field_id( 'productid' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'productid' ) ); ?>">
					<option value=""><?php _e('- Select -', 'moohpay'); ?></option>
					<?php
					foreach($products as $product):
					?>
						<option value="<?php echo esc_attr( $product['productid'] ); ?>"<?php if($productid==$product['productid']) echo ' selected'; ?>><?php echo esc_html( $product['name'] ); ?></option>
					<?php
					endforeach;
					?>
				</select>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'no_border' ) ); ?>"><input id="<?php echo esc_attr( $this->get_field_id( 'no_border' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'no_border' ) ); ?>" type="checkbox" value="1"<?php if(!empty($instance['no_border'])) echo ' checked'; ?>><?php _e( esc_attr( 'No Border' ) ); ?></label>
				&nbsp;
				<label for="<?php echo esc_attr( $this->get_field_id( 'no_image' ) ); ?>"><input id="<?php echo esc_attr( $this->get_field_id( 'no_image' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'no_image' ) ); ?>" type="checkbox" value="1"<?php if(!empty($instance['no_image'])) echo ' checked'; ?>><?php _e( esc_attr( 'No Image' ) ); ?></label>
				&nbsp;
				<label for="<?php echo esc_attr( $this->get_field_id( 'no_author' ) ); ?>"><input id="<?php echo esc_attr( $this->get_field_id( 'no_author' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'no_author' ) ); ?>" type="checkbox" value="1"<?php if(!empty($instance['no_author'])) echo ' checked'; ?>><?php _e( esc_attr( 'No Author' ) ); ?></label>
				&nbsp;
				<label for="<?php echo esc_attr( $this->get_field_id( 'no_description' ) ); ?>"><input id="<?php echo esc_attr( $this->get_field_id( 'no_description' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'no_description' ) ); ?>" type="checkbox" value="1"<?php if(!empty($instance['no_description'])) echo ' checked'; ?>><?php _e( esc_attr( 'No Description' ) ); ?></label>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'align' ) ); ?>"><?php _e( 'Align:', 'moohpay' ); ?></label><br>
				<select id="<?php echo esc_attr( $this->get_field_id( 'align' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'align' ) ); ?>">
					<option value=""><?php _e('Center', 'moohpay'); ?></option>
					<option value="left"<?php if(!empty($instance['align']) && $instance['align']==='left') echo ' selected'; ?>><?php echo _e( 'Left', 'moohpay' ); ?></option>
					<option value="right"<?php if(!empty($instance['align']) && $instance['align']==='right') echo ' selected'; ?>><?php echo _e( 'Right', 'moohpay' ); ?></option>
				</select>
			</p>
			<?php
		else:
			?>
			<p>
				<strong><?php _e('No products found.', 'moohpay'); ?></strong><br>
				<?php echo __('Go to <a href="https://www.moohpay.com/product/new" target="_blank">MoohPay.com</a> to add a product.', 'moohpay'); ?>
			</p>
			<?php
		endif;
	}
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
		$instance['productid'] = (!empty($new_instance['productid'])) ? $new_instance['productid'] : '';
		$instance['no_border'] = (!empty($new_instance['no_border'])) ? $new_instance['no_border'] : '';
		$instance['no_image'] = (!empty($new_instance['no_image'])) ? $new_instance['no_image'] : '';
		$instance['no_author'] = (!empty($new_instance['no_author'])) ? $new_instance['no_author'] : '';
		$instance['no_description'] = (!empty($new_instance['no_description'])) ? $new_instance['no_description'] : '';
		$instance['align'] = (!empty($new_instance['align'])) ? $new_instance['align'] : '';
		return $instance;
	}
}

function moohpay_widgets_init() {
	register_widget('MoohPay_Widget');
}
add_action('widgets_init', 'moohpay_widgets_init');
