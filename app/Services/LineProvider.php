<?php

namespace App\Services;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use Illuminate\Http\Request;

class LineProvider extends AbstractProvider
{
    /**
     * The Line OAuth base URL.
     */
    // Base URL used by AbstractProvider for token calls if it falls back to baseUrl
    // Set to api.line.me to guarantee the token host is correct even if parent logic is used
    protected $baseUrl = 'https://api.line.me';

    /**
     * The Line OAuth API version.
     */
    protected $version = 'v2.1';

    /**
     * The scopes being requested.
     */
    protected $scopes = ['profile', 'openid'];

    /**
     * The separating character for the requested scopes.
     */
    protected $scopeSeparator = ' ';

    /**
     * Get the authentication URL for the provider.
     */
    protected function getAuthUrl($state)
    {
        // Authorize must hit access.line.me
        $authBase = 'https://access.line.me';
        return $this->buildAuthUrlFromBase($authBase . '/oauth2/' . $this->version . '/authorize', $state);
    }

    /**
     * Get the token URL for the provider.
     */
    protected function getTokenUrl()
    {
        // Explicit token endpoint on api.line.me
        return 'https://api.line.me' . '/oauth2/' . $this->version . '/token';
    }

    /**
     * Get the raw user for the given access token.
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://api.line.me/v2/profile', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Map the raw user array to a Socialite User instance.
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['userId'] ?? $user['id'] ?? null,
            'nickname' => $user['displayName'] ?? null,
            'name' => $user['displayName'] ?? null,
            'email' => $user['email'] ?? null,
            'avatar' => $user['pictureUrl'] ?? null,
        ]);
    }

    /**
     * Get the default fields for the token request.
     */
    protected function getTokenFields($code)
    {
        // LINE requires client_id and client_secret in POST body for token
        return [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUrl,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];
    }
}
