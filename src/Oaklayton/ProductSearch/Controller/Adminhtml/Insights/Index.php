<?php
namespace Oaklayton\ProductSearch\Controller\Adminhtml\Insights;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Oaklayton_ProductSearch::search_insights';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Search Insights'));
        return $page;
    }
}
