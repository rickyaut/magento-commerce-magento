<?php
namespace Oaklayton\AdminSso\Controller\Sso;

use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\User\Model\UserFactory;
use Psr\Log\LoggerInterface;

class Token implements ActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly ResourceConnection $resource,
        private readonly UserFactory $userFactory,
        private readonly AuthSession $authSession,
        private readonly BackendUrl $backendUrl,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute()
    {
        try {
            $token = $this->request->getParam('sso_token');
            if (!$token) throw new \RuntimeException('Missing sso_token.');

            $conn = $this->resource->getConnection();
            $row  = $conn->fetchRow(
                "SELECT user_id, expires_at FROM adminsso_token WHERE token = ?",
                [$token]
            );
            $conn->delete('adminsso_token', ['token = ?' => $token]);

            if (!$row || $row['expires_at'] < time()) {
                throw new \RuntimeException('Token invalid or expired.');
            }

            $user = $this->userFactory->create()->load($row['user_id']);
            if (!$user->getId()) throw new \RuntimeException('User not found.');

            $this->authSession->setUser($user);
            $this->authSession->processLogin();
            $this->logger->info("Oaklayton_AdminSso: logged in {$user->getEmail()}");

            session_write_close();
            $this->response->setRedirect($this->backendUrl->getBaseUrl() . 'admin/');
        } catch (\Throwable $e) {
            $this->logger->error('Oaklayton_AdminSso Token: ' . $e->getMessage());
            $this->response->setRedirect($this->backendUrl->getUrl(''));
        }

        return $this->response;
    }
}
