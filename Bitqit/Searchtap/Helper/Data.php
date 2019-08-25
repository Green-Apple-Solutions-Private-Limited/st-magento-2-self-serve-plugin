<?php

namespace Bitqit\Searchtap\Helper;

use \Bitqit\Searchtap\Helper\ConfigHelper;
use \Bitqit\Searchtap\Helper\SearchtapHelper;
use \Magento\Store\Model\StoreManagerInterface;
use \Bitqit\Searchtap\Model\QueueFactory as QueueFactory;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const PRIVATE_KEY = "private_key";

    private $configHelper;
    private $storeManager;
    private $searchtapHelper;
    private $queueFactory;

    public function __construct(
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        SearchtapHelper $searchtapHelper,
        QueueFactory $queueFactory
    )
    {
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->searchtapHelper = $searchtapHelper;
        $this->queueFactory = $queueFactory;
    }

    public function checkPrivateKey($privateKey)
    {
        $dbPrivateKey = $this->configHelper->getPrivateToken();

        if (!empty($privateKey)) {
            if ($privateKey === $dbPrivateKey)
                return true;
        }

        return false;
    }

    public function getStoresData($token)
    {
        if (!$this->checkPrivateKey($token)) {
            return $this->searchtapHelper->error("Invalid token");
        }

        $stores = [];
        $collection = $this->storeManager->getStores();
        foreach ($collection as $store) {
            $data = array(
                "id" => $store->getId(),
                "code" => $store->getCode(),
                "name" => $store->getName(),
                "is_active" => $store->isActive(),
                "website_id" => $store->getWebsiteId()
            );
            $stores[] = $data;
        }

        return $this->searchtapHelper->okResult($stores, count($stores));
    }

    public function getQueueData($token, $count, $page, $type, $action, $storeId)
    {
        if (!$this->checkPrivateKey($token)) {
            return $this->searchtapHelper->error("Invalid token");
        }

        $data = $this->queueFactory->create()->getQueueData($count, $page, $type, $action, $storeId);

        return $this->searchtapHelper->okResult($data['data'], $data['count']);
    }

    public function deleteQueueData($token, $entityIds)
    {
        if (!$this->checkPrivateKey($token)) {
            return $this->searchtapHelper->error("Invalid token");
        }

        if (!$entityIds) {
            return $this->searchtapHelper->error("Invalid entity Ids");
        }

        $data = $this->queueFactory->create()->deleteQueueData(explode(',', $entityIds));

        return $this->searchtapHelper->okResult($data);
    }
}