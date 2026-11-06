<?php
namespace Oaklayton\ProductSearch\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\UrlInterface;

class SearchRedirectPlugin
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly UrlInterface $url,
    ) {}

    public function aroundExecute(
        \Magento\CatalogSearch\Controller\Result\Index $subject,
        callable $proceed
    ) {
        $q = $this->request->getParam('q');
        $redirectUrl = $this->url->getUrl('productsearch/search/index', ['_query' => ['q' => $q]]);
        $this->response->setRedirect($redirectUrl);
        return $this->response;
    }
}
