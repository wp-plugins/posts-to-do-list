<?php
/*
Plugin Name: Posts To Do List
Plugin URI: http://www.thecrowned.org/posts-to-do-list
Description: Share post ideas with your blog writers, suggest them what to write and keep track of all the posts ideas in a convenient to do list. Do not lose post ideas, keep them!
Author: Stefano Ottolenghi
Version: 0.8
Author URI: http://www.thecrowned.org/
*/

//If trying to open this file without wordpress, warn and exit
if( !function_exists( 'add_action' ) )
    die( 'This file is not meant to be called directly' );

include_once( 'posts-to-do-list-options-functions.php' );
include_once( 'posts-to-do-list-ajax-functions.php' );
include_once( 'posts-to-do-list-print-functions.php' );
include_once( 'posts-to-do-list-install-functions.php' );

class posts_to_do_list_core {
    public static   $newest_version,
                    $posts_to_do_list_db_table,
                    $posts_to_do_list_options,
                    $posts_to_do_list_options_page_slug,
                    $posts_to_do_list_dashboard_page_slug,
                    $posts_to_do_list_options_page_link,
                    $posts_to_do_list_dashboard_page_link,
                    $posts_to_do_list_ajax_loader,
                    $publication_time_range_start,
                    $publication_time_range_end;
    
