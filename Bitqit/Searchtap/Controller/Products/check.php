<?php


namespace Bitqit\Searchtap\Controller\Products;

class check extends \Magento\Framework\App\Action\Action
{
    private $productHelper;
    private $searchtapHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Bitqit\Searchtap\Helper\Products\ProductHelper $productHelper,
        \Bitqit\Searchtap\Helper\SearchtapHelper $searchtapHelper
    )
    {
        $this->productHelper = $productHelper;
        $this->searchtapHelper = $searchtapHelper;

        parent::__construct($context);
    }

    public function execute()
    {
        $storeId = $this->getRequest()->getParam('store', 1);
        $page = $this->getRequest()->getParam('page', 1);
        $count = $this->getRequest()->getParam('count', 100);


        $response = $this->productHelper->getReindexIds($count, $page);
print_r($response);
      //  $this->getResponse()->setHeader('content-type', 'application/json');
      //  $this->getResponse()->setStatusCode($this->searchtapHelper->getStatusCodeList()[$response["statusCode"]]);
        //$this->getResponse()->setBody($response["output"]);
    }
}
