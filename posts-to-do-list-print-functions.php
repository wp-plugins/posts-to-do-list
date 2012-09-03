<?php

class posts_to_do_list_print_functions extends posts_to_do_list_core {
    
function posts_to_do_list_print_new_item_form() {
        global $wpdb; ?>
<div style="text-align: center;"><a href="#" title="Add new entry" id="posts_to_do_list_new">Add new post</a></div>
<div id="posts_to_do_list_new_content" style="display: none;">
    <br />
    <div id="new_item_form">
        <form method="post" id="new_item_form">
            <label for="new_item_URL" style="font-weight: bold; font-size: bigger;">New item URL</label><br />
            <input type="text" name="new_item_URL" id="new_item_URL" style="margin-bottom: 10px; width: 250px;" />
            <br />
            <label for="new_item_title" style="font-weight: bold; font-size: 1em;" id="new_item_title_label">New item title (mandatory)</label><br />
            <input type="text" name="new_item_title" id="new_item_title" style="margin-bottom: 10px; width: 250px;" />
            <br />
            <label for="new_item_keyword" style="font-weight: bold; font-size: bigger; margin-bottom: 50px;">New item keyword</label><br />
            <input type="text" name="new_item_keyword" id="new_item_keyword" style="margin-bottom: 10px; width: 250px;" />
            <br />
            <label for="new_item_notes" style="font-weight: bold; font-size: bigger;">New item notes</label><br />
            <textarea rows="2" name="new_item_notes" id="new_item_notes" style="margin-bottom: 10px; width: 250px;" /></textarea>
            <br />
            <label for="posts_to_do_list_author" style="font-weight: bold; font-size: bigger;">Assign post to specific user</label><br />
            <select id="posts_to_do_list_author">
                <option value="0">Unassigned</option>
            
        <?php $all_users = $wpdb->get_results( 'SELECT ID, display_name FROM '.$wpdb->users.' ORDER BY display_name ASC' );
        foreach( $all_users as $single ) {
            echo '<option value="'.$single->ID.'">'.stripslashes( $single->display_name ).'</option>';
        } ?>

            </select>
            <br />
            <br />
            <label for="item_priority" style="font-weight: bold; font-size: bigger;">Set priority</label><br />
            <select id="item_priority">
                <option value="7">A matter of life or death</option>
                <option value="6">Highest</option>
                <option value="5">High</option>
                <option value="4" selected="selected">Normal</option>
                <option value="3">Low</option>
                <option value="2">Lowest</option>
                <option value="1">Lower than hell</option>
            </select>
            <br />
            <div id="new_item_loading" style="display: none; float: right;"><img src="<?php echo parent::$posts_to_do_list_ajax_loader; ?>" alt="Loading..." title="Loading..." /></div>
            <p style="float: left; color: red;" id="new_item_error"></p>
            <span style="float: right;"><input type="submit" name="posts_to_do_list_new_submit" id="new_item_submit" value="Add" disabled="disabled" style="opacity: 0.50;" /></span>
        </form>
    </div>
</div>
    <?php }
    
    //After page data has been got and sorted, it is printed. If no rows are available, an information paragraph is shown
    function posts_to_do_list_print_page( $selected_data ) {
        
        if( count( $selected_data ) == 0 ) {
            echo '<p id="posts_to_do_list_no_posts_available">No posts available. Click on <em>Add new post</em> below to add a new item.</p>';
        } else {
            
            foreach( $selected_data as $single ) {
                posts_to_do_list_print_functions::posts_to_do_list_print_item( $single );
            }
        }
    }
    