    function __construct() {
        global $wpdb;
        
        self::$posts_to_do_list_ajax_loader = plugins_url( 'style/images/ajax-loader.gif', __FILE__ );
        self::$newest_version               = '0.8';
        self::$posts_to_do_list_db_table    = $wpdb->prefix.'posts_to_do_list';
        
        //If table does not exist, create it 
        if( ! $wpdb->query( 'SHOW TABLES FROM '.$wpdb->dbname.' LIKE "'.self::$posts_to_do_list_db_table.'"' ) )
            posts_to_do_list_install::posts_to_do_list_create_table();
        
        //If option does not exist, create it
        if( ! self::$posts_to_do_list_options = @get_option( 'posts_to_do_list' ) )
            posts_to_do_list_install::posts_to_do_list_create_option();
        
        //If updating from an older version, run update procedure
        if( self::$posts_to_do_list_options['current_version'] != self::$newest_version )
            self::posts_to_do_list_update_routine();
        
        //Define publication time range depending on chosen settings: if monthly it depends on current month days number, weekly always 7, otherwise custom
        if( self::$posts_to_do_list_options['publication_time_range'] == 'week' ) {
            self::$publication_time_range_start   = time() - ( ( date( 'N' )-1 )*24*60*60 );
            self::$publication_time_range_end     = time();
        } else if( self::$posts_to_do_list_options['publication_time_range'] == 'month' ) {
            self::$publication_time_range_start   = time() - ( ( date( 'j' )-1 )*24*60*60 );
            self::$publication_time_range_end     = time();
        } else if( is_numeric( self::$posts_to_do_list_options['publication_time_range'] ) ) {
            self::$publication_time_range_start   = time() - ( self::$posts_to_do_list_options['publication_time_range']*24*60*60 );
            self::$publication_time_range_end     = time();
        }
        
        //Add left menu entries for options pages
        add_action( 'admin_menu', array( $this, 'posts_to_do_list_menus' ) );
        
        //When plugin is installed
        register_activation_hook( __FILE__, array( 'posts_to_do_list_install', 'posts_to_do_list_do_install' ) );
        
        //Hook on blog adding on multisite wp to install the plugin on new blogs either
        add_action( 'wpmu_new_blog', array( 'posts_to_do_list_install', 'posts_to_do_list_new_blog_install' ), 10, 6); 
        
        //Load metaboxes for posts, options and dashboard pages
        add_action( 'add_meta_boxes', array( $this, 'posts_to_do_list_post_page_metabox' ) );
        add_action( 'load-settings_page_posts_to_do_list_options', array( $this, 'posts_to_do_list_options_page_metaboxes' ) );
        add_action( 'load-dashboard_page_posts_to_do_list', array( $this, 'posts_to_do_list_dashboard_page_metaboxes' ) );
        
        //Hook to show custom action links besides the usual "Edit" and "Deactivate"
        add_filter( 'plugin_action_links', array( $this, 'posts_to_do_list_settings_meta_link' ), 10, 2 );
        add_filter( 'plugin_row_meta', array( $this, 'posts_to_do_list_donate_meta_link' ), 10, 2 );
        
        //Inject proper css stylesheets to make the two settings page meta box columns 50% large equal
        add_action( 'admin_head-settings_page_posts_to_do_list_options', array( $this, 'posts_to_do_list_head' ) );
        
        //Manage AJAX calls
        add_action( 'wp_ajax_posts_to_do_list_ajax_retrieve_title', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_retrieve_title' ) );
        add_action( 'wp_ajax_posts_to_do_list_ajax_new_item_submit', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_new_item_add' ) );
        add_action( 'wp_ajax_posts_to_do_list_ajax_print_item_after_adding', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_print_item_after_adding' ) );
        add_action( 'wp_ajax_posts_to_do_list_ajax_mark_as_done', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_mark_as_done' ) );
        add_action( 'wp_ajax_posts_to_do_list_ajax_get_page', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_get_page' ) );
        add_action( 'wp_ajax_posts_to_do_list_ajax_delete_item', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_delete_item' ) );
        add_action( 'wp_ajax_posts_to_do_list_ajax_i_ll_take_it', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_i_ll_take_it' ) );
        add_action( 'wp_ajax_posts_to_do_list_ajax_i_dont_want_it_anymore', array( 'posts_to_do_list_ajax_functions', 'posts_to_do_list_ajax_i_dont_want_it_anymore' ) );
    }
    
    function posts_to_do_list_update_routine() {
        global $wpdb;
        
        if( ! $wpdb->query( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '".self::$posts_to_do_list_db_table."' AND TABLE_SCHEMA = '".$wpdb->dbname."' AND COLUMN_NAME = 'item_author'" ) ) {
            $wpdb->query( "ALTER TABLE `".self::$posts_to_do_list_db_table."` ADD `item_author` INT(15) NOT NULL DEFAULT '0'" );
        }
        if( ! $wpdb->query( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '".self::$posts_to_do_list_db_table."' AND TABLE_SCHEMA = '".$wpdb->dbname."' AND COLUMN_NAME = 'item_priority'" ) ) {
            $wpdb->query( "ALTER TABLE `".self::$posts_to_do_list_db_table."` ADD `item_priority` INT(1) NOT NULL DEFAULT '4'" );
        }
        if( ! $wpdb->query( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '".self::$posts_to_do_list_db_table."' AND TABLE_SCHEMA = '".$wpdb->dbname."' AND COLUMN_NAME = 'item_adder'" ) ) {
            $wpdb->query( "ALTER TABLE `".self::$posts_to_do_list_db_table."` ADD `item_adder` INT(15) NOT NULL " );
        }
        $wpdb->query( 'ALTER TABLE  `'.self::$posts_to_do_list_db_table.'` CHANGE  `item_url`  `item_url` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL' );
        
        self::$posts_to_do_list_options['current_version'] = self::$newest_version;
        update_option( 'posts_to_do_list', self::$posts_to_do_list_options );
    }
    
    //Called after options are somehow changed, it mirrors those changes in the global variable used around
    function posts_to_do_list_update_options_variable() {
        self::$posts_to_do_list_options = get_option( 'posts_to_do_list' );
    }
    
    function posts_to_do_list_menus() {
        self::$posts_to_do_list_options_page_slug   = add_options_page( 'Posts To Do List Options', 'Posts To Do List Options', 'manage_options', 'posts_to_do_list_options', array( $this, 'posts_to_do_list_options' ) );
        self::$posts_to_do_list_options_page_link   = 'admin.php?page=posts_to_do_list_options';
        self::$posts_to_do_list_dashboard_page_slug = add_dashboard_page( "Posts To Do List", "Posts To Do List", 'manage_options', 'posts_to_do_list', array( $this, 'posts_to_do_list_dashboard' ));
        self::$posts_to_do_list_dashboard_page_link = 'index.php?page=posts_to_do_list';
    }
    
    function posts_to_do_list_head() { ?>        
<script type="text/javascript">
    /* <![CDATA[ */
        //Won't let them reset without confirmation
        function confirm_reset() {
            var agree = confirm("Are you sure you wish to reset? There will not be a second confirmation, this is your only choice to cancel!");
            if (agree)
            	return true;
            else
            	return false;
        }
        
        jQuery(document).ready(function($) {
            $(".tooltip_container").tipTip({
                activation: "hover",
                keepAlive:  "true",
                maxWidth:   "300px"
            });
            
            //Enter key will always trigger the Save Options submit, not the others
            $("#posts_to_do_list_form input").keypress(function (e) {
                if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
                    $('#posts_to_do_list_options_save').click();
                    return false;
                } else {
                    return true;
                }
            });
        });
    /* ]]> */
</script>
<style type="text/css">
    #side-info-column {
        width: 49%;
    }
    .inner-sidebar #side-sortables {
        width: 100%;
    }
    .has-right-sidebar #post-body-content {
        width: 49%;
        margin-right: 390px;
    }
    .has-right-sidebar #post-body {
        margin-right: -50%;
    }
    #post-body #normal-sortables {
        width: 100%;
    }
    .section_title {
        font-weight: bold;
        text-align: left;
        margin-bottom: -5px;
        margin-top: 20px;
    }
    .tooltip_span {
        float: right;
        width: 20px;
        text-align: right;
    }
</style>
    <?php }
    
    function posts_to_do_list_post_page_metabox( ) {
       add_meta_box( 'posts_to_do_list', 'Posts To Do List', array( $this, 'posts_to_do_list_metabox_post' ), 'post', 'side', 'high' );
       self::posts_to_do_list_enqueue_scripts();
    }
    
    function posts_to_do_list_dashboard_page_metaboxes() {
        wp_enqueue_script( 'post' );
        add_meta_box( 'posts_to_do_list_main', 'Posts To Do List', array( $this, 'posts_to_do_list_metabox_post' ), self::$posts_to_do_list_dashboard_page_slug, 'side' );
        add_meta_box( 'posts_to_do_list_stats', 'Posts To Do List Stats', array( $this, 'posts_to_do_list_metabox_stats' ), self::$posts_to_do_list_dashboard_page_slug, 'normal' );
        
        //Datepicker stuff
        wp_enqueue_script( 'jquery-ui-datepicker', plugins_url( 'js/jquery.ui.datepicker.min.js', __FILE__ ), array('jquery', 'jquery-ui-core' ) );
        wp_enqueue_style( 'jquery.ui.theme', plugins_url( 'style/ui-lightness/jquery-ui-1.8.15.custom.css', __FILE__ ) );
        
        self::posts_to_do_list_enqueue_scripts();
    }
    
    function posts_to_do_list_options_page_metaboxes() {
        wp_enqueue_script( 'post' );
        add_meta_box( 'posts_to_do_list_general_options', 'Posts To Do List General Options', array( $this, 'posts_to_do_list_metabox_general_options' ), self::$posts_to_do_list_options_page_slug, 'normal' );
        add_meta_box( 'posts_to_do_list_permissions', 'Posts To Do List Permissions', array( $this, 'posts_to_do_list_metabox_permissions' ), self::$posts_to_do_list_options_page_slug, 'normal' );
        add_meta_box( 'posts_to_do_list_metabox_reset', 'Posts To Do List Reset', array( $this, 'posts_to_do_list_metabox_reset' ), self::$posts_to_do_list_options_page_slug, 'side' );
        add_meta_box( 'posts_to_do_list_support_the_author', 'Support The Author', array( $this, 'posts_to_do_list_metabox_support_the_author' ), self::$posts_to_do_list_options_page_slug, 'side' );
        
        //And this is for the tooltips
        wp_enqueue_script( 'jquery-tooltip-plugin', plugins_url( 'js/jquery.tiptip.min.js', __FILE__ ), array( 'jquery' ) );
        wp_enqueue_style( 'jquery.tooltip.theme', plugins_url( 'style/tipTip.css', __FILE__ ) );
    }
    
    function posts_to_do_list_enqueue_scripts() {
        global $current_user;
        
        wp_enqueue_script( 'posts_to_do_list_js', plugins_url( 'js/posts-to-do-list-js.js', __FILE__ ), 'jquery' );
        wp_localize_script( 'posts_to_do_list_js', 'posts_to_do_list_vars', array(
            'ajax_url'                                              => admin_url( 'admin-ajax.php' ),
            'ajax_loader'                                           => self::$posts_to_do_list_ajax_loader,
            'current_user_ID'                                       => $current_user->ID,
            'current_user_display_name'                             => $current_user->display_name,
            'current_date'                                          => date( 'd/m/Y' ),
            'nonce_posts_to_do_list_ajax_mark_as_done'              => wp_create_nonce( 'posts_to_do_list_ajax_mark_as_done' ),
            'nonce_posts_to_do_list_ajax_i_ll_take_it'              => wp_create_nonce( 'posts_to_do_list_ajax_i_ll_take_it' ),
            'nonce_posts_to_do_list_ajax_i_dont_want_it_anymore'    => wp_create_nonce( 'posts_to_do_list_ajax_i_dont_want_it_anymore' ),
            'nonce_posts_to_do_list_ajax_delete_item'               => wp_create_nonce( 'posts_to_do_list_ajax_delete_item' ),
            'nonce_posts_to_do_list_ajax_get_page'                  => wp_create_nonce( 'posts_to_do_list_ajax_get_page' ),
            'nonce_posts_to_do_list_ajax_retrieve_title'            => wp_create_nonce( 'posts_to_do_list_ajax_retrieve_title' ),
            'nonce_posts_to_do_list_ajax_new_item_add'              => wp_create_nonce( 'posts_to_do_list_ajax_new_item_add' ),
            'nonce_posts_to_do_list_ajax_print_item_after_adding'   => wp_create_nonce( 'posts_to_do_list_ajax_print_item_after_adding' )
        ) );
    }
    
    //If we are on the right plugin, show the "Settings" link in the plugins list (under the title)
    function posts_to_do_list_settings_meta_link( $links, $file ) {
       if ( $file == plugin_basename( __FILE__ ) )
            $links[] = '<a href="'.admin_url( self::$posts_to_do_list_options_page_link ).'" title="'.__('Settings').'">'.__('Settings').'</a>';
     
        return $links;
    }
    
    //If we are on the right plugin, show the "Donate" link in the plugins list (under the description)
    function posts_to_do_list_donate_meta_link( $links, $file ) {
       if ( $file == plugin_basename( __FILE__ ) )
            $links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4Y4STCU56MUKE" title="'.__('Donate').'">'.__('Donate').'</a>';
     
        return $links;
    }
    
    function posts_to_do_list_metabox_post() {
        global  $wpdb,
                $current_user; ?>

        <div id="posts_to_do_list_content">
        
        <?php $total_rows = self::posts_to_do_list_get_page( 1 ); ?>
        
        </div>
        <div id="posts_to_do_list_content_error" style="display: none; color: red; font-weight: bold; font-size: smaller;"></div>
        <div>
            <span style="float:left; display: inline-block;"><a href="#" id="posts_to_do_list_previous_page" title="Previous entries" rel="1">&laquo;</a></span>
            <span style="text-align: center; width: 90%; display: inline-block;">Page <span id="posts_to_do_list_current_page">1</span> of <span id="posts_to_do_list_total_pages"><?php echo ( ceil( $total_rows/self::$posts_to_do_list_options['items_per_page'] ) ); ?></span></span>
            <span style="float: right; display: inline-block;"><a href="#" id="posts_to_do_list_next_page" title="Next entries" rel="2">&raquo;</a></span>
        </div>
        
        <?php //If current user can add new items, show link and div
        if( array_intersect( self::$posts_to_do_list_options['permission_new_item_add_roles'], get_userdata( $current_user->ID )->roles ) )
            posts_to_do_list_print_functions::posts_to_do_list_print_new_item_form(); ?>
        
        <div class="clear"></div>
        
    <?php }
    
    //Queries the db for the data to populate the list metabox. If user can or is admin, all records are selected, otherwise only user assigned ones and unassigned ones. Records are ordered by item_done (so that NULL values appear at the bottom) > item_priority > item_timestamp. Results are then sorted. It both calls print_page to show the HTML and returns the total rows current user is allowed to see
    function posts_to_do_list_get_page( $requested_page ) {
        global  $wpdb,
                $current_user;
        
        if( $requested_page <= 0 )
            die( 'Error: Page number not valid' );
        
        if( self::$posts_to_do_list_options['permission_users_can_see_others_items'] == 1 OR in_array( 'administrator', $current_user->roles ) ) {
            $requested_page_items   = $wpdb->get_results( 'SELECT * FROM '.self::$posts_to_do_list_db_table.' ORDER BY item_done ASC, (item_author = '.$current_user->ID.') DESC, item_priority DESC, item_timestamp DESC LIMIT '.( ( $requested_page - 1 ) * self::$posts_to_do_list_options['items_per_page'] ).','.self::$posts_to_do_list_options['items_per_page'] );
            $total_rows             = $wpdb->get_var( 'SELECT COUNT(*) FROM '.self::$posts_to_do_list_db_table );
        } else {
            $requested_page_items   = $wpdb->get_results( 'SELECT * FROM '.self::$posts_to_do_list_db_table.' WHERE item_author = '.$current_user->ID.' OR item_author = 0 ORDER BY item_done ASC, item_priority DESC, item_timestamp DESC LIMIT '.( ( $requested_page - 1 ) * self::$posts_to_do_list_options['items_per_page'] ).','.self::$posts_to_do_list_options['items_per_page'] );
            $total_rows             = $wpdb->get_var( 'SELECT COUNT(*) FROM '.self::$posts_to_do_list_db_table.' WHERE item_author = 0 OR item_author = '.$current_user->ID );
        }
        
        $requested_page_items = self::posts_to_do_list_order_page_result( $requested_page_items );
        posts_to_do_list_print_functions::posts_to_do_list_print_page( $requested_page_items );
        return $total_rows; 
    }
    
    //Called after get_page but before print_page. Puts first user assigned items not done yet, then not done yet items assigned to other users or unassigned, and finally done items
    function posts_to_do_list_order_page_result( $data ) {
        global $current_user;
        
        //Not directly using an object cause we need the [] array ability
        $return_array = array();
        
        //First foreach only takes into account items assigned to current user which are not marked as done, and puts them on top of the new array
        foreach( $data as $single ) {
            
            if( $single->item_author == $current_user->ID AND $single->item_done == NULL )
                $return_array[] = $single;
        }
        
        //Second foreach only takes into account NON-marked as done, NON-current user assigned items, and puts after user assigned ones in the new array
        foreach( $data as $single ) {
            if( $single->item_author != $current_user->ID AND $single->item_done == NULL )
                $return_array[] = $single;
        }
        
        //Third foreach only takes into account marked as done items, and puts after NON-marked as done ones in the new array
        foreach( $data as $single ) {
            if( strlen( $single->item_done ) > 0 )
                $return_array[] = $single;
        }
        return $return_array;
    }
    
    //Shows the dashboard stats page
    function posts_to_do_list_dashboard() {
        global $wpdb; ?>
        
        <div class="wrap">
            <h2>Posts To Do List Stats</h2>
            
        <?php //Metaboxes WP Nonces
        wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
        wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
        
            <div id="poststuff" class="metabox-holder has-right-sidebar">
                <div id="side-info-column" class="inner-sidebar">
            	<?php do_meta_boxes( self::$posts_to_do_list_dashboard_page_slug, 'side', null ); ?>
                </div>
                <div id="post-body" class="has-sidebar">
                    <div id="post-body-content" class="has-sidebar-content">
            	<?php do_meta_boxes( self::$posts_to_do_list_dashboard_page_slug, 'normal', null ); ?>
                    </div>
                </div>
            </div>
            <div class="clear"></div>
        </div>
    <?php }
    
    //Deletes each and every single record from the plugin table
    function posts_to_do_list_bulk_delete() {
        global $wpdb;
        
        //Nonce check
        check_admin_referer( 'posts_to_do_list_reset', 'posts_to_do_list_reset' );
        
        $wpdb->query( 'DELETE FROM '.self::$posts_to_do_list_db_table );
        echo '<div id="message" class="updated fade"><p><strong>Posts To Do List records deleted.</strong> <a href="'.admin_url( self::$posts_to_do_list_dashboard_page_link ).'">Go to the dashboard page &raquo;</a></p></div>';
    }
    
    //Saves sent options
    function posts_to_do_list_options_save( $_POST ) {
        //Nonce check
        check_admin_referer( 'posts_to_do_list_main_form_update', 'posts_to_do_list_main_form_update' );
        
        $new_settings                       = array();
        $new_settings['current_version']    = self::$posts_to_do_list_options['current_version'];
        
        //General settings box
        $new_settings['items_per_page']                     = (int) trim( $_POST['items_per_page'] );
        $new_settings['send_email_users_on_assignment']     = posts_to_do_list_options_functions::determine_checkbox_value( @$_POST['send_email_users_on_assignment'] );
        
        if( $_POST['publication_time_range'] == 'custom' )
            $new_settings['publication_time_range'] = (int) $_POST['publication_time_range_custom_value'];
        else
            $new_settings['publication_time_range'] = $_POST['publication_time_range'];
        
        //Permissions box: for the roles, it cycles through the POST data and find all the fields that were sent to add them to the serialized array
        $permission_new_item_add_roles  = array();
        $permission_item_delete_roles   = array();
        $permission_item_unassign_roles = array();
        foreach( $_POST as $key => $value ) {
            if( strpos( $key, 'permission_new_item_add_' ) === 0 )
                $permission_new_item_add_roles[] = $value;
                
            if( strpos( $key, 'permission_item_delete_' ) === 0 )
                $permission_item_delete_roles[] = $value;
            
            if( strpos( $key, 'permission_item_unassign_' ) === 0 )
                $permission_item_unassign_roles[] = $value;
        }
        
        $new_settings['permission_new_item_add_roles']          = $permission_new_item_add_roles;
        $new_settings['permission_item_delete_roles']           = $permission_item_delete_roles;
        $new_settings['permission_item_unassign_roles']         = $permission_item_unassign_roles;
        $new_settings['permission_users_can_see_others_items']  = posts_to_do_list_options_functions::determine_checkbox_value( @$_POST['permission_users_can_see_others_items'] );
        
        //Options update
        update_option( 'posts_to_do_list', $new_settings );
        self::posts_to_do_list_update_options_variable();
        
        echo '<div id="message" class="updated fade"><p><strong>Posts To Do List settings updated.</strong> <a href="'.admin_url( self::$posts_to_do_list_dashboard_page_link ).'">Go to the dashboard page &raquo;</a></p></div>';
    }
    
    function posts_to_do_list_options() { ?> 
        <div class="wrap">
        
        <?php //Options update and similar
        if( isset( $_POST['posts_to_do_list_options_save'] ) )
            self::posts_to_do_list_options_save( $_POST );
        else if( isset( $_POST['posts_to_do_list_reset_button'] ) )
            self::posts_to_do_list_bulk_delete(); ?>
        
            <h2>Posts To Do List Options</h2>
            <p>From this page you can configure the Post Pay Counter plug-in. You will find all the information you need inside each following box and, for each available function, clicking on the info icon on the right of them. Generated sta, where you will find many details about each post (its status, date, , images and comments count, payment value) with tons of general statistics and the ability to browse old stats. If you want to be able to see stats since the first published post, use the Update Stats box below.</p>
            <form action="" method="post" id="posts_to_do_list_form">            
        
        <?php //Nonces for major security
        wp_nonce_field( 'posts_to_do_list_main_form_update', 'posts_to_do_list_main_form_update' );
        wp_nonce_field( 'posts_to_do_list_reset', 'posts_to_do_list_reset' );
        wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
        wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false ); ?>
        
                <div id="poststuff" class="metabox-holder has-right-sidebar">
                    <div id="side-info-column" class="inner-sidebar">
                	<?php do_meta_boxes( self::$posts_to_do_list_options_page_slug, 'side', null ); ?>
                    </div>
                    <div id="post-body" class="has-sidebar">
                        <div id="post-body-content" class="has-sidebar-content">
                	<?php do_meta_boxes( self::$posts_to_do_list_options_page_slug, 'normal', null ); ?>
                        </div>
                    </div>
                </div>
                <div class="clear"></div>
                <input type="submit" class="button-primary" name="posts_to_do_list_options_save" id="posts_to_do_list_options_save" value="<?php _e( 'Save options' ) ?>" />
            </form>
        </div>
    <?php }
    
    function posts_to_do_list_metabox_general_options() { ?>
        
        <span class="tooltip_span">
            <img src="<?php echo plugins_url( 'style/images/info.png', __FILE__ ); ?>" title="Put here the number of items that you want to be displayed for each page in the Posts To Do List in the posts page." class="tooltip_container" />
        </span>
        <label for="items_per_page">Number of items to display per page:</label>
        <input type="text" id="items_per_page" name="items_per_page" size="5" value="<?php echo self::$posts_to_do_list_options['items_per_page']; ?>" />
        
        <?php posts_to_do_list_options_functions::print_p_field( 'Send email to users when a post is assigned to them', self::$posts_to_do_list_options['send_email_users_on_assignment'], 'checkbox', 'send_email_users_on_assignment', 'If checked, when a new post is assigned to a user, they will receive an email with the related details.' ); ?>
        
        <div class="section_title">Publication time range</div>
        <p style="height: 10px;">
            <span class="tooltip_span">
                <img src="<?php echo plugins_url( 'style/images/info.png', __FILE__ ); ?>" title="With this, in the stats it will automatically selected a time range that goes from the beginning of the week to the current day. You will anyway be able to change the time range as you wish from the stats page." class="tooltip_container" />
            </span>
            <label>
                <span style="float: left; width: 5%;">
                    <input type="radio" name="publication_time_range" value="week" <?php if( self::$posts_to_do_list_options['publication_time_range'] == 'week' ) { echo 'checked="checked"'; } ?> />
                </span>
                <span style="width: 90%;">Default stats time range displayed is first day the week - today</span>
            </label>
        </p>
        <p style="height: 10px;">
            <span class="tooltip_span">
                <img src="<?php echo plugins_url( 'style/images/info.png', __FILE__ ); ?>" title="With this, in the stats it will automatically selected a time range that goes from the beginning of the month to the current day. You will anyway be able to change the time range as you wish from the stats page." class="tooltip_container" />
            </span>
            <label>
                <span style="float: left; width: 5%;">    
                    <input type="radio" name="publication_time_range" value="month" <?php if( self::$posts_to_do_list_options['publication_time_range'] == 'month' ) { echo 'checked="checked"'; } ?> />
                </span>
                <span style="width: 90%;">Default stats time range displayed is first day the month - today</span>
            </label>
        </p>
        <p style="height: 10px;">
            <span class="tooltip_span">
                <img src="<?php echo plugins_url( 'style/images/info.png', __FILE__ ); ?>" title="Customizing the time range means that you can choose how many days back you want the default time range to be. So, for example, if you set this to 90, and today is 25 May, the plugin will show you stats ranging from 25 February to 25 May." class="tooltip_container" />
            </span>
            <label>
                <span style="float: left; width: 5%;">    
                    <input type="radio" name="publication_time_range" value="custom" <?php if( is_numeric( self::$posts_to_do_list_options['publication_time_range'] ) ) { echo 'checked="checked"'; } ?> />
                </span>
                <span style="width: 90%;">Customize default stats time range displayed <input style="height: 20px;" type="text" id="publication_time_range_custom_value" name="publication_time_range_custom_value" size="5" maxlength="5" <?php if( is_numeric( self::$posts_to_do_list_options['publication_time_range'] ) ) { echo 'value="'.self::$posts_to_do_list_options['publication_time_range'].'"'; } ?> /></span>
            </label>
        </p>
        <div style="clear: both;"></div>
    <?php }
    
    function posts_to_do_list_metabox_permissions() {
        global $wp_roles;
        
        if ( ! isset($wp_roles) )
            $wp_roles = new WP_Roles();
        
        posts_to_do_list_options_functions::print_p_field( 'Non-administrator users can see posts assigned to other users', self::$posts_to_do_list_options['permission_users_can_see_others_items'], 'checkbox', 'permission_users_can_see_others_items', 'If checked, normal users will not only see posts assigned to themselves, but also the ones assigned to other users. Unassigned posts will be shown anyway.' ); ?>
        
        <span class="tooltip_span">
            <img src="<?php echo plugins_url( 'style/images/info.png', __FILE__ ); ?>" title="Only users belonging to one of the checked user roles will be able to add posts to the list." class="tooltip_container" />
        </span>
        <div class="section_title">User roles allowed to add new posts</div>
        
        <?php foreach( $wp_roles->role_names as $key => $value ) {
            if( in_array( $key, self::$posts_to_do_list_options['permission_new_item_add_roles'] ) )
                $checked = ' checked="checked"';
            
            echo '<p style="height: 10px;"><label><input type="checkbox" name="permission_new_item_add_'.$key.'" value="'.$key.'"'.@$checked.' /> '.$value.'</label></p>';
            unset( $checked );
        } ?>
        
        <span class="tooltip_span">
            <img src="<?php echo plugins_url( 'style/images/info.png', __FILE__ ); ?>" title="Only users belonging to one of the checked user roles will be able to delete previously added posts." class="tooltip_container" />
        </span>
        <div class="section_title">User roles allowed to delete already added posts</div>
        
        <?php foreach( $wp_roles->role_names as $key => $value ) {
            if( in_array( $key, self::$posts_to_do_list_options['permission_item_delete_roles'] ) )
                $checked = ' checked="checked"';
            
            echo '<p style="height: 10px;"><label><input type="checkbox" name="permission_item_delete_'.$key.'" value="'.$key.'"'.@$checked.' /> '.$value.'</label></p>';
            unset( $checked );
        } ?>
        
        <span class="tooltip_span">
            <img src="<?php echo plugins_url( 'style/images/info.png', __FILE__ ); ?>" title="Only users belonging to one of the checked user roles will be able to unassign items assigned to them." class="tooltip_container" />
        </span>
        <div class="section_title">User roles allowed to unassign posts assigned to them</div>
        
        <?php foreach( $wp_roles->role_names as $key => $value ) {
            if( in_array( $key, self::$posts_to_do_list_options['permission_item_unassign_roles'] ) )
                $checked = ' checked="checked"';
            
            echo '<p style="height: 10px;"><label><input type="checkbox" name="permission_item_unassign_'.$key.'" value="'.$key.'"'.@$checked.' /> '.$value.'</label></p>';
            unset( $checked );
        }
    }
    
    function posts_to_do_list_metabox_reset() { ?>
        <p>If, for some reason to me unknown, you would like to delete all the posts added to the list with just one click, the button below will do. But do not blame me: <strong>each and every single added item will be unrecoverably deleted</strong>, although your options will not be touched.</p>
        <p style="text-align: center;"><input type="submit" class="button-secondary" name="posts_to_do_list_reset_button" value="I got it. Do it, delete everything!" onclick="javascript:return confirm_reset();" /></p>
    <?php }
    
    function posts_to_do_list_metabox_stats() {
        global $wpdb;
        
        //Merging _GET and _POST data due to the time range form available in the stats page header. 
        //We don't know whether the user is choosing the time frame from the form (via POST data) or if they arrived to this page following a link (via GET data)
        $get_and_post = array_merge( $_GET, $_POST );
        
        //Validate time range values (start and end), if set. They must be isset, numeric and positive. 
        //If something's wrong, start and end time are taken from the default settings the user set (publication time range) and defined in the construct of functions
        if( ( isset( $get_and_post['tstart'] ) AND ( ! is_numeric( $get_and_post['tstart'] ) OR  $get_and_post['tstart'] < 0 ) )
        OR ( isset( $get_and_post['tend'] ) AND ( ! is_numeric( $get_and_post['tend'] ) OR  $get_and_post['tend'] < 0 ) )
        OR ( ! isset( $get_and_post['tend'] ) OR ! isset( $get_and_post['tend'] ) ) ) {
            //If user has selected a time range, convert it into unix timestamp
            if( strtotime( @$get_and_post['tstart'] ) AND strtotime( @$get_and_post['tend'] ) ) {
                $time_start = strtotime( $get_and_post['tstart'].' 00:00:01' );
                $time_end   = strtotime( $get_and_post['tend'].' 23:59:59' );
            } else {
                $time_start = mktime( 0, 0, 1, date( 'm', self::$publication_time_range_start ), date( 'd', self::$publication_time_range_start ), date( 'Y', self::$publication_time_range_start ) );
                $time_end   = mktime( 23, 59, 59, date( 'm', self::$publication_time_range_end ), date( 'd', self::$publication_time_range_end ), date( 'Y', self::$publication_time_range_end ) );
            }
        } else {
            $time_start = $get_and_post['tstart'];
            $time_end   = $get_and_post['tend'];
        } ?>
        
        <?php $first_available_post = $wpdb->get_var( 'SELECT item_timestamp FROM '.self::$posts_to_do_list_db_table.' LIMIT 0,1' );
		
    		if( $first_available_post == NULL )
                $first_available_post = time(); ?>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#posts_to_do_list_time_start').datepicker({
            dateFormat:         'yy/mm/dd',
            minDate:            '<?php echo date( 'y/m/d', $first_available_post ); ?>',
            maxDate:            '<?php echo date( 'y/m/d' ); ?>',
            changeMonth:        true,
            changeYear:         true,
            showButtonPanel:    true,
            showOtherMonths:    true,
            selectOtherMonths:  true,
            showAnim:           "slideDown",
            onSelect:           function(dateText, inst) {
                $('#posts_to_do_list_time_end').datepicker('option', 'minDate', new Date(inst.selectedYear, inst.selectedMonth, inst.selectedDay));
            }
        });
        $('#posts_to_do_list_time_end').datepicker({
            dateFormat:         'yy/mm/dd',
            minDate:            '<?php echo date( 'y/m/d', $first_available_post ); ?>',
            maxDate:            '<?php echo date( 'y/m/d' ); ?>',
            changeMonth:        true,
            changeYear:         true,
            showButtonPanel:    true,
            showOtherMonths:    true,
            selectOtherMonths:  true,
            showAnim:           "slideDown",
            onSelect:           function(dateText, inst) {
                 jQuery('#posts_to_do_list_time_start').datepicker('option', 'maxDate', new Date(inst.selectedYear, inst.selectedMonth, inst.selectedDay));
            }      
        });
    });
</script>
<form action="" method="post">
    <span style="float: left; text-align: center; margin-top: 5px;">
        <strong style="margin: 10px 0 5px; font-size: medium;">
            Showing stats from <input type="text" name="tstart" id="posts_to_do_list_time_start" class="mydatepicker" value="<?php echo date( 'Y/m/d', $time_start ); ?>" size="7" /> to <input type="text" name="tend" id="posts_to_do_list_time_end" class="mydatepicker" value="<?php echo date( 'Y/m/d', $time_end ); ?>" size="7" />
        </strong>
    </span>
    <span style="float: right; text-align: center;">
        <input type="submit" class="button-secondary" name="posts_to_do_list_update_time_range" value="<?php _e( 'Update time range' ) ?>" /><br />
        <a href="<?php echo admin_url( self::$posts_to_do_list_dashboard_page_link.'&amp;tstart='.$time_start.'&amp;tend='.$time_end ); ?>" title="Get the what-you-are-seeing permalink" style="font-size: smaller;">Get current view permalink</a>
    </span>
</form>
<div style="clear: both; "></div>
<hr style="border-color: #ccc; border-style: solid; border-width: 1px 0 0; clear: both; margin: 5px 0 20px; height: 0;" />
<h2 style="text-align: center;">General Stats</h2>
<p><em>Added</em> tells you how many posts were added to the to-do list by any user. <em>Marked as done</em> is for how many posts have been marked as done by, again, any user. <em>Still to do</em> are the ones that were added but that have not been marked as done yet. <em>Created</em> counts the number of Wordpress posts that were created by any user, either auto and manually saved drafts, pending review posts and future scheduled ones. And <em>Published</em> is finally the number of posts that were published. All of this refered to the time range displayed above.</p>
<table class="widefat fixed">
    <thead>
        <tr>
            <th scope="col" width="19%">Added</th>
            <th scope="col" width="26%">Marked as done</th>
            <th scope="col" width="19%">Still to do</th>
            <th scope="col" width="18%">Created</th>
            <th scope="col" width="19%">Published</th>
		</tr>
	</thead>
    <tbody>
    
