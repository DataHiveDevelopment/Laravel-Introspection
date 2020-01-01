<?php

namespace DataHiveDevelopment\Introspection\Http\Middleware;

use Closure;
use DataHiveDevelopment\Introspection\Token;
use DataHiveDevelopment\Introspection\Introspection;

abstract class CheckCredentials
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$scopes
     * @return mixed
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$scopes)
    {
        $this->validate($request, $scopes);

        return $next($request);
    }

    /**
     * Validate the scopes and token on the incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  array  $scopes
     * @return void
     */
    protected function validate($request, $scopes)
    {
        $request = Introspection::doIntrospection($request);
        
        $token = new Token([
            'client' => $request->attributes->get('oauth_client_id'),
            'scopes' => explode(' ', $request->attributes->get('oauth_scopes')),
            'expires_at' => $request->attributes->get('oauth_expires_at'),
        ]);

        $this->validateCredentials($token);
        
        $this->validateScopes($token, $scopes);
    }

    /**
     * Validate token credentials.
     *
     * @param  \DataHiveDevelopment\Introspection\Token  $token
     * @return void
     */
    abstract protected function validateCredentials($token);

    /**
     * Validate token credentials.
     *
     * @param  \DataHiveDevelopment\Introspection\Token  $token
     * @param  array  $scopes
     * @return void
     */
    abstract protected function validateScopes($token, $scopes);
}