    //Prints each single item
    function posts_to_do_list_print_item( $single ) {
        global $current_user;
        
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
        
        $item_title         = stripslashes( $single->item_title );
        $item_title_display = stripslashes( $single->item_title );
        $item_title_style   = ' style="text-decoration: none;"'; //This is the default style
        $item_url           = stripslashes( $single->item_url );
        $item_date_added    = date( 'd/m/Y', $single->item_timestamp );
        $item_adder         = get_userdata( $single->item_adder )->display_name;
        $item_keyword       = stripslashes( $single->item_keyword );
        $item_notes         = stripslashes( $single->item_notes );
        $item_author        = 'Unassigned'; //This is the default assigment
        $item_priority      = $priority_values_to_text[$single->item_priority];
        $item_done_details  = @unserialize( $single->item_done );
        
        //If item is assigned to current user, highlight that
        if( $current_user->ID == $single->item_author )
            $item_title_display = '* '.$item_title;
        
        //If post was assigned to someone, fetch their display name
        if( $single->item_author != 0 )
            $item_author    = get_userdata( $single->item_author )->display_name;
        
        //If post has already been marked as done, check the related checkbox and strike the title
        if( is_array( $item_done_details) ) {
            $done_checked       = ' checked="checked"';
            $item_title_style   = ' style="text-decoration: line-through;"';
        } ?>
        
        <div class="item_to_do" style="margin-bottom: 5px;">
            <strong><a class="item_to_do_link" href="<?php echo $item_url; ?>" title="<?php echo $item_title; ?>"<?php echo @$item_title_style; ?>><?php echo $item_title_display; ?></a></strong>
            <div class="item_to_do_content" style="display: none; margin-left: 10px;">
                <div class="content_left" style="margin-top: 2px;">
                    <strong>Inserted</strong>: on <?php echo $item_date_added; ?> by <?php echo $item_adder; ?><br />
                    <strong>Assigned to</strong>: <?php echo $item_author; ?><br />
                    <strong>Priority</strong>: <?php echo $item_priority; ?><br />
                    <strong>Keyword</strong>: <?php echo $item_keyword; ?><br />
                    <strong>Notes</strong>: <?php echo $item_notes; ?><br />
                
        <?php if( is_array( $item_done_details ) )
            echo '<p class="marked_as_done_p" style="font-style: italic;">This item was marked as done by '.get_userdata( $item_done_details['marker'] )->display_name.' on '.date( 'Y-m-d', $item_done_details['date'] ).'</p>'; ?>
                
                </div>
                <div style="margin-top: 8px;">
                    <div style="float: left; width: 50%;">
                        &rArr; &nbsp;<a href="<?php echo admin_url().'post-new.php?post_title='.$item_title; ?>" title="Go to an already filled-in draft and start writing!">Write it!</a><br />
                        
        <?php if( strlen( $item_url ) > 0 ) { ?>
                        
                        &rArr; &nbsp;<a href="<?php echo $item_url; ?>" title="Go to source" target="_blank">Go to source</a><br />
                        
        <?php } else { ?>
                        &rArr; &nbsp;No source
        <?php } ?>
                    </div>
                    <div style="float: right; width: 50%;">
                        &rArr; &nbsp;Mark as done <input type="checkbox" name="mark_as_done" class="mark_as_done" value="<?php echo $single->ID; ?>"<?php echo @$done_checked; ?> /><br />
                        
        <?php //If current user belong to a user role that can delete items, show link
        if( array_intersect( parent::$posts_to_do_list_options['permission_item_delete_roles'], get_userdata( $current_user->ID )->roles ) ) { ?>
                        
                        &rArr; &nbsp;<a href="#" class="item_delete" title="Delete this item" rel="<?php echo $single->ID; ?>">Delete item</a>
                        
        <?php } ?>
                        
                    </div>
                </div>  
            </div>
            <div style="clear: both;"></div>
        </div>
        
    <?php }
    
    function posts_to_do_list_print_detailed_stats( $detailed_stats_array ) {
        $n = 0;
        foreach( $detailed_stats_array as $single ) {
            
            if( $n % 2 == 0 )
                $row_alternate = ' class="alternate"';
            
            echo '<tr'.@$row_alternate.'>
            <td>'.$single['display_name'].'</td>
            <td>'.$single['added_items'].'</td>
            <td>'.$single['assigned_items'].'</td>
            <td>'.$single['total_marked_as_done_items'].'</td>
            <td>'.$single['assigned_marked_as_done_items'].'</td>
            <td>'.$single['still_to_do_items'].'</td>
            <td>'.$single['created_posts'].'</td>
            <td>'.$single['published_posts'].'</td>';
            
            unset( $row_alternate );
            ++$n;
        }
    }
    
    function posts_to_do_list_print_general_stats( $general_stats_array ) {
        echo '<tr>
            <td>'.$general_stats_array['added_items'].'</td>
            <td>'.$general_stats_array['marked_as_done_items'].'</td>
            <td>'.$general_stats_array['still_to_do_items'].'</td>
            <td>'.$general_stats_array['created_posts'].'</td>
            <td>'.$general_stats_array['published_posts'].'</td>';
    }
    
}

?>