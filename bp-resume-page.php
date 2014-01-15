<?php
/**
 * Plugin Name: BP Resume Page
 * Plugin URI:  http://scenicjobs.com/wordpress-plugins
 * Description: Adds a Resume page to Buddypress profile and Nav menu. Add Education, Profession Experience, and Skills into your Profile Resume.
 * Author:      ScenicJobs.com
 * Version:     1.0
 * Author URI:  http://scenicjobs.com/wordpress-plugins
 */

define( BPRP_ROOT_PATH, plugin_dir_path(__FILE__));


/**
 * 多语言加载
 */
function myplugin_init() {
    load_plugin_textdomain( 'buddypress', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action('bp_include', 'myplugin_init');


//load_plugin_textdomain( 'buddypress', false,  'bp-resume-page/languages' );

class BP_Resume_Page {
	
	var $component_name = 'Resume';
	var $component_slug = 'resume';
	var $user_id = 0;
	var $messages = array();
	
	public function __construct() {
		add_action('bp_init', array($this,'init'));
        add_action( 'bp_setup_admin_bar', array($this,'bp_resume_setup_admin_bar') );
	}
	
	function init() {
		global $bp,$current_user;
		$this->user_id = $bp->displayed_user->id;
		
		if( $bp->current_component == $this->component_slug && !empty($_REQUEST) ) {
			$this->process_request();
		}
		
		//Add Top level menu in user profile
		bp_core_new_nav_item(
			array(
				'name' => __( $this->component_name , 'buddypress'),
				'slug' => $this->component_slug,
				'position' => 90,
				'show_for_displayed_user' => true,
				'screen_function' => array($this, 'resume_page'),
				'default_subnav_slug' => 'view'
		));
		
		
		$parent_url = trailingslashit( $bp->displayed_user->domain . $this->component_slug );
		
		$main_page = array(
			'name'            => __('Resume','buddypress'),
			'slug'            => 'view',
			'parent_url'      => $parent_url,
			'parent_slug'     => $this->component_slug,
			'screen_function' => array($this, 'resume_page'),
			'position'        => 10,
			'user_has_access' => 'all'
		);
		bp_core_new_subnav_item($main_page);
		
		$edit_page = array(
			'name'            => __('Add','buddypress'),
			'slug'            => 'add',
			'parent_url'      => $parent_url,
			'parent_slug'     => $this->component_slug,
			'screen_function' => array($this, 'resume_page'),
			'position'        => 20,
			'user_has_access' => ( bp_is_my_profile() || current_user_can('administrator') )
		);
		bp_core_new_subnav_item($edit_page);
		
		add_action('bp_template_content', array($this, 'template_content'));

	}


    /**
     * bp_resume_setup_admin_bar()
     * 添加顶部导航栏快捷导航
     * sets adminbar
     */
    function bp_resume_setup_admin_bar(){
        global $wp_admin_bar, $bp;
        if ( is_user_logged_in() ) {
            $user_domain = bp_loggedin_user_domain();

            $parent_url = trailingslashit( $user_domain . $this->component_slug );

            
            $wp_admin_bar->add_menu( array(
                'parent'  => $bp->my_account_menu_id,
                'id'      => $this->component_slug,
                'title'   => __( $this->component_name , 'buddypress'),
                'href'    => $parent_url,
            ) );

            $wp_admin_bar->add_menu( array(
                    'parent'  => $this->component_slug,
                    'id'      => 'my-bp-resume',
                    'title'   => __('Resume','buddypress'),
                    'href'    => $parent_url,
                )
            );

            $wp_admin_bar->add_menu( array(
                'parent'  => $this->component_slug,
                'id'      => 'my-bp-resume-add',
                'title'   => __('Add','buddypress'),
                'href'    => trailingslashit( $parent_url.'add' )
            ));
        }
    }


	function resume_page() {
		bp_core_load_template( 'members/single/plugins' );
	}
	
	function template_content() {
		global $bp;
		
		include( BPRP_ROOT_PATH .'pages/style.php');
		
		if( !empty($this->messages)) {
			foreach($this->messages as $m){
				echo '<div class="bprp-updated"><p>'.$m.'</p></div>';
			}
		}
		
		if( $bp->current_component == $this->component_slug ) {
			if( $bp->current_action == 'add'){
				$this->edit_page();
			} else {
				$this->view_page();
			}
		}
	}

	function view_page(){
		include( BPRP_ROOT_PATH .'pages/view.php');
	}
	
	function edit_page(){
		include( BPRP_ROOT_PATH . 'pages/edit.php');
	}
	
	function process_request() {
		
		if( ! (bp_is_my_profile() || current_user_can('administrator') ) ) {
			return;
		}
		
		if( isset($_POST['add_education']) ) {
			$this->add_info_array('education', 'Education updated');
		}
		
		if( isset($_GET['delete_education']) ) {
			$this->delete_info_array('education', 'Education record revomed');
		}
		
		if( isset($_POST['add_experience']) ) {
			$this->add_info_array('experience', 'Professional Experience updated');
		}
		
		if( isset($_GET['delete_experience']) ) {
			$this->delete_info_array('experience', 'Professional Experience record revomed');
		}
		
		if( isset($_POST['add_skill']) ) {
			$this->add_skill();
		}
		
		if( isset($_GET['delete_skill']) ) {
			$this->delete_skill();
		}
	}
	
	function add_info_array( $type, $message = 'Updated succesfully' ) {
		$option_name = 'resume_' . $type;
		
		$info = get_user_meta( $this->user_id,  $option_name, true);
		if( empty( $info ) ) {
			$info = array();
		}
		
		$new_item = $_POST[$type];
		$new_item['current'] = ( !isset($new_item['current'] ) ) ? 0 : 1;
		$new_item['timestamp'] = time();
		$info[] = $new_item;
		
		update_user_meta( $this->user_id, $option_name, $info );
		$this->messages[] = $message;
	}
	
	function delete_info_array( $type, $message = 'Record revomed') {
		$timestamp = (int)$_GET['delete_'.$type];
		$option_name = 'resume_' . $type;
		
		$info = get_user_meta( $this->user_id, $option_name, true);
		if( ! empty( $info ) ) {
			foreach( $info as $i => $item){
				if( $item['timestamp'] == $timestamp ) {
					unset($info[$i]);
					break;
				}
			}
			update_user_meta( $this->user_id, $option_name, $info);
			$this->messages[] = $message;;
		}
	}
	
	function add_skill(){
		$skills = get_user_meta( $this->user_id, 'resume_skills', true);
		if( empty( $skills ) ) {
			$skills = array();
		}
		$new_item = $_POST['skill'];
		$skills[] = $new_item;
		update_user_meta( $this->user_id, 'resume_skills', $skills);
		$this->messages[] = 'Skills list updated';
	}
	
	function delete_skill() {
		$id = (int)$_GET['delete_skill'];
		$skills = get_user_meta( $this->user_id, 'resume_skills', true);
		if( ! empty( $skills ) ) {
			unset($skills[$id]);
			update_user_meta( $this->user_id, 'resume_skills', $skills);
		}
		$this->messages[] = 'Skill revomed from list';
	}
	
	
	
	function delete_link($id, $type) {
		global $bp;
		$link = trailingslashit( $bp->displayed_user->domain . $this->component_slug );
		$link .= '?delete_'.$type.'='.$id;
		return $link;
	}
	
	function months_list(){
		for($i=1;$i<=12;$i++){
			echo '<option value="'.date('m',mktime(0,0,0,$i)).'">'.date('F',mktime(0,0,0,$i)).'</option>';		
		}
	}
	
	function years_list(){
		$current_year = date('Y');
		for($i=$current_year;$i>=1970;$i--){
			echo '<option value="'.$i.'">'.$i.'</option>';		
		}
	}
	
	function country_list(){
		include('pages/countries.php');
	}
	
	function sort_dates($a, $b) {
		if( $a['current'] == '1' ) {
			return -1;
		}
		if( $b['current'] == '1' ) {
			return 1;
		}
		
		$a_date = mktime(0,0,0,$a['end_date_m'],1,$a['end_date_y']);
		$b_date = mktime(0,0,0,$b['end_date_m'],1,$b['end_date_y']);
		
		if ($a_date == $b_date) {
			return 0;
		}
		return ($a_date > $b_date) ? -1 : 1;
	}
}

$BP_Resume_Page = new BP_Resume_Page();