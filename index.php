<?php
/*
Plugin Name: Facebook Page Poster
Plugin URI: http://www.mwordpress.net
Description: publish your post to facebook page when you publish or update your post
Version: 0.1
Author: Mouad Achemli
Author URI: http://www.mwordpress.net
License: GPLv2 or later
*/


/*
facebook Page Poster Plugin based on this sources :
facebook page poster : https://github.com/keeganstreet/facebook-page-poster
settings api : http://alisothegeek.com/2011/01/wordpress-settings-api-tutorial-1/
*/

define( 'FBPPPlUGIN_PATH', plugin_dir_path(__FILE__) );

class FBPP_Plugin_Options {
	
	private $sections;
	private $checkboxes;
	private $settings;
	/**
	 * Construct
	 *
	 * @since 1.0
	 */
	public function __construct() {
		// This will keep track of the checkbox options for the validate_settings function.
		$this->checkboxes = array();
		$this->settings = array();
		$this->get_settings();
		$this->sections['general']      = __( '' );
		add_action( 'admin_menu', array( &$this, 'add_pages' ) );
		add_action( 'admin_menu', array( &$this, 'add_pages_ex' ) );
		add_action( 'admin_init', array( &$this, 'register_settings' ) );
		add_action('wp_head', 'fpp_head_action');
		if ( ! get_option( 'fbpp_options' ) )
		$this->initialize_settings();
	}
	/**
	 * Add options page
	 *
	 * @since 1.0
	 */
	public function add_pages() {
		$admin_page = add_plugins_page( __( 'Facebook Page Poster' ), __( 'Facebook Page Poster' ), 'manage_options', 'fbpp-options', array( &$this, 'display_page' ) );
	}
	public function add_pages_ex() {
		$access_token = add_plugins_page( __( 'Facebook Access Token' ), __( 'Facebook Access Token' ), 'manage_options', 'facebook-access-token', array( &$this, 'fb_atoken' ) );
	}
	/**
	 * Create settings field
	 *
	 * @since 1.0
	 */
	public function create_setting( $args = array() ) {
		$defaults = array(
			'id'      => 'default_field',
			'title'   => __( 'Default Field' ),
			'desc'    => __( 'This is a default description.' ),
			'std'     => '',
			'type'    => 'text',
			'section' => 'general',
			'choices' => array(),
			'class'   => ''
		);	
		extract( wp_parse_args( $args, $defaults ) );
		$field_args = array(
			'type'      => $type,
			'id'        => $id,
			'desc'      => $desc,
			'std'       => $std,
			'choices'   => $choices,
			'label_for' => $id,
			'class'     => $class
		);
		if ( $type == 'checkbox' )
			$this->checkboxes[] = $id;
		add_settings_field( $id, $title, array( $this, 'display_setting' ), 'fbpp-options', $section, $field_args );
	}
	
	public function fb_atoken() {
			
		echo '<div class="wrap">
			<div class="icon32" id="icon-options-general"></div>
			<h2>' . __( 'Facebook Access Token' ) . '</h2>';
			
		$app_id = fbpp_option('facebook_app_id');
		$app_secret = fbpp_option('facebook_app_secret');
		$app_page = fbpp_option('facebook_page_id');
			
		require_once 'includes/facebook.php';
			
		$facebook = new Facebook(array(
			'appId'  => $app_id,
			'secret' => $app_secret,
		));
			
		// Try to get User ID
		$user = $facebook->getUser();
			
		// If a user is not logged in, redirect to Facebook Auth page
		if (empty($user)) {
			$loginUrl = $facebook->getLoginUrl(array(
				'scope' => 'manage_pages, publish_stream'
			));
			die("<script type='text/javascript'>top.location.href = '" . $loginUrl. "';</script>");
			exit;
		}
		// Retrieve the access_token for the Page
		try {
			$result = $facebook->api('/' . fbpp_option('facebook_page_id') . '?fields=access_token');
		} catch (FacebookApiException $e) {
			echo '<pre>' . print_r($e, 1) . '</pre>';
		}
		
		if (empty($result)) {
			echo '<h1>We were not able to retrieve the Page access token.</h1>';
			echo '<p>Are you sure this is the correct Page ID? <b>' . fbpp_option('facebook_page_id') . '</b></p>';
			echo '<p>Are you sure you are an administrator for this Page?</p>';
		} else {
			echo '<br><br><br><h1>The access token for Page ' . fbpp_option('facebook_page_id') . ' is:</h1>';
			echo '<textarea rows="10" cols="90">' . $result['access_token'] . '</textarea>';
			echo '<p>copy this access token and past in text field of Facebook Page ACCESS_TOKEN in page options of Facebook Page Poster</p>';
		}
		echo '</div>';
	}
	
