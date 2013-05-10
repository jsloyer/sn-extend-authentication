<?php
$boxes = array();
$data = array();
class metabox {
	function __construct($key) { //Default properties for metabox object.
        global $boxes;
        global $wpdb;
        $query = $wpdb->get_results("SELECT post_type FROM " . $wpdb->prefix . "posts  WHERE post_type<>'revision' AND post_type<>'comment' GROUP BY post_type");
		foreach ($query as $type) {
          $post_type[] = $type->post_type;
        }
		$this->key = $key;
		$this->id = $key;
		$this->title = ucfirst($this->key) . ' Title';
		$this->page = $post_type;
		$this->context = 'normal';
		$this->priority = 'high';
		$this->html = '';
		$this->names = '';
		$this->categories = 'all';
		$this->is_single_value = false; //Should the data be saved as an array (less database clutter) or a single text value (good for sorting on get_posts) 
		$this->metabox_key = 'metabox';		
		array_push($boxes, $this); //Add this object to a $boxes array which we will loop over later when rendering the boxes.
    }
	
	function extract_field_names() { //Simple RegEx to search through the HTML of each metabox and pull out the value of each field name to be stored in an array.
		//$pattern = '/name="(?<name>\w+)"/'; This worked with older PHP 5 but not PHP 6.
		$pattern = '/name="(?P<name>\w+)"/'; //This works with newer versions of PHP
		preg_match_all($pattern, $this->html, $matches);
		$this->names = $matches['name'];
	}
	
	function display_box($box) { //Renders the HTML for the metabox and creates a wpnonce.
		global $post; 
	?>
	<div class="form-wrap">
    <?php
	wp_nonce_field(plugin_basename( __FILE__ ), $this->key . '_wpnonce', false, true);
	
	if( $this->is_single_value ) {
		$this->metabox_key = $this->key;
	}
	$data = get_post_meta($post->ID, $this->metabox_key, true);
	if (is_string($data)) {
		//$data = unserialize($data);
	}
	
	if( !is_array($data) ) {
		$data = array(
			$this->metabox_key => $data
		);
	} else {
		$data = $data[$this->key];
	}	

	require_once('simple_html_dom.php');
	$html = new simple_html_dom();
	$html->load($this->html);
	if($data) {
		foreach($data as $key => $value) {
              foreach ($html->find('[name='.$key.']') as $e) {
                  $tag = $e->tag;

                  switch($tag) {
                      case 'input':
                          if($e->type == 'text') {
                              $e->value = $value;
                          }
                          if($e->type == 'checkbox' || $e->type == 'radio') {
                              if($e->checked == $value) {
                                  $e->checked = null;
                              }
                              else {
                                  $e->checked = $value;
                              }
                          }
                          break;
                      case 'textarea':
                          $e->innertext = $value;
                          break;
                      case 'select':
                          $e->find('option[value='.$value.']',0)->selected = 'selected';
                          break;
                  }
              }
		}
        
		$this->html = $html;//Save our HTML modified with previously saved values back into the Object.
	}
	?>
  		<div>
			<?php echo $this->html; ?>
  		</div>
	</div>
<?php
	}
}

function create_box() {
	global $boxes;
	foreach ($boxes as $box) {
		if( function_exists('add_meta_box') ) {
			$callback = array($box, 'display_box');
			$box->extract_field_names();
			if (is_array($box->page)) {
				foreach($box->page as $page) {
					add_meta_box($box->id, $box->title, $callback, $page, $box->context, $box->priority);
				}
			} else {
				add_meta_box($box->id, $box->title, $callback, $box->page, $box->context, $box->priority);
			}
		}
	}
}

