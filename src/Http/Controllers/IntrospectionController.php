<?php

namespace DataHiveDevelopment\Introspection\Http\Controllers;

use Lcobucci\JWT\Token;
use Lcobucci\JWT\Parser;
use Illuminate\Http\Request;
use Laravel\Passport\Passport;
use Lcobucci\JWT\ValidationData;
use Illuminate\Http\JsonResponse;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Laravel\Passport\ClientRepository;
use Psr\Http\Message\ResponseInterface;
use League\OAuth2\Server\ResourceServer;
use Laravel\Passport\Token as PassportToken;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\Exception\OAuthServerException;

class IntrospectionController
{
    /**
	 * @var \Lcobucci\JWT\Parser
	 */
    private $jwt;

	/**
	 * @var \League\OAuth2\Server\ResourceServer
	 */
    private $resourceServer;

	/**
	 * @var \Laravel\Passport\Bridge\AccessTokenRepository
	 */
    private $accessTokenRepository;

	/**
	 * @var \Laravel\Passport\ClientRepository
	 */
    private $clientRepository;

	/**
	 * Setup private variables
	 *
	 * @param \Lcobucci\JWT\Parser $jwt
	 * @param \League\OAuth2\Server\ResourceServer $resourceServer
	 * @param \Laravel\Passport\Bridge\AccessTokenRepository $accessTokenRepository
	 * @param \Laravel\Passport\ClientRepository
	 */
	public function __construct(
		Parser $jwt,
		ResourceServer $resourceServer,
		AccessTokenRepository $accessTokenRepository,
		ClientRepository $clientRepository
    )
	{
		$this->jwt = $jwt;
		$this->resourceServer = $resourceServer;
		$this->accessTokenRepository = $accessTokenRepository;
		$this->clientRepository = $clientRepository;
    }

    /**
	 * Setup private variables
	 *
	 * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function introspectToken(Request $request)
	{
        try {
            if (! $this->validateIntrospectingClient($request)) {
                return $this->inactiveResponse();
            }

            // INTROSPECTION TOKEN (Passed in body)
            // Validation - Let's make sure we were actually given a token to introspect
            // TODO: Move to a Form Request? Probably if I make this it's own package
            if (! $request->token) {
                return $this->inactiveResponse();
            }

            // Parse to Lcobucci\JWT\Token
            $token = $this->jwt->parse($request->token);

            $publicKey = 'file://' . Passport::keyPath('oauth-public.key');

            // Validation Data for $token->validate() method
            $data = new ValidationData();
            $data->setCurrentTime(time());
            $data->setAudience($token->getClaim('aud'));
            $data->setSubject($token->getClaim('sub'));

            if (
                !$token->verify(new Sha256(), $publicKey) || // Verify token
                !$token->validate($data) || // Validate token data
                $token->isExpired() || // Is token expired?
                $this->accessTokenRepository->isAccessTokenRevoked($token->getClaim('jti')) || // Has the token been revoked
                $this->clientRepository->revoked($token->getClaim('aud')) // Has the client that requested the token been revoked
            )
            {
                // If any of the above are false, we should return an inactive resposne
                return $this->inactiveResponse();
            }

            // Base response, may add more data if there is an associated subject/user
            $response = [
                'active' => true,
                'scope' => trim(implode(' ', $token->getClaim('scopes'))),
                'client_id' => intval($token->getClaim('aud')),
                'token_type' => 'access_token',
                'exp' => intval($token->getClaim('exp')),
                'iat' => intval($token->getClaim('iat')),
                'nbf' => intval($token->getClaim('nbf')),
                'sub' => intval($token->getClaim('sub')),
                'aud' => intval($token->getClaim('aud')),
                'jti' => $token->getClaim('jti'),
            ];

            // If we have an subject, fetch the user to return additional data
            if ($token->getClaim('sub')) {
                $userModel = config('auth.providers.users.model');
                $user = (new $userModel)->findOrFail($token->getClaim('sub'));
                if (method_exists($user, 'getIntrospectionUserId')) {
                    $response['id'] = $user->getIntrospectionUserId();
                } else {
                    $response['id'] = $user->uuid;
                }
            }

            return response()->json($response, 200);
        } catch (\Exception $exception) {
            return $this->inactiveResponse();
        }
    }

    /**
     * Validates the client requesting introspection
     * 
     * @param Illuminate\Http\Request $request
     * @return bool
     */
    protected function validateIntrospectingClient(Request $request)
    {
        // AUTHORIZATION TOKEN (Passed in Authorization header)
        // Get the client that issued the token, it should be a first party, client credentials grant, OAuth client

        $tokenId = (new Parser())->parse($request->bearerToken())->getClaim('jti');
        if (! $client = PassportToken::find($tokenId)->client) {
            return false;
        }

        // This is commented out as it hasn't fully been determined how we are going to implement the first-party method
        /*if (! $client->firstParty()) {
            return false;
        }*/

        return true;
    }

    /**
     * Pre-defined response as part of the Introspection RFC
     * 
     * @return Illuminate\Http\JsonResponse
     */
    protected function inactiveResponse()
    {
        return response()->json([
            'active' => false
        ], 200);
    }
}