	/**
	 * Display options page
	 *
	 * @since 1.0
	 */
	public function display_page() {
		
		echo '<div class="wrap">';
		
		echo '<div class="icon32" id="icon-options-general"></div>';
		echo '<h2>' . __( 'Facebook Page Poster' ) . '</h2><br><br>';
		
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true )
		
		echo '<div class="updated fade"><p>' . __( 'Facebook Page Poster updated.' ) . '</p></div>';
		echo '<form action="options.php" method="post">';
			
			settings_fields( 'fbpp_options' );
			do_settings_sections( $_GET['page'] );
			
		echo '</div>';
		
		echo '<p class="submit">';
		echo '<input name="Submit" type="submit" class="button-primary" value="' . __( 'Save Changes' ) . '" />';
		echo '</p>';
		
		echo '</form>';
	
		echo '<script type="text/javascript">
				jQuery(document).ready(function($) {
				var sections = [];';
				foreach ( $this->sections as $section_slug => $section )
				echo "sections['$section'] = '$section_slug';";
		echo 'var wrapped = $(".wrap h3").wrap("<div class=\"ui-tabs-panel\">");
				wrapped.each(function() {
					$(this).parent().append($(this).parent().nextUntil("div.ui-tabs-panel"));
				});
				$(".ui-tabs-panel").each(function(index) {
					$(this).attr("id", sections[$(this).children("h3").text()]);
					if (index > 0)
						$(this).addClass("ui-tabs-hide");
				});
				$(".ui-tabs").tabs({
					fx: { opacity: "toggle", duration: "fast" }
				});
				
				$("input[type=text], textarea").each(function() {
					if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "")
						$(this).css("color", "#999");
				});
				
				$("input[type=text], textarea").focus(function() {
					if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "") {
						$(this).val("");
						$(this).css("color", "#000");
					}
				}).blur(function() {
					if ($(this).val() == "" || $(this).val() == $(this).attr("placeholder")) {
						$(this).val($(this).attr("placeholder"));
						$(this).css("color", "#999");
					}
				});
				
				$(".wrap h3, .wrap table").show();
				
