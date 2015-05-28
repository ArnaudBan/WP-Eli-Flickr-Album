<?php

Class Eli_Flickr_Call{

    private $consumer_key;
    private $consumer_secret;

    function __construct(){

        $this->consumer_key     = get_option( 'eli_consumer_key', '' );
        $this->consumer_secret  = get_option( 'eli_consumer_secret', '' );
        $this->oauth_token      = get_option( 'eli_oauth_token', '' );
        $this->token_secret     = get_option( 'eli_token_secret', '' );
    }

    private function flickr_api_call( $add_parameters, $transient = false, $url = 'https://api.flickr.com/services/rest' ){

        $response = false;

        if( $transient ){

            $transient_name = 'eli_flickr_' . md5( serialize( $add_parameters ) );

            $response = get_transient( $transient_name );
        }


        if( ! $response ){

            $parameters = array_merge( array(
                    'oauth_consumer_key'    => $this->consumer_key,
                    'oauth_nonce'           => uniqid(),
                    'oauth_signature_method'=> 'HMAC-SHA1',
                    'oauth_token'           => $this->oauth_urlencode( $this->oauth_token ),
                    'oauth_timestamp'       => time(),
                    'oauth_version'         => '1.0',
                    'nojsoncallback'        => '1',
                    'format'                => 'json',
                ), $add_parameters );

            $signature = $this->signParams( $url, $parameters );

            $parameters['oauth_signature'] = $this->oauth_urlencode( $signature );

            $url_request_token = add_query_arg( $parameters, $url );

            $wp_response = wp_remote_get( $url_request_token );

            if( ! is_wp_error( $wp_response ) ){

                $response_body = wp_remote_retrieve_body( $wp_response );

                $response = json_decode( $response_body );

                if( $transient ){

                    set_transient( $transient_name, $response, HOUR_IN_SECONDS );
                }

            }
        }

        return $response;

    }

    function flickr_request_token(){

        $request_token = false;

        $url = 'https://www.flickr.com/services/oauth/request_token';

        $parameters = array(
                'oauth_callback'        => $this->oauth_urlencode( admin_url( 'options-general.php?page=eli-flickr-album-option' ) ),
                'oauth_consumer_key'    => $this->consumer_key,
                'oauth_nonce'           => uniqid(),
                'oauth_signature_method'=> 'HMAC-SHA1',
                'oauth_timestamp'       => time(),
                'oauth_version'         => '1.0',
            );

        $signature = $this->signParams( $url, $parameters );

        $parameters['oauth_signature'] = $this->oauth_urlencode( $signature );

        $url_request_token = add_query_arg( $parameters, $url );

        $response = wp_remote_get( $url_request_token );

        if( ! is_wp_error( $response ) ){

            $response_body = wp_remote_retrieve_body( $response );

            $request_token = $this->explode_query_args( $response_body );

        }

        return $request_token;
    }

    function flickr_access_token( $oauth_token, $oauth_verifier ){

        $access_token = false;

        $url = 'https://www.flickr.com/services/oauth/access_token';

        $parameters = array(
                'oauth_consumer_key'    => $this->consumer_key,
                'oauth_nonce'           => uniqid(),
                'oauth_signature_method'=> 'HMAC-SHA1',
                'oauth_token'           => $this->oauth_urlencode( $oauth_token ),
                'oauth_timestamp'       => time(),
                'oauth_version'         => '1.0',
                'oauth_verifier'        => $this->oauth_urlencode( $oauth_verifier ),
            );

        $signature = $this->signParams( $url, $parameters );

        $parameters['oauth_signature'] = $this->oauth_urlencode( $signature );

        $url_request_token = add_query_arg( $parameters, $url );

        $response = wp_remote_get( $url_request_token );

        if( ! is_wp_error( $response ) ){

            $response_body = wp_remote_retrieve_body( $response );

            $access_token = $this->explode_query_args( $response_body );

        }

        return $access_token;

    }

    function flickr_get_my_album(){

        $parameters = array( 'method' => 'flickr.photosets.getList' );


        return $this->flickr_api_call( $parameters, true );

    }

    function flickr_get_album_photos( $photoset_id, $page = 1, $per_page = 100 ){

        $parameters = array(
                'method'                => 'flickr.photosets.getPhotos',
                'photoset_id'           => $photoset_id,
                'privacy_filter'        => 4,
                'media'                 => 'photos',
                'extras'                => 'date_taken,tags',
                'per_page'              => $per_page,
                'page'                  => $page,
            );

        return $this->flickr_api_call( $parameters, true );

    }

    private function explode_query_args( $response_body ){

        $query_args = array();

        $response_body_explode = explode('&', $response_body);

        if( is_array( $response_body_explode ) ){

            foreach( $response_body_explode as $arg ) {
                $arg_explode = explode('=', $arg);

                $query_args[ $arg_explode[0] ] = $arg_explode[1];
            }
        }

        return $query_args;
    }

    function oauth_urlencode ( $s )
    {
        return str_replace('%7E', '~', rawurlencode($s) );
    }

    /**
     * Create a signed signature of the parameters.
     *
     * @param   string  $url
     * @param   array   $params
     * @return  string
     */
    private function signParams( $url, $params )
    {

        ksort($params);
        $parameters_string_signature = '';

        foreach ($params as $key => $value) {
            $parameters_string_signature .= $key . '=' . $value . '&';
        }
        $parameters_string_signature = substr($parameters_string_signature, 0, -1);
        $parameters_string_signature = $this->oauth_urlencode( $parameters_string_signature );

        $baseString = 'GET&' . $this->oauth_urlencode ( $url ) . '&' . $parameters_string_signature;

        $key = $this->consumer_secret . '&' . $this->token_secret;

        $signature = base64_encode(hash_hmac('sha1', $baseString, $key, true));

        return $signature;
    }
}
