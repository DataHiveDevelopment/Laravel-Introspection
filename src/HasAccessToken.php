<?php

namespace DataHiveDevelopment\Introspection;

trait HasAccessToken
{
    /**
     * The current access token for the authentication user.
     *
     * @var \DataHiveDevelopment\Introspection\Token
     */
    protected $accessToken;

    /**
     * Get the current access token being used by the user.
     *
     * @return \DataHiveDevelopment\Introspection\Token|null
     */
    public function token()
    {
        return $this->accessToken;
    }

    /**
     * Determine if the current API token has a given scope.
     *
     * @param  string  $scope
     * @return bool
     */
    public function tokenCan($scope)
    {
        return $this->accessToken ? $this->accessToken->can($scope) : false;
    }

    /**
     * Set the current access token for the user.
     *
     * @param  \DataHiveDevelopment\Introspection\Token  $accessToken
     * @return $this
     */
    public function withAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }
}
