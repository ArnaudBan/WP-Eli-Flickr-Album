<?php
/*
 * Plugin Name: Eli Photo Flickr
 * Plugin URI: https://github.com/arnaudban
 * Description: Add Flickr Album to your WordPress site
 * Version: 1.0
 * Author: ArnaudBan
 * Author URI: http://www.arnaudban.me
 * License: GPL2
 *
 * Text Domain: eli-flickr-album
 * Domain Path: /languages
 *
 */

Class Eli_Flickr_Album{

    function __construct(){

        add_action('admin_menu', array( $this, 'admin_menu_action') );
        add_action('admin_init', array( $this, 'admin_init_action') );
        add_action('init', array( $this, 'init_action') );
        add_action('save_post', array( $this, 'save_post') );
        add_filter( 'the_content', array( $this, 'eli_flickr_add_photo_to_content') );
    }

    function init_action(){

        // Creat CPT Flickr Album
        $labels = array(
            'name'               => _x( 'Flickr Albums', 'post type general name', 'eli-flickr-album' ),
            'singular_name'      => _x( 'Flickr Album', 'post type singular name', 'eli-flickr-album' ),
            'menu_name'          => _x( 'Flickr Albums', 'admin menu', 'eli-flickr-album' ),
            'name_admin_bar'     => _x( 'Flickr Album', 'add new on admin bar', 'eli-flickr-album' ),
            'add_new'            => _x( 'Add New', 'Flickr Album', 'eli-flickr-album' ),
            'add_new_item'       => __( 'Add New Flickr Album', 'eli-flickr-album' ),
            'new_item'           => __( 'New Flickr Album', 'eli-flickr-album' ),
            'edit_item'          => __( 'Edit Flickr Album', 'eli-flickr-album' ),
            'view_item'          => __( 'View Flickr Album', 'eli-flickr-album' ),
            'all_items'          => __( 'All Flickr Albums', 'eli-flickr-album' ),
            'search_items'       => __( 'Search Flickr Albums', 'eli-flickr-album' ),
            'parent_item_colon'  => __( 'Parent Flickr Albums:', 'eli-flickr-album' ),
            'not_found'          => __( 'No Flickr Albums found.', 'eli-flickr-album' ),
            'not_found_in_trash' => __( 'No Flickr Albums found in Trash.', 'eli-flickr-album' )
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'menu_icon'          => 'dashicons-images-alt',
            'register_meta_box_cb'  => array( $this, 'add_flickr_album_metabox' ),
            'rewrite'            => array( 'slug' => __('album', 'eli-flickr-album' ) ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' )
        );

        register_post_type( 'flickr-album', $args );
    }

    function admin_menu_action(){

        add_options_page( __('Eli Flickr Album option', 'eli-flickr-album' ), __('Eli Flickr Album', 'eli-flickr-album' ), 'manage_options', 'eli-flickr-album-option', array( $this,'eli_flickr_album_option_page') );
    }

    function eli_flickr_album_option_page(){
        ?>
        <div class="wrap">

                <h2><?php _e('Eli Flickr Album option', 'eli-flickr-album' ) ?></h2>

                <form action="options.php" method="post">
                    <?php
                    //Output nonce, action, and option_page
                    settings_fields('eli_options_group');

                    //Prints out all settings sections added to a particular settings page
                    do_settings_sections('eli_options_page');

                    submit_button();
                    ?>
                </form>

            </div>
        <?php
    }

    function admin_init_action(){

        //register_setting( $option_group, $option_name, $sanitize_callback );
        register_setting('eli_options_group', 'eli_consumer_key' );
        register_setting('eli_options_group', 'eli_consumer_secret' );
        register_setting('eli_options_group', 'eli_app_authorize' );

        //add_settings_section( $id, $title, $callback, $page );
        add_settings_section('eli_flickr_connection_section', __('Flickr connection', 'eli-flickr-album'), array( $this, 'eli_flickr_connection_section_text'), 'eli_options_page');


        //Register a settings field to a settings page and section.
        //add_settings_field( $id, $title, $callback, $page, $section, $args );
        add_settings_field('eli_consumer_key', 'API key', array( $this, 'eli_option_text' ), 'eli_options_page', 'eli_flickr_connection_section', array( 'option_name' => 'eli_consumer_key' ));
        add_settings_field('eli_consumer_secret', 'API secret', array( $this, 'eli_option_text' ), 'eli_options_page', 'eli_flickr_connection_section', array( 'option_name' => 'eli_consumer_secret' ));
        add_settings_field('eli_app_authorize', 'Flickr Authorize', array( $this, 'eli_option_text_flickr_authorize' ), 'eli_options_page', 'eli_flickr_connection_section' );

    }


    function eli_flickr_connection_section_text(){
        echo '';
    }

    function eli_option_text( $options ){

        $option_name = isset( $options['option_name'] ) && ! empty( $options['option_name'] ) ? $options['option_name'] : '';

        $option_value = get_option( $option_name );

        ?>
        <input type='text' name='<?php echo esc_attr( $option_name ) ?>' value='<?php echo esc_attr( $option_value ) ?>' class='widefat' />
        <?php

    }

    function eli_option_text_flickr_authorize(){

        // If the autorization is not set yet check if wee can propose it
        $eli_oauth_token = get_option( 'eli_oauth_token' );

        $classes = 'button';
        $href = '#';

        if( $eli_oauth_token ){

            $classes .= ' disabled';

        } else {

            $flickr_call = new Eli_Flickr_Call();

            // Are We back from authorization ?
            if( isset( $_GET['oauth_token']) &&
                isset( $_GET['oauth_verifier'])
                ){

                $oauth_token = sanitize_text_field( $_GET['oauth_token'] );
                $oauth_verifier = sanitize_text_field( $_GET['oauth_verifier'] );

                // Get the access token
                $access_token = $flickr_call->flickr_access_token( $oauth_token, $oauth_verifier );

                if( isset( $access_token['oauth_token'] ) &&
                    isset( $access_token['oauth_token_secret'] )
                    ){
                        $oauth_token = sanitize_text_field( $access_token['oauth_token'] );
                        $oauth_token_secret = sanitize_text_field( $access_token['oauth_token_secret'] );

                        update_option( 'eli_oauth_token', $oauth_token );
                        update_option( 'eli_token_secret', $oauth_token_secret );
                    }

            } else {

                // We are not back from authorization
                // Get the auth_token
                $request_token = $flickr_call->flickr_request_token();

                if( isset( $request_token['oauth_callback_confirmed'] ) &&
                    isset( $request_token['oauth_token'] ) &&
                    ! empty( $request_token['oauth_token'] ) ){

                    $oauth_token = $request_token['oauth_token'];
                    $oauth_token_secret = $request_token['oauth_token_secret'];

                    update_option( 'eli_token_secret', $oauth_token_secret );

                    $href = "https://www.flickr.com/services/oauth/authorize?oauth_token=$oauth_token";
                }
            }

        }

        ?>
        <a class="<?php echo $classes; ?>" href="<?php echo $href; ?>"><?php _e('Authorize your application', 'eli-flickr-album'); ?></a>
        <?php
    }

    function add_flickr_album_metabox(){

        add_meta_box( 'eli-flickr-album', __('Flickr Album', 'eli-flickr-album'), array( $this, 'flickr_album_metabox_content'), 'flickr-album', 'side', 'default' );
    }

    function flickr_album_metabox_content( $post ){

        $album_id = get_post_meta( $post->ID , 'flickr_album_id', true );

        $flickr_call = new Eli_Flickr_Call();
        $my_albums = $flickr_call->flickr_get_my_album();

        if( $my_albums->stat == 'ok' ){

            wp_nonce_field( 'flickr_album_metabox_nonce', 'flickr_album_metabox_nonce_' . $post->ID );

            echo '<select name="flickr_album_id">';
            foreach ($my_albums->photosets->photoset as $album) {

                echo "<option value='{$album->id}' ". selected( $album_id, $album->id, false ).">{$album->title->_content}</option>";
            }
            echo '</select>';
        }

    }

    function save_post( $post_id ){

        if( check_admin_referer( 'flickr_album_metabox_nonce', 'flickr_album_metabox_nonce_' . $post_id ) &&
            current_user_can( 'edit_post', $post_id ) ){

            $album_id = isset( $_POST['flickr_album_id'] ) ? sanitize_text_field( $_POST['flickr_album_id'] ) : '';

            update_post_meta( $post_id, 'flickr_album_id', $album_id );
        }

    }

    function eli_flickr_add_photo_to_content( $content ){
        if( is_singular( 'flickr-album' ) ){

            $album_id = get_post_meta( get_the_ID() , 'flickr_album_id', true );

            $flickr_call = new Eli_Flickr_Call();
            $photos = $flickr_call->flickr_get_album_photos( $album_id );

            $content .= "<p>total : {$photos->photoset->total}</p>";
            $content .= "<div class='eli-flickr-album-wrapper'>";

            foreach ($photos->photoset->photo as $photo) {
                $photo_url = $this->get_flikr_photo_url( $photo->id, $photo->secret, $photo->server, $photo->farm  );

                $content .= "<img src='$photo_url'/>";
            }

            $content .= "</div>";
        }

        return $content;
    }

    private function get_flikr_photo_url( $photo_id, $secret_id, $server_id, $farm_id, $size = 't' ){
        return "https://farm$farm_id.staticflickr.com/$server_id/{$photo_id}_{$secret_id}_{$size}.jpg";
    }

}

/* Initialise outselves */
add_action('plugins_loaded', create_function('','new Eli_Flickr_Album();'));

require_once( dirname( __FILE__ ) . '/flickr-call.class.php' );

