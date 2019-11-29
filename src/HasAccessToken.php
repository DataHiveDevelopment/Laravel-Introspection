<?php

namespace BioHiveTech\Introspection;

trait HasAccessToken {

    /**
     * The current access token for the authentication user.
     *
     * @var \BioHiveTech\Introspection\Token
     */
    protected $accessToken;

    /**
     * Get the current access token being used by the user.
     *
     * @return \BioHiveTech\Introspection\Token|null
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
     * @param  \BioHiveTech\Introspection\Token  $accessToken
     * @return $this
     */
    public function withAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
        return $this;
    }
    
}