function save_box($post_id) {
	global $post, $boxes;
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
    	return $post_id;
	}
	if( defined('DOING_AJAX') && DOING_AJAX ) {
		return $post_id;
	}
	if( ereg('/\edit\.php', $_SERVER['REQUEST_URI']) ) { //Detects if the save action is coming from a quick edit/batch edit.
		return $post_id;
	}
	if($boxes) {
		$data = array();
		foreach ($boxes as $box) {
			if($box->names) { //This if statement prevents an error when doing a quick edit in the admin area.
				foreach ($box->names as $name) {
					$continue = false;

					//Makes sure we're looking at boxes for the current post_type and not all of the boxes we have defined.
					if( is_array( $box->page ) && isset($_POST['post_type']) && in_array( $_POST['post_type'], $box->page ) ) {
						$continue = true;
					} else if(isset($_POST['post_type']) && $_POST['post_type'] == $box->page) {
						$continue = true;
					}
					
					if($continue) { 
						if ( !wp_verify_nonce($_POST[ $box->key . '_wpnonce'], plugin_basename(__FILE__))) {
							return $post_id;
						}
						if ( !current_user_can( 'edit_post', $post_id )) {
							return $post_id;
						}
					
					
						if($box->is_single_value) {
							$data[$box->key] = $_POST[$name];
							//update_post_meta( $post_id, $box->metabox_key, $_POST[$name] );
						} else {
							$data[$box->metabox_key][$box->key][$name] = $_POST[$name];
						}
					}
				}
			}
		}
		foreach( $data as $metabox_key => $val ) {
			update_post_meta( $post_id, $metabox_key, $val );
		}
	}
}
function metabox_show_hide() { //JavaScript to make Metaboxes show and hide based on selected categories. 
		global $boxes;
		$category_js;
		$boxnames;
		foreach ($boxes as $box) {
			if ($box->categories == 'all') {
			
			}
			else {
				$id = $box->id;
				$categories = $box->categories;
				$boxnames.= $id . ', ';
				$category_js.= <<<HEREHTML
					var $id = new Metabox();
					$id.id = '$id';
					$id.categories.allowed = [$categories];
					$id.categories.checked = new Array();
HEREHTML;
			}
		}
		$boxnames = preg_replace('/[,]\s$/iD', '', $boxnames);
		$category_js.= 'metaboxes = [' . $boxnames . ']';
		$javascript = <<<HEREHTML
			<script type="text/javascript">
            var metaboxes;
			var Metabox = function() {
					this.id;
					this.categories = function() {
						this.allowed;
						this.checked;
					}
					this.on = function() {
						var box = '#'+this.id;
						jQuery(box).fadeIn('slow');

					}
					this.off = function() {
						var box = '#'+this.id;
						jQuery(box).fadeOut('slow');
					}
				} 
			jQuery(document).ready(function() {
			
				$category_js;
				
				var prechecked = jQuery('#categorychecklist input:checked').map(function() { return Number(this.value) });
				
				for (i=0; i<metaboxes.length; i++) {
					metaboxes[i].off();
					var allowed = metaboxes[i].categories.allowed;
					var checked = metaboxes[i].categories.checked;
					for (j=0; j<allowed.length; j++) {
						if (jQuery.inArray( allowed[j], prechecked) > -1) {
							metaboxes[i].on();
						}
					}
				}
				
				jQuery('#categorychecklist input[type="checkbox"]').change(function(){
					if(jQuery(this).is(':checked')) {
						for (i=0; i<metaboxes.length; i++) {
							var val = Number(this.value);
							var allowed = jQuery.inArray( val, metaboxes[i].categories.allowed );
							var checked = jQuery.inArray( val, metaboxes[i].categories.checked );
							if (allowed > -1 && checked < 0) {
								metaboxes[i].categories.checked.push(val);
								metaboxes[i].on();
							}
						}
					}
					else if(!jQuery(this).is(':checked')) {
						for (i=0; i<metaboxes.length; i++) {
							var val = Number(this.value);
							var checked = metaboxes[i].categories.checked;
							var checkedString = checked.join(''); //Convert the array to a string for IE so we can better parse an array.
							
							if (checkedString.indexOf(val) > -1) {
								checked.splice(checkedString.indexOf(val),1);
							}
							if (checked.length < 1) {
								metaboxes[i].off();
							}
						}
					}
				});
			});
            </script>
HEREHTML;
		echo $javascript;
	}
//	add_action('admin_footer-post.php', 'metabox_show_hide');
//	add_action('admin_init', 'load_jquery');
    
function load_jquery() {
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-core');
}
	
?>