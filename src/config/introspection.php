<?php

return [

    /**
     * Token URL should be set to the endpoint on the introspection server that we can use client credentials with.
     * Token Scopes is any scope(s), if any, that may be required to do introspection by the OAuth server
     */
    'token_url' => env('introspection_token_url'),
    'token_scopes' => env('introspection_token_scopes'),

    /**
     * The introspection URL on the OAuth server (e.g: https://api.example.com/introspect)
     */
    'introspection_endpoint' => env('introspection_endpoint'),
    
    /**
     * Client Credentials from the OAuth server that will be used to authenticate with the introspection endpoint
     */
    'client_id' => env('introspection_client_id'),
    'client_secret' => env('introspection_client_secret')
]

?>