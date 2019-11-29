<?php

namespace App\Http\Middleware;

use Closure;
use Guzzle\Http\Exception\RequestException;
use GuzzleHttp\Exception\RequestException as GuzzleHttpRequestException;

class Introspect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $client = new \GuzzleHttp\Client();

        // Get token via Client Credentials
        $response = $client->post('http://hiveid.test/oauth/token', [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => 3,
                'client_secret' => 'mOkXaimbYtbDigmh2VFHoMPss9atzXwzZQfnW1dm',
                'scope' => '*'
            ]
        ]);

        $decoded = json_decode($response->getBody(), true);
        if ($decoded['access_token']) {
            $accessToken = $decoded['access_token'];
        } else {
            return response()->json([
                'error' => 'Failed to obtain access token from upstream server.'
            ], 500);
        }

        // Do introspection
        try {
            $response = $client->post('http://hiveid.test/api/oauth/introspection', [
                'form_params' => [
                    'token_type_hint' => 'access_token',
                    'token' => $request->bearerToken()
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);
        } catch (GuzzleHttpRequestException $exception) {
            dd($exception->getResponse()->getBody()->getContents());
        }

        $decoded = json_decode($response->getBody(), true);
        if (isset($decoded['active']) && $decoded['active']) {
            $request->headers->add([
                'oauth_access_token_id' => $decoded['jti'],
                'oauth_client_id' => $decoded['aud'],
                'oauth_hive_uuid' => $decoded['id'],
                'oauth_scopes' => $decoded['scope'],
            ]);
        } else {
            return response()->json([
                'error' => 'Invalid authentication'
            ], 401);
        }
        return $next($request);
    }
}
