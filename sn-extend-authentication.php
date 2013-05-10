<?php
/*
 Plugin Name: SN Extend Authentication
 Description: This plugin allows you to make your WordPress site Posts accessible to logged in users.
 In other words to view your site they have to create / have an account in your site and be logged in.
 Author: Paritosh Gautam
 Version: 1.0
 License: GPLv2
 */
require_once('metabox.class.php'); //Include the Class

// check for uses in WP
if ( ! function_exists( 'add_filter' ) ) {
  echo "Hi there! I'm just a part of plugin, not much I can do when called directly.";
  exit;
}

class SnExtendAuthentication {

  /**
   * Array for pages, there are checked for exclude the redirect
   */
  public static $pagenows = array( 'wp-login.php', 'wp-register.php' );
  /**
   * Constructor, init redirect on defined hooks
   *
   * @since   0.4.0
   * @return  void
   */
  public function __construct($auth_flag) {
    if(isset($auth_flag) && $auth_flag == 1) {
      if ( ! isset( $GLOBALS['pagenow'] ) ||
      ! in_array( $GLOBALS['pagenow'], self :: $pagenows )
      )
      add_action( 'template_redirect', array( __CLASS__, 'redirect' ) );
    }

    if(is_admin()){
      add_action('admin_menu', array($this, 'add_plugin_page'));
    }

    @wp_enqueue_style('authentication-styles', plugins_url( '/css/authentication-styles.css', __FILE__ ));
  }

  /*
   * Get redirect to login-page, if user not logged in blogs of network and single install
   *
   * @since  0.4.2
   * @retur  void
   */
  public function redirect() {
    /**
     * Checks if a user is logged in or has rights on the blog in multisite,
     * if not redirects them to the login page
     */
    
    
      $reauth = ! current_user_can( 'read' ) &&
      function_exists('is_multisite') &&
      is_multisite() ? TRUE : FALSE;
      if ( ! is_user_logged_in() || $reauth ) {
        nocache_headers();
        wp_redirect(
        wp_login_url( $_SERVER[ 'REQUEST_URI' ], $reauth ),
        $status = 302
        );
        exit();
      }
  }

  /**
   * function to add option page for authentication settings
   */
  public function add_plugin_page() {
    // This page will be under "Settings"
    add_options_page('Settings Admin', 'Authentication Settings', 'manage_options', 'authentication-setting-admin', array($this, 'create_admin_page'));
  }

  /**
   * Function to create authentication option form
   */
  public function create_admin_page() {
    $advancedOptions = get_option('authentication_settings');
    if (isset($_POST) && count($_POST)>1 && !isset($advancedOptions)) {
      $this->save_authentication_ext_options();
      unset($_POST);
    }

    if (isset($_POST) && count($_POST)>=1 && isset($advancedOptions)) {
      $this->update_authentication_ext_options();
      unset($_POST);
    }


    ?>
    <?php screen_icon(); ?>
<h2>Authentication Settings</h2>
    <?php
    if (isset($_GET['update']) && $_GET['update'] == 'true') {
      $message = __('Settings Updated Successfully');
    }
    if (isset($message)) {
      ?>
<div class="updated" id="message_auth">
<?php print $message; ?>
</div>
<?php
    }
    if(isset($_SESSION['authentication_message'])) {
      unset($_SESSION['authentication_message']);
    }
    ?>
<div class="wrap_auth">
	<form method="post" name="authentication_settings" action="#">
		<div class='element'>
			<div class='control'>
				<label><input type="checkbox" name="default_auth_mode" value="1"
				<?php print (isset($advancedOptions['default_auth_mode']) && $advancedOptions['default_auth_mode'] == 1) ? ' checked="checked"' : '' ?> />
				<?php print __('Disable Anonymous Site Browsing') ?> </label>
                <p class="description"><?php print __("(Note: Configuration for post/page specific non authenticated users browsing can be turn on/off. Priority will be given to post/page specific authentication setting over default 'Anonymous Site Browsing')"); ?></p>
			</div>
		</div>
		<div class='element'>
			<div class='control'>
				<label><input type="checkbox" name="feed_auth_mode" value="1"
				<?php print (isset($advancedOptions['feed_auth_mode']) && $advancedOptions['feed_auth_mode'] == 1) ? ' checked="checked"' : '' ?> />
				<?php print __('Disable Anonymous Feeds Reading') ?> </label>
			</div>
		</div>
		<div class='element'>
			<div class='control submit'>
				<input type="submit" value="Save Changes"
					class="auth_button auth_button-primary" id="submit" name="submit">
			</div>
		</div>

	</form>
</div>
<?php
  }

