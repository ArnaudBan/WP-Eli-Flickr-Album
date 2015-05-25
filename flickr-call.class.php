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
            error_log( print_r( $response_body, true ) );
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

        $my_albums = false;

        $url = 'https://api.flickr.com/services/rest';

        $parameters = array(
                'oauth_consumer_key'    => $this->consumer_key,
                'oauth_nonce'           => uniqid(),
                'oauth_signature_method'=> 'HMAC-SHA1',
                'oauth_token'           => $this->oauth_urlencode( $this->oauth_token ),
                'oauth_timestamp'       => time(),
                'oauth_version'         => '1.0',
                'nojsoncallback'        => '1',
                'format'                => 'json',
                'method'                => 'flickr.photosets.getList',
            );

        $signature = $this->signParams( $url, $parameters );

        $parameters['oauth_signature'] = $this->oauth_urlencode( $signature );

        $url_request_token = add_query_arg( $parameters, $url );

        $response = wp_remote_get( $url_request_token );

        if( ! is_wp_error( $response ) ){

            $response_body = wp_remote_retrieve_body( $response );

            $my_albums = json_decode($response_body);

        }

        return $my_albums;

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
