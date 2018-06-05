<?php 
/**
 * Plugin Name: Twitter import
 * Plugin URI: http://www.igorkiselev.com/wp-plugins/import_twitter
 * Description: Plug-in imports images from Twitter
 * Version: 0.0.1
 * Author: Igor Kiselev
 * Author URI: http://www.igorkiselev.com/
 * Copyright: Igor Kiselev
 * License: A "JustBeNice" license name e.g. GPL2.
 */


if( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', function(){
	
	add_action('admin_init', function () {
		register_setting('libraries-twitter-import', 'libraries-twitter-import');
		register_setting('libraries-twitter-import', 'libraries-twitter-import-account');
	});
	
	
	
	class twitterImport {
		
		private $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
		
		
		private function returnResponce($options = '', $account = ''){
			
			require_once('TwitterAPIExchange.php');

			$requestMethod = 'GET';

			$twitter = new TwitterAPIExchange($options);

			$response = $twitter->setGetfield($account)->buildOauth($this->url, $requestMethod)->performRequest();

			return $response;
			
		}
		
		private function insertPost($item){
			global $wpdb;
			
			$regex = "@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?)?)@";
			
			$check = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key = 'twitter_ID' AND  meta_value = $item->id LIMIT 1");
			
			if(!$check){
				
				$ID = wp_insert_post(
					array(
						'post_type' => 'post',
						'post_title'    => preg_replace($regex, ' ', $item->text),
						'post_content'   => preg_replace($regex, '<a href="$0" target="_blank">$0</a>', $item->text),
						'post_status'   => 'publish',
						'post_author'   => 1,
						'post_date' => date('Y-m-d h:i:s', strtotime($item->created_at))
					)
				);
			
				set_post_format( $ID , 'status' );
			
				add_post_meta( $ID, 'twitter_ID', $item->id, true );
				
			}
		}
		
		public function updateResponce($options = '', $account = ''){
			
			$array = json_decode($this->returnResponce($options, $account));
			
			foreach ($array as &$value) {
				$this->insertPost($value);
			}
			
		}
		
	}
	
	
	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), function($links){
		
		return array_merge( $links, array('<a href="' . admin_url( 'options-general.php?page=libraries-twitter-import' ) . '">'.__('Settings','libraries-twitter-import').'</a>',) );
		
	});

	add_action('admin_menu', function () {
		add_options_page( __('Twitter','libraries-twitter-import'), __('Twitter','libraries-twitter-import'), 'manage_options', 'libraries-twitter-import', function(){
		
		(!current_user_can('manage_options')) ? wp_die( __('You do not have sufficient permissions to access this page.','libraries-twitter-import') ) : false;
		
		$settings = get_option( 'libraries-twitter-import' );
		
		?>
		<div class="wrap">
			
			<h1><?php _e('Twitter import plugin', 'libraries-twitter-import'); ?></h1>

			<form method="post" action="options.php">
				
				<?php settings_fields('libraries-twitter-import'); ?>
			
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php _e('oauth_access_token', 'libraries-twitter-import'); ?>
						</th>
						<td>
							<input type="text" class="large-text code" name="libraries-twitter-import[oauth_access_token]" value="<?php echo esc_attr( $settings['oauth_access_token'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php _e('oauth_access_token_secret', 'libraries-twitter-import'); ?>
						</th>
						<td>
							<input type="text" class="large-text code" name="libraries-twitter-import[oauth_access_token_secret]" value="<?php echo esc_attr( $settings['oauth_access_token_secret'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php _e('consumer_key', 'libraries-twitter-import'); ?>
						</th>
						<td>
							<input type="text" class="large-text code" name="libraries-twitter-import[consumer_key]" value="<?php echo esc_attr( $settings['consumer_key'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php _e('consumer_secret', 'libraries-twitter-import'); ?>
						</th>
						<td>
							<input type="text" class="large-text code" name="libraries-twitter-import[consumer_secret]" value="<?php echo esc_attr( $settings['consumer_secret'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php _e('account name', 'libraries-twitter-import'); ?>
						</th>
						<td>
							<input type="text" class="regular-text code" name="libraries-twitter-import-account" value="<?php echo esc_attr( get_option('libraries-twitter-import-account') ); ?>" />
						</td>
					</tr>
				</table>
			
				<?php do_settings_sections('libraries-twitter-import'); ?>
				
				<?php submit_button(); ?>

				<p>
					<?php _e('Plugin to make work easier. Developed by Igor Kiselev in <a href="//www.justbenice.ru/">Just Be Nice</a>', 'libraries-twitter-import'); ?>
				</p>
		
			</form>
		
		</div>
		
		<?php
		
		});
	});
	
	
	register_activation_hook(__FILE__, function(){
		( !wp_next_scheduled( 'update_twitter_feed' ) ) ? wp_schedule_event( time(), 'hourly', 'update_twitter_feed' ) : false;
	});
	
	function update_twitter_feed_function(){
		$settings = get_option( 'libraries-twitter-import' );
		$account = get_option( 'libraries-twitter-import-account' );
		
		if($settings){
			$twitterImport = new twitterImport();
			$twitterImport->updateResponce($settings,'?screen_name='.$account);
		}
	}
	
	add_action('update_twitter_feed', 'update_twitter_feed_function');
	
	register_deactivation_hook (__FILE__, function(){
		
		wp_unschedule_event(
			wp_next_scheduled('update_twitter_feed'), 'update_twitter_feed');
		
	});
	
});





?>