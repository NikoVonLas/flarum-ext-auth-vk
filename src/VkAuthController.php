<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Auth\Vk;

use Exception;
use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use VK\OAuth\VKOAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\Diactoros\Response\RedirectResponse;

class VkAuthController implements RequestHandlerInterface
{
    /**
     * @var ResponseFactory
     */
    protected $response;

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @param ResponseFactory $response
     * @param SettingsRepositoryInterface $settings
     * @param UrlGenerator $url
     */
    public function __construct(ResponseFactory $response, SettingsRepositoryInterface $settings, UrlGenerator $url)
    {
        $this->response = $response;
        $this->settings = $settings;
        $this->url = $url;
    }

    /**
     * @param Request $request
     * @return ResponseInterface
     * @throws \League\OAuth2\Client\Provider\Exception\FacebookProviderException
     * @throws Exception
     */
    public function handle(Request $request): ResponseInterface
    {
        $provider = new VK\OAuth\VKOAuth();
        $redirectUri = $this->url->to('forum')->route('auth.vk');
        $clientId = $this->settings->get('flarum-auth-vk.client_id');
        $clientSecret = $this->settings->get('flarum-auth-vk.client_secret');
        $display = VK\OAuth\VKOAuthDisplay::POPUP;
        $scope = [VK\OAuth\Scopes\VKOAuthUserScope::EMAIL];

        $authUrl = $provider->getAuthorizeUrl(VK\OAuth\VKOAuthResponseType::CODE, $clientId, $redirectUri, $display, $scope);

        $session = $request->getAttribute('session');
        $queryParams = $request->getQueryParams();

        $code = array_get($queryParams, 'code');
        $error = array_get($queryParams, 'error');

        if (!$code && !$error) {
            return new RedirectResponse($authUrl);
        } elseif ($error) {
          $errorMsg = array_get($queryParams, 'error_description');
          throw new \Exception($errorMsg);
        } elseif ($code) {
          $redirectUri = $this->url->to('forum')->route('auth.vk') . '&code=' . $code;
          $response = $oauth->getAccessToken($clientId, $clientSecret, $redirectUri, $code);
          $accessToken = $response['access_token'];

          $vk = new VK\Client\VKApiClient();
          $responseUser = $vk->users()->get($accessToken, [
              'user_ids'  => [$response['user_id']],
              'fields'    => ['photo_100', 'nickname', 'connections'],
          ]);

          if (!isset($responseUser[0])) {
            throw new \Exception('Error while get User info from VK');
          }

          $user = $responseUser[0];
          $username = !empty($responseUser['nickname']) ? $responseUser['nickname'] : ($responseUser['first_name'] . ' ' . $responseUser['last_name']);
          return $this->response->make(
              'vk', $response['user_id'],
              function (Registration $registration) use ($user) {
                  $registration
                      ->provideTrustedEmail($response['email'])
                      ->provideAvatar($responseUser['photo_100'])
                      ->suggest('username', $username)
                      ->setPayload($response);
              }
          );
        }
    }
}