    <?php self::posts_to_do_list_get_general_stats( $time_start, $time_end ); ?>
    
    </tbody>
</table>
<h2 style="text-align: center;">Detailed Stats</h2>
<p><em>Username</em> is the user we are taking into account. <em>Added</em> tells you how many posts were added to the to-do list by that user. <em>Assigned</em> is for how many posts were assigned to that user. <em>Done</em> is for how many posts that user marked as done, either assigned to them, to other users or unassigned. <em>Assigned Done</em> is for how many posts of the ones assigned to them that user marked as done. <em>Still to do</em> are the ones that were assigned to that user but that have not been marked as done yet. <em>Created</em> counts the number of Wordpress posts that were created by the user, either auto and manually saved drafts, pending review posts and future scheduled ones. And <em>Published</em> is finally the number of posts of that author that were published. All of this refered to the time range displayed above.</p>
<table class="widefat fixed">
    <thead>
        <tr>
            <th scope="col" width="15%">Username</th>
            <th scope="col" width="12%">Added</th>
            <th scope="col" width="15%">Assigned</th>
            <th scope="col" width="11%">Done</th>
            <th scope="col" width="18%">Assigned Done</th>
            <th scope="col" width="15%">Still to do</th>
            <th scope="col" width="12%">Created</th>
            <th scope="col" width="14%">Published</th>
		</tr>
	</thead>
    <tbody>
    
