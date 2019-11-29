---
title: "BioHive Tech - Laravel Passport Introspection"
---

## Quick Setup

```bash
composer require biohivetech/laravel-introspection
```

## About this Package

This package provides an Introspection controller used to valid tokens that Laravel's Passport has issued. This controller should be used on the Laravel instance that also acts as a Passport OAuth server.

In addition to providing the introspection component, this package also provides the middleware and authentication guard similar to that of Passport.

One package, two functions.

## A Note on UUIDs and How-To Override

This package was designed for BioHive's specific needs. Therefore, it assumes that there is a 'share nothing' database implementation. Each application maintains a list of users that all can be linked back to a master authentication application.

### Some quick background on our usage

We utilize our own Socialite provider to authenticate users on our other applications with our master user identification service. This ID service also is the system that has Passport, issues oauth tokens and manages oauth clients.

With that said, we have the following `user` table schema in place on our non-ID applications (Resource servers):

```php
$table->bigIncrements('id');
$table->efficientUuid('uuid')->index();
$table->rememberToken();
$table->timestamps();
```

And on our central user authentication (Authorization server):

```php
$table->bigIncrements('id');
$table->efficientUuid('uuid')->index();
$table->string('name');
$table->string('password');
// additional columns etc
$table->rememberToken();
$table->timestamps();
```

We utilize [Michael Dyrynda's Laravel Efficient UUID](<https://github.com/michaeldyrynda/laravel-efficient-uuid>) package to provide our `efficientUuid` method on our database tables as well as his [Laravel Model UUID](<https://github.com/michaeldyrynda/laravel-model-uuid>) package to provide the type casting and model methods to lookup entries via UUID.

Since we use Socialite to authenticate users, we weren't able to use the built-in [Eloquent UserProvider's](<https://laravel.com/api/6.x/Illuminate/Auth/EloquentUserProvider.html>) methods to retrieve the user. Instead, we are instantiating the User model (as provided by `auth.providers.[guards.api.provider].model'`, which defaults to `App\User::class`) so that we can utilize the `User::whereUuid(...)` method.

The UUID is returned by the introspection controller so that the introspection guard knows what UUID to try and match the user against.

Example response from the `/oauth/introspect` endpoint:

```json
{
    "active": true,
    "scope": "user.read",
    "client_id": 4,
    "token_type": "access_token",
    "exp": 1606091154,
    "iat": 1574468754,
    "nbf": 1574468754,
    "sub": 1,
    "aud": 4,
    "jti": "702481dc66b64bd1eee41be4e20e2d3170ac509b6d47b3cab50d8fbef83f73d1b637080b5a0cdd47",
    "id": "11e17430-9710-4033-be5d-12e0d182f8f3",
    "username": "ReArmedHalo",
    "displayName": "(BioHive) Dustin Schreiber",
    "givenName": "Dustin",
    "familyName": "Schreiber"
}
```

### Overriding

You can override how the resource server fetches users by  defining a `findForIntrospect()` method on your user model:

```php
public function findForIntrospect($userId)
{
    return $this->where('username', $userId)->first();
}
```

This method should return the model for the user that matches the ID based on whatever criteria you want to use. In this case, we are matching based on the username. 

**NOTE:** We do not recommend utilizing a non-static, user-changeable field, the above is purely for an example.

For the Authorization Server, you will want to implement the `getIntrospectionUserId()` method that returns the unique user ID you are using to tie users on your resource servers to the identity on the authorization server. This ID will be returned in the introspection response as the `id` claim as shown above.

```php
public function getIntrospectionUserId()
{
    return $this->username;
}
```

If you override our default UUID lookup by utilizing the `getIntrospectionUserId()` method, you will need to  implement the corresponding `findForIntrospect()` method on the resource server's user model.