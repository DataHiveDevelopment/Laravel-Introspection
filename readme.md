# DataHive Development - Laravel Passport Introspection

## Heads up

I will be splitting this repository into two parts to separate out the components for the authorization and resource servers. This package will remain, probably become archived, and two new packages created for the respective components. Advisories and links will be posted on this readme as necessary.

Install this package on **both** your resource and authorization server. Passport should also be installed and configured on your **authorization server**. Passport is a dependency of this package currently so it will get installed on your resource server but you do not need to configure it. Please use the methods outlined in this document in lieu of Passport on your resource servers.

Thanks :)

- <a href="#AboutThisPackage">About This Package</a>
- <a href="#Installing">Installing</a>
- <a href="#AuthorizationServer">Authorization Server</a>
- <a href="#ResourceServers">Resource Server(s)</a>
- <a href="#ProtectingRoutes">Protecting Routes</a>
    - <a href="#Middleware">Via Middleware</a>
    - <a href="#TokenScopes">Token Scopes</a>
        - <a href="#ScopesMiddleware">Scopes Middleware</a>
        - <a href="#ScopeMiddleware">Scope Middleware</a>
- <a href="#UUIDSetup">UUID Setup</a>
- <a href="#WhyUUIDs">A Note on UUIDs</a>
    - <a href="#Background">Quick Background on DataHive's Usage</a>
    - <a href="#Overriding">Overriding</a>
- <a href="#JavaScript">Consuming Your Resource Server's API With JavaScript</a>
    - <a href="#CSRF">CSRF Protection</a>

## <a name="AboutThisPackage">#</a> About This Package

This package provides an Introspection controller,  authentication guard and middleware needed to allow you to host a separate authorization and resource server.

One package, two functions.

## <a name="Installing">#</a> Installing

```bash
composer require datahivedevelopment/laravel-introspection
```

Install the package and the following the appropriate steps below depending on which server you are configuring.

If you want to use UUIDs as a unique identifier for your users across applications, as we do, follow the [UUID Setup](<#UUIDSetup>). You will perform this setup on the authorization server only!

## <a name="AuthorizationServer">#</a> Authorization Server

From <https://oauth2.thephpleague.com/terminology/>
> A server which issues access tokens after successfully authenticating a client and resource owner, and authorizing the request.

This is the server that runs Passport and is your central authority for user information and is responsible for validating access tokens passed to it from the resource servers.

First, if you haven't already, install Passport and perform the standard configuration according to the [official documentation](<https://laravel.com/docs/6.x/passport>). In our default configuration, you will want to enable the `Client Credentials` grant type. See the Passport documentation's [Client Credentials Grant](<https://laravel.com/docs/6.x/passport#client-credentials-grant-tokens>) section for instructions on adding the middleware.

Next, you should call the `Introspection::routes` method within the `boot` method of your `AuthServiceProvider` after your `Passport` calls.

`App\Providers\AuthServiceProvider.php`

```php
<?php

use DataHiveDevelopment\Introspection\Introspection;
// ...
public function boot()
{
    $this->registerPolicies();

    Passport::routes();
    Passport::tokensCan([
        'user.read' => '...',
        //...
    ]);

    Introspection::routes();
}
```

You can change the introspection endpoint prefix from the default `/oauth` by passing the option to `Introspection::routes` call:

```php
Introspection::routes([
    'prefix' => '/apiauth'
]);
```

In the above example, the introspection endpoint would become `https://myauthserver.test/apiauth/introspect`.

## <a name="ResourceServers">#</a> Resource Server(s)

From <https://oauth2.thephpleague.com/terminology/>
> A server which sits in front of protected resources (for example “tweets”, users’ photos, or personal data) and is capable of accepting and responding to protected resource requests using access tokens.

In your `config/auth.php` configuration file, you should set the `driver` option of the `api` authentication guard to `introspect`.

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],

    'api' => [
        'driver' => 'introspect',
        'provider' => 'users',
    ],
],
```

Add the following to your `.env` file:

```text
INTROSPECTION_TOKEN_URL=https://authserver.test/oauth/token
INTROSPECTION_TOKEN_SCOPES="Introspect"
INTROSPECTION_ENDPOINT=https://authserver.test/oauth/introspect

