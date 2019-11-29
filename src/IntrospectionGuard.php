<?php

namespace BioHiveTech\Introspection;

use Firebase\JWT\JWT;
use GuzzleHttp\client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Container\Container;
use Laravel\Passport\TransientToken;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\Middleware\EncryptCookies;
use BioHiveTech\Introspection\Introspection;

class IntrospectionGuard
{

    /**
     * The user provider implementation.
     *
     * @var \Illuminate\Contracts\Auth\UserProvider
     */
    protected $provider;

    /**
     * The encrypter implementation.
     *
     * @var \Illuminate\Contracts\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * Create a new token guard instance.
     *
     * @param  \Illuminate\Contracts\Auth\UserProvider  $provider
     * @param  \Illuminate\Contracts\Encryption\Encrypter  $encrypter
     * @return void
     */
    public function __construct(
        UserProvider $provider,
        Encrypter $encrypter
    )
    {
        $this->provider = $provider;
        $this->encrypter = $encrypter;
    }

    /**
     * Get the user for the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function user(Request $request)
    {
        if ($request->bearerToken()) {
            if (! $request = Introspection::introspect($request)) {
                return; // Returns unauthenticated error
            }
            
            // If we have a user_id we are probably using authorization code, bind token to user
            if ($request->attributes->get('oauth_user_id')) {
                $user = \App\User::whereUuid($request->attributes->get('oauth_user_id'))->first();

                if (! $user) {
                    return;
                }

                $token = new Token([
                    'client' => $request->attributes->get('oauth_client_id'),
                    'scopes' => explode(' ', $request->attributes->get('oauth_scopes')),
                    'expires_at' => $request->attributes->get('oauth_expires_at'),
                ]);
    
                return $token ? $user->withAccessToken($token) : null;
            }
        } elseif ($request->cookie(Introspection::cookie())) {
            $token = (array) JWT::decode(
                $this->encrypter->decrypt($request->cookie(Introspection::cookie()), false),
                $this->encrypter->getKey(),
                ['HS256']
            );

            if (! $this->validCsrf($token, $request) || time() >= $token['expiry']) {
                return;
            }

            if (! $token) {
                return;
            }

            if ($user = \App\User::whereUuid($token['sub'])->first()) {
                return $user->withAccessToken(new \Laravel\Passport\TransientToken);
            }
        }
    }

    /**
     * Determine if the CSRF / header are valid and match.
     *
     * @param  array  $token
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function validCsrf($token, $request)
    {
        return isset($token['csrf']) && hash_equals(
            $token['csrf'], (string) $this->getTokenFromRequest($request)
        );
    }

    /**
     * Get the CSRF token from the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string
     */
    protected function getTokenFromRequest($request)
    {
        $token = $request->header('X-CSRF-TOKEN');
        if (! $token && $header = $request->header('X-XSRF-TOKEN')) {
            $token = $this->encrypter->decrypt($header, static::serialized());
        }
        return $token;
    }

    /**
     * Determine if the cookie contents should be serialized.
     *
     * @return bool
     */
    public static function serialized()
    {
        return EncryptCookies::serialized('XSRF-TOKEN');
    }

}