  /**
   * Function to save authentication option form
   */
  private function save_authentication_ext_options(){
    $advancedOptions = array(
         'default_auth_mode' => isset($_POST['default_auth_mode']) ? $_POST['default_auth_mode'] : 0,
          'feed_auth_mode' => isset($_POST['feed_auth_mode'])? $_POST['feed_auth_mode'] : 0);

    add_option('authentication_settings', $advancedOptions);
    $this->set_message(array('status' => TRUE, 'message' => __('Settings Added Successfully')));
  }

  /**
   * Function to update authentication option form
   */
  private function update_authentication_ext_options(){
    $advancedOptions = array(
         'default_auth_mode' => isset($_POST['default_auth_mode']) ? $_POST['default_auth_mode'] : 0,
          'feed_auth_mode' => isset($_POST['feed_auth_mode'])? $_POST['feed_auth_mode'] : 0);
    update_option('authentication_settings', $advancedOptions);
    $url_raw = str_replace('&update=true', '', $_SERVER["REQUEST_URI"]);
    $url = isset ($url_raw)? $url_raw : $_SERVER["REQUEST_URI"];
    wp_redirect($url . '&update=true');
  }
} // end class


/**
 * Function to get post id based on url
 */
function custom_get_page_by_path($page_path, $output = OBJECT) {
  global $wpdb;
  $page_path = rawurlencode(urldecode($page_path));
  $page_path = str_replace('%2F', '/', $page_path);
  $page_path = str_replace('%20', ' ', $page_path);
  $parts = explode( '/', trim( $page_path, '/' ) );
  $parts = array_map('esc_sql', $parts);
  $parts = array_map('sanitize_title_for_query', $parts);

  $in_string = "'". implode( "','", $parts ) . "'";
  $pages = $wpdb->get_results( "SELECT ID, post_name, post_parent, post_type FROM $wpdb->posts WHERE post_name IN ($in_string) AND (post_type IN ('page', 'post') OR post_type = 'attachment')", OBJECT_K );
  if(!in_array('archives', $parts)) {
    $revparts = array_reverse( $parts );
    foreach ((array) $pages as $page) {
      if ($page->post_name == $revparts[0]) {
        $count = 0;
        $p = $page;
        while ($p->post_parent != 0 && isset($pages[ $p->post_parent ])) {
          $count++;
          $parent = $pages[ $p->post_parent ];
          if ( ! isset( $revparts[ $count ] ) || $parent->post_name != $revparts[ $count ] )
          break;
          $p = $parent;
        }
      }
    }
  }
  else {
    $revparts = array_reverse( $parts );
    return $revparts[0];
  }
  if ( isset($p) )
  return $p->ID;

  return null;
}

// Create Admin widget for post/page authentication
@$authentic_user_meta = new metabox('authentic_user');
$authentic_user_meta->title = 'Authenticated Users Only';
$authentic_user_meta->html = <<<HEREHTML
	<div class="inside control"><label class="selectit"><input type="checkbox" name="authentic_user_value" id="authentic_user_value" value="1" class="auth_check"/>
	Restrict Post to Authenticated Users.</label></div>
HEREHTML;

/* After declaring your metaboxes, add the two hooks to make it all go! */
add_action('admin_menu', 'create_box');
add_action('save_post', 'save_box');


global $post, $page_id;
// Get authentication option
$advancedOptions = get_option('authentication_settings');
$feed_auth = isset ($advancedOptions['feed_auth_mode']) ? $advancedOptions['feed_auth_mode'] : 0;
$query = explode('=', $_SERVER['QUERY_STRING']);
$feed_url = explode('/', $_SERVER['REQUEST_URI']);

// current url to post id
$url1 = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$post_id = custom_get_page_by_path($url1);

// Condition to authenticate post/page, feed and site
if($query[0] == 'p' || $query[0] == 'page_id' || isset($post_id)) {
  if(!isset($post_id)) {
    $postid = url_to_postid($_SERVER[ 'REQUEST_URI' ]);
  }
  else {
    $postid = $post_id;
  }
  $authentic_user_info = get_post_meta($postid, 'metabox');
  
  if(count($authentic_user_info) == 0) {
    $auth_flag = isset($advancedOptions['default_auth_mode']) ? $advancedOptions['default_auth_mode'] : 0;
    $authenticator = new SnExtendAuthentication($auth_flag);
  }
  else if(count($authentic_user_info) > 0 && $authentic_user_info[0]['authentic_user']['authentic_user_value'] == 1) {
    $auth_flag = 1;
    $authenticator = new SnExtendAuthentication($auth_flag);
  }
}

elseif($query[0] == 'feed' || (isset ($feed_url[2]) && $feed_url[2] == 'feed')) {
  if(isset($feed_auth) && $feed_auth == 1) {
    $auth_flag = 1;
    $authenticator = new SnExtendAuthentication($auth_flag);
  }
}
else { 
    $auth_flag = isset($advancedOptions['default_auth_mode']) ? $advancedOptions['default_auth_mode'] : 0;
    $authenticator = new SnExtendAuthentication($auth_flag);
}
