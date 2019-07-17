<?php

namespace Bitqit\Searchtap\Controller\Categories;
use Bitqit\Searchtap\Helper;

class Index extends \Magento\Framework\App\Action\Action
{
    private $conf;
    private $stcurl;
    public function __construct(\Bitqit\Searchtap\Helper\getConfigValue $config)
    {
        $this->conf=$config;
    }

    public function execute()
    {

       $this->stcurl = new Helper\searchtapCurl($this->conf->applicationId, $this->conf->collectionName, $this->conf->adminKey);
       $storeId=$this->getRequest()->getParam('sid');
       $this->categoryJson($storeId);

    }

    public function categoryJson($sid)
    {

     $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
     $categoryFactory = $objectManager->create('Magento\Catalog\Model\ResourceModel\Category\CollectionFactory');
     $categories = $categoryFactory->create()->addAttributeToSelect('*')->setStore($sid); //categories from current store will be fetched
     if(!$this->conf->inactiveCategoryOption)
         $categories->addAttributeToFilter('is_active',true);

        if ($this->conf->categoryIncludeInMenu)
            $categories->addAttributeToFilter('include_in_menu', array('eq' => 1));


        if ($this->conf->skipCategoryIds) {
            $cat_ids = explode(",", $this->skipCategoryIds);
            foreach ($cat_ids as $id)
                $categories->addAttributeToFilter('path', array('nlike' => "%$id%"));
        }
       // $meta_tags=Array();
        foreach ($categories as $category) {

                 $pathIds = explode('/', $category->getPath());
                 foreach ($pathIds as $path){
                 $collection = $categoryFactory->create()->setStoreId($sid)->addAttributeToSelect('name')->addFieldToFilter('entity_id', array('in' => $path));
                 $pahtByName = '';

                 foreach ($collection as $cat) {
                        $pahtByName .= $cat->getName();
                 }
                     $pathArray[] = $pahtByName;
                 }

            $path=implode('|||',$pathArray);
            $meta_tags=explode(',',$category->getData('meta_keywords'));
             $categoryArray[] = array(
                'id' => (int)$category->getId(),
                'name' => $category->getName(),
                'url' => $category->getUrl(),
                'is_active' => $category->getIsActive(),
                'include_in_menu' => $category->getIncludeInMenu(),
                'product_count' => $category->getProductCount(),
                'path' => $path,
                'description' => strip_tags($category->getDescription()),
                'meta_title' => strip_tags($category->getMetaTitle()),
                'meta_description' => strip_tags($category->getMetaDescription()),
                'meta_keywords' => array_filter(array_map('trim', $meta_tags)),
                'updated_date' => $category->getUpdatedAt(),
                'level'=>$category->getLevel(),
                'parent_id'=> $category->getParentId()

            );

       unset($pathArray);
       unset($meta_tags);
        }

        $categoryJson=json_encode($categoryArray);
        //print_r($categoryJson);
        $response=$this->stcurl->searchtapCurlRequest($categoryJson);
        //echo $response;
    } 

}
