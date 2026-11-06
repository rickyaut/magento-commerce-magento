<?php
namespace Oaklayton\ProductSearch\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;

class ProductSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        $url     = getenv('SEARCH_LAMBDA_URL');
        if (!$url) return;

        $categories = [];
        try {
            foreach ($product->getCategoryIds() as $id) {
                $categories[] = $id;
            }
        } catch (\Throwable) {}

        $payload = json_encode([
            'sku'               => $product->getSku(),
            'name'              => $product->getName(),
            'price'             => (string) $product->getPrice(),
            'status'            => (string) $product->getStatus(),
            'description'       => strip_tags((string) $product->getDescription()),
            'short_description' => strip_tags((string) $product->getShortDescription()),
            'categories'        => $categories,
            'url_key'           => $product->getUrlKey(),
            'image'             => $product->getImage(),
        ]);

        try {
            $ch = curl_init(rtrim($url, '/') . '/ingest');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);
            curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $this->logger->info("Oaklayton_ProductSearch: ingested {$product->getSku()} ({$status})");
        } catch (\Throwable $e) {
            $this->logger->error("Oaklayton_ProductSearch ingest: " . $e->getMessage());
        }
    }
}
