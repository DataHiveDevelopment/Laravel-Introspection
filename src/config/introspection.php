<?php

return [

    /**
     * Token URL should be set to the endpoint on the introspection server that we can use client credentials with.
     * Token Scopes is any scope(s), if any, that may be required to do introspection by the OAuth server
     */
    'token_url' => env('INTROSPECTION_TOKEN_URL'),
    'token_scopes' => env('INTROSPECTION_TOKEN_SCOPES', '*'),

    /**
     * The introspection URL on the OAuth server (e.g: https://api.example.com/introspect)
     */
    'introspection_endpoint' => env('INTROSPECTION_ENDPOINT'),
    
    /**
     * Client Credentials from the OAuth server that will be used to authenticate with the introspection endpoint
     */
    'client_id' => env('INTROSPECTION_CLIENT_ID'),
    'client_secret' => env('INTROSPECTION_CLIENT_SECRET')
]

?>