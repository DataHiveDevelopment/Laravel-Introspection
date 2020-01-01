<?php

namespace DataHiveDevelopment\Introspection;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Laravel\Passport\RouteRegistrar;
use Illuminate\Support\Facades\Route;

class Introspection {

    /**
     * The name for API token cookies.
     *
     * @var string
     */
    public static $cookie = 'laravel_token';

    public static function introspect(Request $request)
    {
        $client = new \GuzzleHttp\Client();

        // Get token via Client Credentials
        $response = $client->post(config('introspection.token_url'), [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => config('introspection.client_id'),
                'client_secret' => config('introspection.client_secret'),
                'scope' => config('introspection.token_scopes')
            ]
        ]);

        // Decode the JSON, let's make sure we have an access_token
        $decoded = json_decode($response->getBody(), true);
        if (isset($decoded['access_token'])) {
            $accessToken = $decoded['access_token'];
        } else {
            return;
        }

        try {
            $response = $client->post(config('introspection.introspection_endpoint'), [
                'form_params' => [
                    'token_type_hint' => 'access_token',
                    'token' => $request->bearerToken()
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);
        } catch (GuzzleHttpRequestException $e) {
            return;
        }

        $decoded = json_decode($response->getBody(), true);

        if (isset($decoded['active']) && $decoded['active']) {
            $request->attributes->add([
                'oauth_access_token_id' => $decoded['jti'],
                'oauth_client_id' => $decoded['aud'],
                'oauth_user_id' => isset($decoded['id']) ? $decoded['id'] : null,
                'oauth_expires_at' => $decoded['exp'],
                'oauth_scopes' => $decoded['scope'],
                'oauth_token' => $decoded,
            ]);

            return $request;
        }
    }

    /**
     * Get or set the name for API token cookies.
     *
     * @param  string|null  $cookie
     * @return string|static
     */
    public static function cookie($cookie = null)
    {
        if (is_null($cookie)) {
            return static::$cookie;
        }

        static::$cookie = $cookie;
        
        return new static;
    }

    /**
     * Binds the Introspection routes into the controller.
     *
     * @param  callable|null  $callback
     * @param  array  $options
     * @return void
     */
    public static function routes(array $options = [])
    {
        $defaultOptions = [
            'middleware' => 'client',
            'prefix' => 'oauth',
            'namespace' => '\DataHiveDevelopment\Introspection\Http\Controllers',
        ];

        $options = array_merge($defaultOptions, $options);
        
        Route::group($options, function ($router) {
            $router->post('/introspect', [
                'uses' => 'IntrospectionController@introspectToken',
                'as' => 'introspection.introspect',
            ]);
        });
    }
}