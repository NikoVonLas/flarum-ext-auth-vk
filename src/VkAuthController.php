<?php

namespace Flarum\Auth\Vk;

use Exception;
use Flarum\Forum\Auth\Registration;
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use VK\OAuth\VKOAuth;
use VK\OAuth\VKOAuthDisplay;
use VK\OAuth\Scopes\VKOAuthUserScope;
use VK\OAuth\VKOAuthResponseType;
use VK\Client\VKApiClient;
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
     * @throws \VK\Exceptions\VKOAuthException
     * @throws \VK\Exceptions\VKApiExceptio
     * @throws Exception
     */
    public function handle(Request $request): ResponseInterface
    {
        $provider = new VKOAuth();
        $redirectUri = $this->url->to('forum')->route('auth.vk');
        $clientId = trim($this->settings->get('flarum-auth-vk.client_id'));
        $clientSecret = trim($this->settings->get('flarum-auth-vk.client_secret'));
        $display = VKOAuthDisplay::POPUP;
        $scope = [VKOAuthUserScope::EMAIL];

        $authUrl = $provider->getAuthorizeUrl(VKOAuthResponseType::CODE, $clientId, $redirectUri, $display, $scope);

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
          $response = $provider->getAccessToken($clientId, $clientSecret, $redirectUri, $code);
          $accessToken = $response['access_token'];

          $vk = new VKApiClient();
          $responseUser = $vk->users()->get($accessToken, [
              'user_ids'  => [$response['user_id']],
              'fields'    => ['photo_100', 'nickname', 'connections'],
          ]);

          if (!isset($responseUser[0])) {
            throw new \Exception('Error while get User info from VK');
          }

          $username = !empty($responseUser[0]['nickname']) ? $responseUser[0]['nickname'] : ($responseUser[0]['first_name'] . ' ' . $responseUser[0]['last_name']);
          $user = [
            'email' => $response['email'],
            'avatar' => $responseUser[0]['photo_100'],
            'username' => $username,
            'payload' => $response
          ];

          return $this->response->make(
              'vk', $response['user_id'],
              function (Registration $registration) use ($user) {
                  $registration->provideTrustedEmail($user['email'])
                      ->suggestUsername($this->translit($user['username']))
                      ->setPayload($user['payload']);
                  if (!empty($user['avatar'])) {
                    $registration->provideAvatar($user['avatar']);
                  }
              }
          );
        }
    }

    private function translit($s) {
      $s = (string) $s; // преобразуем в строковое значение
      $s = strip_tags($s); // убираем HTML-теги
      $s = str_replace(array("\n", "\r"), ' ', $s);
      $s = trim($s);
      $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
      $s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
      $s = preg_replace('/[^0-9a-z-_ ]/i', '', $s);
      $s = preg_replace('/\s+/', ' ', $s);
      $s = str_replace(' ', '-', $s);
      return $s;
    }
}