    <?php self::posts_to_do_list_get_detailed_stats( $time_start, $time_end ); ?>
    
    </tbody>
</table>
    <?php }
    
    function posts_to_do_list_metabox_support_the_author() { ?>
<p>If you like the Posts To Do List plugin, there are a couple of things you can do to support its development:</p>
<ul style="margin: 0 0 15px 2em;">
    <li style="list-style-image: url('<?php echo plugins_url( 'style/images/paypal.png', __FILE__ ); ?>');"><a target="_blank" href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4Y4STCU56MUKE" title="Donate money"><strong>Donate money</strong></a>. The plugin is free and is developed in my free time: a small income would make everything easier.</li>
    <li style="list-style-image: url('<?php echo plugins_url( 'style/images/amazon.png', __FILE__ ); ?>');">Give me something from my <a target="_blank" href="http://www.amazon.it/registry/wishlist/1JWAS1MWTLROQ" title="Amazon Wishlist">Amazon Wishlist</a>.</li>
    <li style="list-style-image: url('<?php echo plugins_url( 'style/images/feedback.png', __FILE__ ); ?>');">Suggest new functions and ideas you would like to see in the next release of the plugin, or report bugs you've found at the <a target="_blank" href="http://www.thecrowned.org/post-pay-counter" title="Plugin official page">official page</a>.</li>
    <li style="list-style-image: url('<?php echo plugins_url( 'style/images/star.png', __FILE__ ); ?>');">Rate it in the <a target="_blank" href="http://wordpress.org/extend/plugins/post-pay-counter/" title="Wordpress directory">Wordpress Directory</a> and share the <a target="_blank" href="http://www.thecrowned.org/post-pay-counter" title="Official plugin page">official page</a>.</li>
    <li style="list-style-image: url('<?php echo plugins_url( 'style/images/write.png', __FILE__ ); ?>');">Have a blog or write on some website? Write about the plugin!</li>
</ul>
    <?php }
    
    //Generates general_stats for the dashboard stats box, then calls the print_general_stats function
    function posts_to_do_list_get_general_stats( $time_start, $time_end ) {
        global $wpdb;
        
        $general_stats_array                            = array();
        $selected_data                                  = $wpdb->get_results( 'SELECT item_done FROM '.self::$posts_to_do_list_db_table.' WHERE item_timestamp BETWEEN '.$time_start.' AND '.$time_end );
        $general_stats_array['added_items']             = $wpdb->num_rows;
        $created_and_published_posts                    = $wpdb->get_results( 'SELECT post_date, post_status FROM '.$wpdb->posts.' WHERE UNIX_TIMESTAMP(post_date) BETWEEN '.$time_start.' AND '.$time_end );
        $general_stats_array['created_posts']           = $wpdb->num_rows;
        $general_stats_array['marked_as_done_items']    = 0;
        $general_stats_array['published_posts']         = 0;
        
        foreach( $selected_data as $single ) {
            if( strlen( $single->item_done ) > 0 )
                ++$general_stats_array['marked_as_done_items'];
        }
        
        foreach( $created_and_published_posts as $single ) {
            if( $single->post_status == 'publish' )
                ++$general_stats_array['published_posts'];
        }
        
        $general_stats_array['still_to_do_items'] = $general_stats_array['added_items'] - $general_stats_array['marked_as_done_items'];
        
        posts_to_do_list_print_functions::posts_to_do_list_print_general_stats( $general_stats_array );
    }
    
    //Generates detailed_stats for the dashboard stats box, then calls the print_detailed_stats function
    function posts_to_do_list_get_detailed_stats( $time_start, $time_end ) {
        global $wpdb;
        
        $detailed_stats_array           = array();
        $all_users                      = $wpdb->get_results( 'SELECT ID, display_name FROM '.$wpdb->users.' ORDER BY display_name ASC' );
        $selected_data                  = $wpdb->get_results( 'SELECT item_adder, item_done, item_author FROM '.self::$posts_to_do_list_db_table.' WHERE item_timestamp BETWEEN '.$time_start.' AND '.$time_end );
        $created_and_published_posts    = $wpdb->get_results( 'SELECT post_date, post_status, post_author FROM '.$wpdb->posts.' WHERE UNIX_TIMESTAMP(post_date) BETWEEN '.$time_start.' AND '.$time_end );
        
        foreach( $all_users as $user ) {
            $detailed_stats_array[$user->ID]                                    = array();
            $detailed_stats_array[$user->ID]['display_name']                    = stripslashes( $user->display_name );
            $detailed_stats_array[$user->ID]['created_posts']                   = 0;
            $detailed_stats_array[$user->ID]['added_items']                     = 0;
            $detailed_stats_array[$user->ID]['assigned_items']                  = 0;
            $detailed_stats_array[$user->ID]['total_marked_as_done_items']      = 0;
            $detailed_stats_array[$user->ID]['assigned_marked_as_done_items']   = 0;
            $detailed_stats_array[$user->ID]['published_posts']                 = 0;
            $detailed_stats_array[$user->ID]['still_to_do_items']               = 0;
            
            foreach( $selected_data as $single ) {
                $item_done = @unserialize( $single->item_done );
                
                if( is_array( $item_done ) AND $item_done['marker'] == $user->ID ) {
                    ++$detailed_stats_array[$user->ID]['total_marked_as_done_items'];
                    if( $single->item_author == $user->ID )
                        ++$detailed_stats_array[$user->ID]['assigned_marked_as_done_items'];
                }
                
                if( $single->item_adder == $user->ID )
                    ++$detailed_stats_array[$user->ID]['added_items'];
                
                if( $single->item_author == $user->ID )
                    ++$detailed_stats_array[$user->ID]['assigned_items'];
            }
            
            foreach( $created_and_published_posts as $single ) {
                if( $single->post_author == $user->ID ) {
                    ++$detailed_stats_array[$user->ID]['created_posts'];
                    
                    if( $single->post_status == 'publish' )
                        ++$detailed_stats_array[$user->ID]['published_posts'];
                }
            }
            
            $detailed_stats_array[$user->ID]['still_to_do_items'] = $detailed_stats_array[$user->ID]['assigned_items'] - $detailed_stats_array[$user->ID]['assigned_marked_as_done_items'];
        }
        
        posts_to_do_list_print_functions::posts_to_do_list_print_detailed_stats( $detailed_stats_array );
    }
    
    //Given a numerical representation of the post priority, returns the correspondant textual one
    function posts_to_do_list_get_textual_priority( $numeric_priority ) {
        //Need to store them as numbers in the db cause otherwise it would not be possible to sort for item_priority DESC
        $priority_values_to_text = array(
            '1' => 'Lower than hell',
            '2' => 'Lowest',
            '3' => 'Low',
            '4' => 'Normal',
            '5' => 'High',
            '6' => 'Highest',
            '7' => 'A matter of life and death'
        );
        
        return $item_priority = $priority_values_to_text[$numeric_priority];
    }
    
    //When a new post is added and assigned to some user, send email to them with the details of the new item
    function posts_to_do_list_send_email_assignment( $insert_data ) {
        $blog_name      = get_bloginfo( 'name' );
        $blog_URL       = site_url();
        $email_receiver = get_userdata( $insert_data['item_author'] )->user_email; 
        $email_subject  = $blog_name.': new post assigned';
        $email_text     = 'You are receiving this email because you are a writer at <a href="'.$blog_URL.'" title="'.$blog_name.'">'.$blog_name.'</a> and a new post was added to the list of the to-do ones. And... guess what! You are the lucky winner of that new post to write: in fact, it was assigned to you. Following are details of that new item.<br /><br />
<strong><a href="'.$insert_data['item_url'].'" title="'.$insert_data['item_title'].'">'.$insert_data['item_title'].'</a></strong><br /><strong>Inserted</strong>: on '.date( $insert_data['item_timestamp'] ).' by '.$insert_data['item_adder'].'<br /><strong>Priority</strong>: '.self::posts_to_do_list_get_textual_priority( $insert_data['item_priority'] ).'<br /><strong>Keyword</strong>: '.$insert_data['item_keyword'].'<br /><strong>Notes</strong>: '.$insert_data['item_notes'].'<br /><br />
&rArr; &nbsp;<a href="'.admin_url().'post-new.php?post_title='.$insert_data['item_title'].'" title="Go to an already filled-in draft and start writing!">Write it!</a>';
                        
        if( strlen( $insert_data['item_url'] ) > 0 ) {
            $email_text .= ' &nbsp; &nbsp; &rArr; &nbsp;<a href="'.$insert_data['item_url'].'" title="Go to source" target="_blank">Go to source</a>';
        }
        
        $email_text     .= '<br /><br />Additional actions are available on the blog pages: head to the <a href="'.admin_url().'post-new.php" title="Go to a new blank post">new post page</a> or edit post one and look at the Posts To Do List box on the right, the items specifically assigned to you will be showed first and marked with an asterisk.<br /><br />
<span style="font-size: smaller">This message was generated automatically by the <a href="http://wordpress.org/extend/plugins/posts-to-do-list/" title="Posts To Do List plugin page">Posts To Do List plugin</a>. If these notification are unwanted, contact your administrator.</span>';
        $email_headers  = 'From: '.get_option('admin_email')."\r\n";
        $email_headers  .= 'Content-Type: text/html'."\r\n";
        
        
        mail( $email_receiver, $email_subject, $email_text, $email_headers );
    }
    
}

new posts_to_do_list_core;

?>