INTROSPECTION_CLIENT_ID=
INTROSPECTION_CLIENT_SECRET=
```

The introspection endpoint currently only supports authentication via `Bearer` token by means of the Client Credentials grant. Generate a client credentials OAuth client using `php artisan passport:client --client` on your Passport server and enter the details into your `.env` file under the `INTROSPECTION_CLIENT_ID` and `INTROSPECTION_CLIENT_SECRET`.

The `INTROSPECTION_TOKEN_SCOPES` option should be a quoted, space separated list of scopes you want your introspection client to use when talking with the `INTROSPECTION_ENDPOINT`.

If not defined or left blank, then Passport will assume you asked for no scopes. I recommend that you create and use an `Introspect` scope and assign that to the introspection route but this is entirely optional and depends on your usage.

For example, DataHive currently only issues Client Credentials grant type to internal applications and we protect the introspection route, by default, using the `client` middleware. Therefore, we don't need additional verification to protect the route.

You will need to modify the `Introspection::routes` call on your authorization server if you want to use a scope to protect the route. Below, we are using the Passport `scope` middleware in the following example:

```php
Introspection::routes([
    'middleware' => [
        'client',
        'scope:Introspect'
    ]
]);
```

I recommend leaving the `client` middleware in place unless you implement some other authentication method. See the [OAuth Introspection RFC](<https://tools.ietf.org/html/rfc7662#section-4>) for details on protecting the introspection endpoint.

### Passport Trait
As a reminder, add the `use HasAccessToken;` trait to your User model as per the <a href="https://laravel.com/docs/6.x/passport">Passport documentation</a>.

## <a name="ProtectingRoutes">#</a> Protecting Routes

The following would be performed on your resource server(s).

### <a name="Middleware">#</a> Middleware

This package include our own Passport style [authentication guard](<https://laravel.com/docs/6.x/authentication#adding-custom-guards>) that will validate access tokens on incoming requests. Once you have configured the `api` guard to use the `introspect` driver, you only need to specify the `auth:api` middleware on any routes that require a valid access token, similar to that of Passport.

`routes/api.php`

```php
Route::get('/orders', function () {
    //
})->middleware('auth:api');
```

### <a name="TokenScopes">#</a> Token Scopes

We include scope checking middleware that works pretty much exactly like Passport's. In fact, the following documentation will probably sound really familiar if you have ever read the [Passport documentation](<https://laravel.com/docs/6.x/passport#checking-scopes>).

To get started, add the following middleware to the `$routeMiddleware` property of your `app/Http/Kernel.php` file:

```php
'scopes' => \DataHiveDevelopment\Introspection\Http\Middleware\CheckScopes::class,
'scope' => \DataHiveDevelopment\Introspection\Http\Middleware\CheckForAnyScope::class,
```

#### <a name="ScopesMiddleware">#</a> `Scopes` Middleware

```php
Route::get('/orders', function () {
    // Access token has both "check-status" and "place-orders" scopes...
})->middleware('scopes:check-status,place-orders');
```

#### <a name="ScopeMiddleware">#</a> `Scope` Middleware

```php
Route::get('/orders', function () {
    // Access token has either "check-status" or "place-orders" scope...
})->middleware('scope:check-status,place-orders');
```

## <a name="UUIDSetup">#</a> UUID Setup

Install the following packages on your authorization server:

```bash
composer require dyrynda/laravel-model-uuid
composer require dyrynda/laravel-efficient-uuid
```

Follow the package's documentation to implement the columns and traits on the necessary model. This package currently only support the default `uuid` column name so don't change anything in your database migrations when it comes to that. These packages will make it easy to store a binary form of an UUID in your database.

```php
$table->efficientUuid('uuid')->index();
```

- [Michael Dyrynda's Laravel Efficient UUID](<https://github.com/michaeldyrynda/laravel-efficient-uuid>)
- [Michael Dyrynda's Laravel Model UUID](<https://github.com/michaeldyrynda/laravel-model-uuid>)

## <a name="WhyUUIDs">#</a> Why UUIDs?

This package was designed for DataHive's specific needs. Therefore, it assumes that there is a 'share nothing' database implementation. Each application maintains a list of users that all can be linked back to a master authentication application.

### <a name="Background">#</a> Quick Background on DataHive's Usage

We utilize our own Socialite provider to authenticate users on our resource applications with our master user authentication application. This service also is the system that has Passport, issues OAuth tokens and manages OAuth clients.

We have the following `user` table schema in place on our resource applications::

```php
$table->bigIncrements('id');
$table->uuid('uuid')->index();
$table->rememberToken();
$table->timestamps();
```

> Yes, I'm aware the UUID column is not necessarily efficient at the moment. There is an unrelated issue I haven't solved when we are creating new users in the resource servers with the UUID packages so we aren't using the Michael's packages on resource servers at the moment.

And on our central user authentication server (OAuth Authorization Server):

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

Since we use Socialite to authenticate users, I wasn't able to use the built-in [Eloquent UserProvider's](<https://laravel.com/api/6.x/Illuminate/Auth/EloquentUserProvider.html>) methods to retrieve the user, since we want to use a custom column for finding users. Instead, I am instantiating the User model (as provided by `auth.providers.[guards.api.provider].model'`, which defaults to `App\User::class`), so that I can utilize the `User::whereUuid(...)` method.

