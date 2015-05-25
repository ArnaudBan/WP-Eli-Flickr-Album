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

}

/* Initialise outselves */
add_action('plugins_loaded', create_function('','new Eli_Flickr_Album();'));

require_once( dirname( __FILE__ ) . '/flickr-call.class.php' );

