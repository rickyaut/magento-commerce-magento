<?php
namespace Oaklayton\ProductSearch\Controller\Search;

use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements ActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $pageFactory,
    ) {}

    public function execute()
    {
        return $this->pageFactory->create();
    }
}
