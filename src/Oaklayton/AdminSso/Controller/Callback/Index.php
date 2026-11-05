<?php
namespace Oaklayton\AdminSso\Controller\Callback;

use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\User\Model\UserFactory;
use Psr\Log\LoggerInterface;

class Index implements ActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly ResourceConnection $resource,
        private readonly UserFactory $userFactory,
        private readonly SessionManagerInterface $session,
        private readonly BackendUrl $backendUrl,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute()
    {
        try {
            $code = $this->request->getParam('code');
            if (!$code) throw new \RuntimeException('Missing authorization code.');

            $this->session->start();
            $verifier = $this->session->getPkceVerifier();
            if (!$verifier) throw new \RuntimeException('Missing PKCE verifier.');
            $this->session->unsPkceVerifier();

            $tokens = $this->exchangeCode($code, $verifier);
            $claims = $this->validateJwt($tokens['id_token']);

            $email = $claims['email'] ?? null;
            if (!$email) throw new \RuntimeException('No email in token claims.');

            $user = $this->userFactory->create()->load($email, 'email');
            if (!$user->getId()) {
                $user->setData([
                    'username'  => $email,
                    'firstname' => $claims['given_name'] ?? 'Cognito',
                    'lastname'  => $claims['family_name'] ?? 'User',
                    'email'     => $email,
                    'password'  => bin2hex(random_bytes(16)),
                    'is_active' => 1,
                ]);
                $user->setRoleId(1);
                $user->save();
                $this->logger->info("Oaklayton_AdminSso: auto-provisioned admin for {$email}");
            }

            $token = bin2hex(random_bytes(32));
            $this->resource->getConnection()->insert('adminsso_token', [
                'token'      => $token,
                'user_id'    => $user->getId(),
                'expires_at' => time() + 60,
            ]);

            $this->logger->info("Oaklayton_AdminSso: SSO callback success for {$email}");
            // Build redirect URL with sso_token as path param, no secret key needed
            $baseUrl = rtrim($this->backendUrl->getBaseUrl(), '/');
            $this->response->setRedirect("{$baseUrl}/admin/dashboard/index/index/sso_token/{$token}/");

        } catch (\Throwable $e) {
            $this->logger->error('Oaklayton_AdminSso: ' . $e->getMessage());
            $this->response->setRedirect($this->backendUrl->getUrl(''));
        }

        return $this->response;
    }

    private function exchangeCode(string $code, string $verifier): array
    {
        $domain       = getenv('COGNITO_DOMAIN');
        $clientId     = getenv('COGNITO_CLIENT_ID');
        $redirectUri  = getenv('COGNITO_REDIRECT_URI') ?: 'http://localhost/adminsso/callback';
        $clientSecret = getenv('COGNITO_CLIENT_SECRET');

        $ch = curl_init("https://{$domain}/oauth2/token");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'authorization_code',
                'client_id'     => $clientId,
                'redirect_uri'  => $redirectUri,
                'code'          => $code,
                'code_verifier' => $verifier,
            ]),
            CURLOPT_HTTPHEADER => array_filter([
                'Content-Type: application/x-www-form-urlencoded',
                $clientSecret ? 'Authorization: Basic ' . base64_encode("{$clientId}:{$clientSecret}") : null,
            ]),
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) throw new \RuntimeException("Token exchange failed ({$status}): {$body}");
        return json_decode($body, true);
    }

    private function validateJwt(string $token): array
    {
        $region     = getenv('COGNITO_REGION') ?: 'ap-southeast-2';
        $userPoolId = getenv('COGNITO_USER_POOL_ID');
        $clientId   = getenv('COGNITO_CLIENT_ID');

        [$headerB64, $payloadB64, $sigB64] = explode('.', $token);
        $header = json_decode($this->base64urlDecode($headerB64), true);

        $jwks = json_decode(file_get_contents(
            "https://cognito-idp.{$region}.amazonaws.com/{$userPoolId}/.well-known/jwks.json"
        ), true);

        $key = current(array_filter($jwks['keys'], fn($k) => $k['kid'] === $header['kid']));
        if (!$key) throw new \RuntimeException('JWT signing key not found.');

        $pem   = $this->jwkToPem($key);
        $valid = openssl_verify(
            "{$headerB64}.{$payloadB64}",
            $this->base64urlDecode($sigB64),
            $pem,
            OPENSSL_ALGO_SHA256
        );
        if ($valid !== 1) throw new \RuntimeException('JWT signature invalid.');

        $claims = json_decode($this->base64urlDecode($payloadB64), true);
        if ($claims['exp'] < time()) throw new \RuntimeException('JWT expired.');
        if (($claims['aud'] ?? $claims['client_id']) !== $clientId) throw new \RuntimeException('JWT audience mismatch.');
        if ($claims['iss'] !== "https://cognito-idp.{$region}.amazonaws.com/{$userPoolId}") {
            throw new \RuntimeException('JWT issuer mismatch.');
        }

        return $claims;
    }

    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    private function jwkToPem(array $jwk): string
    {
        $n      = $this->base64urlDecode($jwk['n']);
        $e      = $this->base64urlDecode($jwk['e']);
        $encLen = fn(int $l) => $l <= 0x7f
            ? chr($l)
            : chr(0x80 | strlen($t = ltrim(pack('N', $l), "\x00"))) . $t;

        $mod = "\x02" . $encLen(strlen($n)) . $n;
        $exp = "\x02" . $encLen(strlen($e)) . $e;
        $seq = "\x30" . $encLen(strlen($mod . $exp)) . $mod . $exp;
        $bit = "\x03" . $encLen(strlen($seq) + 1) . "\x00" . $seq;
        $alg = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $der = "\x30" . $encLen(strlen($alg . $bit)) . $alg . $bit;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64) . "-----END PUBLIC KEY-----\n";
    }
}