				// This will make the "warning" checkbox class really stand out when checked.
				// I use it here for the Reset checkbox.
				$(".warning").change(function() {
					if ($(this).is(":checked"))
						$(this).parent().css("background", "#c00").css("color", "#fff").css("fontWeight", "bold");
					else
						$(this).parent().css("background", "none").css("color", "inherit").css("fontWeight", "normal");
				});
				// Browser compatibility
				if ($.browser.mozilla) 
						 $("form").attr("autocomplete", "off");
			});
			</script>
		</div>';
	}
	/**
	 * Description for section
	 *
	 * @since 1.0
	 */
	public function display_section() {}
	/**
	 * Description for About section
	 *
	 * @since 1.0
	 */
	public function display_about_section() {}
	/**
	 * HTML output for text field
	 *
	 * @since 1.0
	 */
	public function display_setting( $args = array() ) {
		
		extract( $args );
		$options = get_option( 'fbpp_options' );
		if ( ! isset( $options[$id] ) && $type != 'checkbox' )
			$options[$id] = $std;
		elseif ( ! isset( $options[$id] ) )
			$options[$id] = 0;
		
		$field_class = '';
		if ( $class != '' )
			$field_class = ' ' . $class;
		
		switch ( $type ) {
			
			case 'heading':
				echo '</td></tr><tr valign="top"><td colspan="2"><h4>' . $desc . '</h4>';
				break;
			
			case 'checkbox':
				
				echo '<input class="checkbox' . $field_class . '" type="checkbox" id="' . $id . '" name="fbpp_options[' . $id . ']" value="1" ' . checked( $options[$id], 1, false ) . ' /> <label for="' . $id . '">' . $desc . '</label>';
				
				break;
			
			case 'select':
				echo '<select class="select' . $field_class . '" name="fbpp_options[' . $id . ']">';
				
				foreach ( $choices as $value => $label )
					echo '<option value="' . esc_attr( $value ) . '"' . selected( $options[$id], $value, false ) . '>' . $label . '</option>';
				
				echo '</select>';
				
				if ( $desc != '' )
					echo '<br /><span class="description">' . $desc . '</span>';
				
				break;
			
			case 'radio':
				$i = 0;
				foreach ( $choices as $value => $label ) {
					echo '<input class="radio' . $field_class . '" type="radio" name="fbpp_options[' . $id . ']" id="' . $id . $i . '" value="' . esc_attr( $value ) . '" ' . checked( $options[$id], $value, false ) . '> <label for="' . $id . $i . '">' . $label . '</label>';
					if ( $i < count( $options ) - 1 )
						echo '<br />';
					$i++;
				}
				
				if ( $desc != '' )
					echo '<br /><span class="description">' . $desc . '</span>';
				
				break;
			
			case 'textarea':
				echo '<textarea class="' . $field_class . '" id="' . $id . '" name="fbpp_options[' . $id . ']" placeholder="' . $std . '" rows="5" cols="30">' . wp_htmledit_pre( $options[$id] ) . '</textarea>';
				
				if ( $desc != '' )
					echo '<br /><span class="description">' . $desc . '</span>';
				
				break;
			
			case 'password':
				echo '<input class="regular-text' . $field_class . '" type="password" id="' . $id . '" name="fbpp_options[' . $id . ']" value="' . esc_attr( $options[$id] ) . '" />';
				
				if ( $desc != '' )
					echo '<br /><span class="description">' . $desc . '</span>';
				
				break;
			
			case 'text':
			default:
		 		echo '<input class="regular-text' . $field_class . '" type="text" id="' . $id . '" name="fbpp_options[' . $id . ']" placeholder="' . $std . '" value="' . esc_attr( $options[$id] ) . '" />';
		 		
		 		if ( $desc != '' )
		 			echo '<br /><span class="description">' . $desc . '</span>';
		 		
		 		break;
			
				
		}
	}
	/**
	 * Settings and defaults
	 * 
	 * @since 1.0
	 */
	public function get_settings() {
		$this->settings['facebook_app_id'] = array(
			'title'   => __( 'Facebook APP ID' ),
			'desc'    => __( 'see this video to get app id' ),
			'std'     => '',
			'type'    => 'text',
			'section' => 'general'
		);
		$this->settings['facebook_app_secret'] = array(
			'title'   => __( 'Facebook APP SECRET' ),
			'desc'    => __( 'see this video to get app secret' ),
			'std'     => '',
			'type'    => 'text',
			'section' => 'general'
		);
		
		$this->settings['facebook_page_id'] = array(
			'title'   => __( 'Facebook PAGE ID' ),
			'desc'    => __( 'you can get Page ID from <br /> http://graph.facebook.com/PageName <br /> change PageName Like Mwordpress ' ),
			'std'     => '',
			'type'    => 'text',
			'section' => 'general'
		);
		
		$this->settings['facebook_access_token'] = array(
			'title'   => __( 'Facebook Page ACCESS_TOKEN' ),
			'desc'    => __( 'To find this access token, visit <a href="'.get_bloginfo('url').'/wp-admin/plugins.php?page=facebook-access-token" target="_blank" >Facebook Access Token</a> and grant the extended permissions.' ),
			'std'     => '',
			'type'    => 'text',
			'section' => 'general'
		);
		
		$this->settings['reset_fbpp_plugin'] = array(
			'section' => 'general',
			'title'   => __( 'Reset Plugin' ),
			'type'    => 'checkbox',
			'std'     => 0,
			'class'   => 'warning', // Custom class for CSS
			'desc'    => __( 'Check this box and click "Save Changes" below to reset plugin options to their defaults.' )
		);
	}
	/**
	 * Initialize settings to their default values
	 * 
	 * @since 1.0
	 */
	public function initialize_settings() {
		
		$default_settings = array();
		foreach ( $this->settings as $id => $setting ) {
			if ( $setting['type'] != 'heading' )
				$default_settings[$id] = $setting['std'];
		}
		update_option( 'fbpp_options', $default_settings );
	}
	/**
	* Register settings
	*
	* @since 1.0
	*/
	public function register_settings() {
		register_setting( 'fbpp_options', 'fbpp_options', array ( &$this, 'validate_settings' ) );
		foreach ( $this->sections as $slug => $title ) {
			add_settings_section( $slug, $title, array( &$this, 'display_section' ), 'fbpp-options' );
		}
		$this->get_settings();
		foreach ( $this->settings as $id => $setting ) {
			$setting['id'] = $id;
			$this->create_setting( $setting );
		}
	}
	/**
	* Validate settings
	*
	* @since 1.0
	*/
	public function validate_settings( $input ) {
		if ( ! isset( $input['reset_fbpp_plugin'] ) ) {
			$options = get_option( 'fbpp_options' );
			foreach ( $this->checkboxes as $id ) {
				if ( isset( $options[$id] ) && ! isset( $input[$id] ) )
					unset( $options[$id] );
			}
			return $input;
		}
		return false;
	}
}

	/**
	* Facebook remove post
	* this function for remove post if change post status from publish to draft
	* @since 1.0
	*/
	function fb_remove_post() {
		
		global $post;
		
		require_once 'includes/facebook.php';
		$facebook = new Facebook(array(
			'appId'  => fbpp_option('facebook_app_id'),
			'secret' => fbpp_option('facebook_app_secret'),
			
		));
		
		$title = get_the_title($post->ID);
		$description = get_the_excerpt($post->ID);
		$linkr = get_permalink($post->ID);
		$thePostID = $post->ID;
		
		$pok = get_post_meta( $thePostID, "facebook_post_id", $single = true );
		$pon = get_post_meta( $thePostID, "facebook_publish", $single = true );
		
		try {
		
		if (!empty($pok)) {
		$go_to_delete = $facebook->api('/'. $pok . '/', 'delete' );
		}
		
		} catch (FacebookApiException $e) {
				 // error_log($e);
		}
		delete_post_meta( $thePostID, 'facebook_post_id', $pok );
		delete_post_meta( $thePostID, 'facebook_publish', $pon );
				
	}
	
	add_action('draft_to_publish','check_draft_publish');
	function check_draft_publish(){
		fb_remove_post();
	}

	add_action( 'transition_post_status', 'check_transition_post', 10, 3 );
	function check_transition_post( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status or 'publish' === $old_status ) {
			fb_remove_post(); 
		}
	}	 

	/**
	* Facebook Publisher
	* 
	* @since 1.0
	*/
	add_action('publish_post','publish_post_facebook');
	function publish_post_facebook() {
		global $post;
		require_once 'includes/facebook.php';
		$facebook = new Facebook(array(
			'appId'  => fbpp_option('facebook_app_id'),
			'secret' => fbpp_option('facebook_app_secret'),
		));
		
		$title = get_the_title($post->ID);
		$description = get_the_excerpt($post->ID);
		$linkr = get_permalink($post->ID);
		$thePostID = $post->ID;
		 
		$facebook_data = $facebook->api('/' . fbpp_option('facebook_page_id') . '/posts/?fields=link', 'get' );
		
		$find = $facebook_data['data'];
		
		$found = false;
		
		foreach ($find as $data) {
			
			if ($data['link'] == $linkr) {
				$found = true;
				break; // no need to loop anymore, as we have found the item => exit the loop
			}
			
			/*
			* Get Duplicate Post Id in Facebook Page
			* many thanks manoj-admlab
			* code by manoj-admlab
			* http://stackoverflow.com/users/2502457/manoj-admlab
			*/
			$duplicate_ids = array();
			$all_links = array();
			
			$data_count = count($facebook_data['data']);
			for ($i = 0; $i < $data_count; $i++) {
				if (in_array($facebook_data['data'][$i]['link'], $all_links)) {
					$duplicate_ids[] = $facebook_data['data'][$i]['id'];
					unset($facebook_data['data'][$i]);
				} else {
					$all_links[] = $facebook_data['data'][$i]['link'];
				}
			}
			/*
			* End code by manoj-admlab
			*/
			
			foreach ($duplicate_ids as $post_arr => $postid_delete) {
				
				if (!empty($postid_delete)) {
				$delete = $facebook->api('/'. $postid_delete . '/', 'delete' );
				delete_post_meta( $thePostID, 'facebook_post_id', $postid_delete, true );
				update_post_meta( $thePostID, 'facebook_publish', 'off', true );
				}
				
			}
		}
		if ($found === false) {
			try { 
				$wallPostParams = array(
					'caption'      => '',
					'description'  => $description,
					'link'         => $linkr,
					'name'         => html_entity_decode($title , ENT_QUOTES, "UTF-8"),
					'picture'      => '',
					'access_token' => fbpp_option('facebook_access_token')
				);
				$wallPost = $facebook->api('/' . fbpp_option('facebook_page_id') . '/feed', 'POST', $wallPostParams);
				
				$catch_post_id_facebook = $wallPost['id'];
				
				add_post_meta( $thePostID, 'facebook_post_id', $catch_post_id_facebook, true );
				add_post_meta( $thePostID, 'facebook_publish', 'on', true );
				
			} catch (FacebookApiException $e) {
				 //error_log($e);
			}
		}
	}

	function check_after_publish($post_id){
		$metapost = get_post_meta( get_the_ID(), 'facebook_publish', true );
			if(!empty( $metapost ) ) {
			add_action('save_post','check_save_post');
			}
		}
	
	function check_save_post($post_id){
		global $flag;
		if($flag == 0){
			publish_post_facebook($post_id);
		}
		$flag = 1;
	}
	/**
		* Called on html head rendering. Prints meta tags to make posts appear
		* correctly in Facebook. 
		* this code is from plugin facebook page publish
	*/
	function fpp_head_action() {
        global $post;
		
        if (is_object($post) /*&& ($post->post_type == 'post') */ && is_singular()) {
			fpp_render_meta_tags($post);
		}
	}
	/**
		* Render Facebook recognized meta tags (Open Graph protocol).
		* Facebooks uses them to refine shared links for example.
		* this code is from plugin facebook page publish
		* 
	*/
	function fpp_render_meta_tags($post) {
		
		echo '<meta property="og:type" content="article"/>'; // Required by FB
		echo '<meta property="og:url" content="'.esc_attr(get_permalink($post->ID)).'"/>'; // Required by FB
		echo '<meta property="og:title" content="'.esc_attr(apply_filters('the_title', $post->post_title), ENT_COMPAT, 'UTF-8')/*, ENT_COMPAT, 'UTF-8')*/.'"/>';
		echo '<meta property="og:description" content="'.esc_attr(get_the_excerpt($post->ID)).'"/>';
		
		if (has_post_thumbnail()) {
		$thumb = wp_get_attachment_image_src(get_post_thumbnail_id(), 'full');
		} 
		echo '<meta property="og:image" content="'.esc_attr($thumb[0]).'"/>';
	}
	
$theme_options = new FBPP_Plugin_Options();
function fbpp_option( $option ) {
	$options = get_option( 'fbpp_options' );
	if ( isset( $options[$option] ) )
		return $options[$option];
	else
		return false;
}
?>