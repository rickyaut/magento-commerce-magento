<?php
namespace Oaklayton\AdminSso\Plugin;

use Magento\Backend\App\AbstractAction;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Magento\Backend\Model\UrlInterface as BackendUrl;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\User\Model\UserFactory;
use Psr\Log\LoggerInterface;

class SsoLoginPlugin
{
    public function __construct(
        private readonly AuthSession $authSession,
        private readonly ResourceConnection $resource,
        private readonly UserFactory $userFactory,
        private readonly BackendUrl $backendUrl,
        private readonly ResponseInterface $response,
        private readonly LoggerInterface $logger,
    ) {}

    public function beforeDispatch(AbstractAction $subject, RequestInterface $request): array
    {
        $token = $request->getParam('sso_token');
        if (!$token || $this->authSession->isLoggedIn()) {
            return [$request];
        }

        try {
            $conn = $this->resource->getConnection();
            $row  = $conn->fetchRow(
                "SELECT user_id, expires_at FROM adminsso_token WHERE token = ?",
                [$token]
            );
            $conn->delete('adminsso_token', ['token = ?' => $token]);

            if (!$row || $row['expires_at'] < time()) {
                return [$request];
            }

            $user = $this->userFactory->create()->load($row['user_id']);
            if (!$user->getId()) {
                return [$request];
            }

            $this->authSession->setUser($user);
            $this->authSession->processLogin();
            $this->response->setRedirect($this->backendUrl->getUrl('adminhtml/dashboard'));
            $this->response->sendResponse();
            exit;

        } catch (\Throwable $e) {
            $this->logger->error('Oaklayton_AdminSso plugin: ' . $e->getMessage());
        }

        return [$request];
    }
}
