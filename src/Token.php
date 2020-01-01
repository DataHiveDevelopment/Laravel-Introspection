<?php

namespace DataHiveDevelopment\Introspection;

use Illuminate\Database\Eloquent\Model;

class Token extends Model {

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'scopes' => 'array',
        'revoked' => 'bool',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'expires_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'client',
        'scopes',
        'expires_at',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the client ID that the token belongs to.
     *
     * @return integer
     */
    public function client()
    {
        return $this->client;
    }

    /**
     * Get the scopes this token was requested with.
     * 
     * @return array
     */
    public function scopes()
    {
        return $this->scopes;
    }

    /**
     * Determine if the token has a given scope.
     *
     * @param  string  $scope
     * @return bool
     */
    public function can($scope)
    {
        if (in_array('*', $this->scopes)) {
            return true;
        }

        if (array_key_exists($scope, array_flip($this->scopes))) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the token is missing a given scope.
     *
     * @param  string  $scope
     * @return bool
     */
    public function cant($scope)
    {
        return ! $this->can($scope);
    }

    public function user()
    {
        return $this->user;
    }
}