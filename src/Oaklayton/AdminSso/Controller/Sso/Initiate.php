<?php
namespace Oaklayton\AdminSso\Controller\Sso;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Session\SessionManagerInterface;

class Initiate implements ActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly SessionManagerInterface $session,
    ) {}

    public function execute()
    {
        $verifier  = $this->base64url(random_bytes(32));
        $challenge = $this->base64url(hash('sha256', $verifier, true));

        $this->session->start();
        $this->session->setPkceVerifier($verifier);

        $domain      = getenv('COGNITO_DOMAIN');
        $clientId    = getenv('COGNITO_CLIENT_ID');
        $redirectUri = getenv('COGNITO_REDIRECT_URI') ?: 'http://localhost/admin/adminsso/callback';

        $url = "https://{$domain}/oauth2/authorize?" . http_build_query([
            'response_type'         => 'code',
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'scope'                 => 'openid email',
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
            'prompt'                => 'login',
        ]);

        $this->response->setRedirect($url);
        return $this->response;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