The UUID is returned in the introspection controller's response so that the introspection guard knows what UUID to try and match the user against.

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
}
```

### <a name="Overriding">#</a> Overriding

You can override how the Resource server fetches users by  defining a `findForIntrospect()` method on your user model:

```php
public function findForIntrospect($userId)
{
    return $this->where('username', $userId)->first();
}
```

This method should return the model for the user that matches the ID based on whatever criteria you want to use. In this example, we are matching based on the username.

**NOTE:** I do not recommend utilizing a non-static, user-changeable field as this would make it impossible to match users across systems. The above is purely for an example.

For the Authorization server, you will want to implement the `getIntrospectionUserId()` method that returns the unique user ID you are using to tie users on your resource servers to the identity on the authorization server. This ID will be returned in the introspection response as the `id` claim as shown above.

```php
public function getIntrospectionUserId()
{
    return $this->username;
}
```

If you override our default UUID lookup by utilizing the `getIntrospectionUserId()` method, you will need to implement the corresponding `findForIntrospect()` method on the resource server's user model.

If you don't define the above methods, the package defaults to using the `whereUuid()` method so be sure you pick and implement the method you wish to utilize.

## <a name="JavaScript">#</a> Consuming Your Resource Server's API With JavaScript

Like Passport, I have a created a `CreateFreshApiToken` middleware that you can implement to make API calls from your front-end JavaScript. I recommend taking a read through the [official documentation](<https://laravel.com/docs/6.x/passport#consuming-your-api-with-javascript>) to better understand this usage.

This token does not work across resource servers currently and can only be used to call APIs published on the same resource server as the JavaScript call was made from.

All you need to do is to add the `CreateFreshApiToken` middleware to your `web` middleware group in your `app/Http/Kernel.php` file:

```php
'web' => [
    // Other middleware...
    \DataHiveDevelopment\Introspection\Http\Middleware\CreateFreshApiToken::class,
],
```

**NOTE:** Just like with Passport, you should ensure that the `CreateFreshApiToken` middleware is the last middleware listed in your stack.

You can customize the cookie name from the default `laravel_token` by using the `Introspection::cookie` method. Normally, you will want to call this from the `boot` method of your `AuthServiceProvider`:

```php
/**
 * Register any authentication / authorization services.
 *
 * @return void
 */
public function boot()
{
    $this->registerPolicies();

    Introspection::routes();

    Introspection::cookie('myapp_token');
}
```

### <a name="CSRF">#</a> CSRF Protection

We have implemented the same style cookie and CSRF protection that Passport has. The default Laravel JavaScript scaffolding includes an Axios instance, which will automatically use the encrypted `XSRF-TOKEN` cookie value to send a `X-XSRF-TOKEN` header on same-origin requests.
