<?php
namespace Oaklayton\ProductSearch\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

class ProductDeleteObserver implements ObserverInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        $url     = getenv('SEARCH_LAMBDA_URL');
        if (!$url) return;

        try {
            $ch = curl_init(rtrim($url, '/') . '/ingest');
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
                CURLOPT_POSTFIELDS     => json_encode(['sku' => $product->getSku()]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            $this->logger->error("Oaklayton_ProductSearch delete: " . $e->getMessage());
        }
    }
}
