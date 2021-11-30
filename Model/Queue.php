<?php

namespace Bitqit\Searchtap\Model;

use Bitqit\Searchtap\Helper\Logger;
use \Magento\Framework\Model\AbstractModel;
use phpDocumentor\Reflection\Types\This;

class Queue extends \Magento\Framework\Model\AbstractModel
{
    protected $_eventPrefix = 'searchtap_queue';

    const ENTITY_ID = 'entity_id';
    const ACTION = 'action';
    const STATUS = 'status';
    const TYPE = 'type';
    const STORE = 'store';

    private $queueFactory;
    private $searchtapHelper;
    private $logger;
    private $dataHelper;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Bitqit\Searchtap\Model\QueueFactory $queueFactory,
        \Bitqit\Searchtap\Helper\SearchtapHelper $searchtapHelper,
        \Bitqit\Searchtap\Helper\Logger $logger,
        \Bitqit\Searchtap\Helper\Data $dataHelper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->queueFactory = $queueFactory;
        $this->searchtapHelper = $searchtapHelper;
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct()
    {
        $this->_init('Bitqit\Searchtap\Model\ResourceModel\Queue');
    }

    public function setEntityId($entityId)
    {
        $this->setData(self::ENTITY_ID, $entityId);
        return $this;
    }

    public function setAction($action)
    {
        $this->setData(self::ACTION, $action);
        return $this;
    }

    public function setStatus($status)
    {
        $this->setData(self::STATUS, $status);
        return $this;
    }

    public function setType($type)
    {
        $this->setData(self::TYPE, $type);
        return $this;
    }

    public function setStore($storeId)
    {
        $this->setData(self::STORE, $storeId);
        return $this;
    }

    public function addToQueue($entityId, $action, $status, $type, $storeId)
    {
        $data = $this->queueFactory->create()
            ->setAction($action)
            ->setStatus($status);

        $entity = $this->isDataExists($entityId, $type, $storeId);

        if ($entity)
            $data->setId($entity->getId());

        else $data->setEntityId($entityId)
            ->setType($type)
            ->setStore($storeId);

        $data->save();
    }

    public function isDataExists($entityId, $type, $storeId)
    {
        $collection = $this->queueFactory->create()
            ->getCollection()
            ->addFilter('entity_id', $entityId)
            ->addFilter('type', $type) //todo: check if we need type in checking if the data exists
            ->addFilter('store', $storeId)
            ->load();

        foreach ($collection as $entity)
            if ($entity)
                return $entity;

        return 0;
    }

    public function getQueueData($count, $page, $type, $action, $storeId)
    {
        $entity = [];

        $collection = $this->queueFactory->create()
            ->getCollection()
            ->addFieldToSelect('*')
            ->setPageSize($count)
            ->setCurPage($page);

        if ($type)
            $collection->addFilter('type', $type);
        if ($action)
            $collection->addFilter('action', $action);
        if ($storeId)
            $collection->addFilter('store', $storeId);

        $collection->load();
        $count = $collection->getSize();

        foreach ($collection as $item) {
            $entity[] = array(
                "id" => $item->getId(),
                "entity_id" => $item->getEntityId(),
                "type" => $item->getType(),
                "action" => $item->getAction(),
                "store" => $item->getStore(),
                "status" => $item->getStatus()
            );
        }

        return [
            "data" => $entity,
            "count" => $count
        ];
    }

    public function deleteQueueData($entityIds)
    {
        foreach ($entityIds as $entityId) {
            $entity = $this->queueFactory->create()->load($entityId);
            $entity->delete();
        }

        return "OK";
    }
}