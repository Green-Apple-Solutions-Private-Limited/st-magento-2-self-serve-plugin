<?php

namespace Bitqit\Searchtap\Helper\Products;

use \Bitqit\Searchtap\Helper\ConfigHelper;
use \Bitqit\Searchtap\Helper\SearchtapHelper;
use \Bitqit\Searchtap\Helper\Data;
use \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use \Bitqit\Searchtap\Helper\Products\ImageHelper;
use \Bitqit\Searchtap\Helper\Categories\CategoryHelper;
use \Magento\Catalog\Model\ProductRepository;
use \Magento\Catalog\Helper\Image;
use \Magento\Store\Model\StoreManagerInterface;
use \Magento\Directory\Model\Currency;
use \Bitqit\Searchtap\Helper\Products\AttributeHelper;
use \Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableModel;
use Magento\Bundle\Model\Product\Type as BundleModel;
use Magento\GroupedProduct\Model\Product\Type\Grouped as GroupedModel;
use Bitqit\Searchtap\Helper\Logger as Logger;
use \Magento\Framework\Module\Manager;
use \Magento\Swatches\Helper\Data as SwatchHelper;
use \Magento\Eav\Model\Config as EavConfig;
use \Magento\Review\Model\ReviewFactory as ReviewFactory;

class ProductHelper
{
    private $attributeHelper;
    private $configHelper;
    private $imageHelper;
    private $searchtapHelper;
    private $productCollectionFactory;
    private $currencyFactory;
    private $categoryHelper;
    private $productImageHelper;
    private $productRepository;
    private $storeManager;
    private $stockRepository;
    private $dataHelper;
    private $configurableModel;
    private $bundleModel;
    private $groupedModel;
    private $logger;
    private $moduleManager;
    private $swatchHelper;
    private $eavConfig;
    private $reviewFactory;

    public function __construct(
        ConfigHelper           $configHelper,
        SearchtapHelper        $searchtapHelper,
        CollectionFactory      $productFactory,
        ImageHelper            $imageHelper,
        CategoryHelper         $categoryHelper,
        ProductRepository      $productRepository,
        Image                  $productImageHelper,
        StoreManagerInterface  $storeManager,
        Currency               $currencyFactory,
        AttributeHelper        $attributeHelper,
        StockRegistryInterface $stockRepository,
        Data                   $dataHelper,
        ConfigurableModel      $configurableModel,
        BundleModel            $bundleModel,
        GroupedModel           $groupedModel,
        Logger                 $logger,
        Manager                $moduleManager,
        SwatchHelper           $swatchHelper,
        EavConfig              $eavConfig,
        ReviewFactory          $reviewFactory
    )
    {
        $this->imageHelper = $imageHelper;
        $this->searchtapHelper = $searchtapHelper;
        $this->configHelper = $configHelper;
        $this->productCollectionFactory = $productFactory;
        $this->categoryHelper = $categoryHelper;
        $this->productImageHelper = $productImageHelper;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->currencyFactory = $currencyFactory;
        $this->attributeHelper = $attributeHelper;
        $this->stockRepository = $stockRepository;
        $this->dataHelper = $dataHelper;
        $this->configurableModel = $configurableModel;
        $this->bundleModel = $bundleModel;
        $this->groupedModel = $groupedModel;
        $this->logger = $logger;
        $this->moduleManager = $moduleManager;
        $this->swatchHelper = $swatchHelper;
        $this->eavConfig = $eavConfig;
        $this->reviewFactory = $reviewFactory;
    }

    public function getProductCollection($storeId, $count, $page, $productIds = null)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStore($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect('*');
        $collection->addAttributeToFilter('status', ['eq' => 1]);
        $collection->addAttributeToFilter('visibility', ['neq' => 1]);
        $collection->setFlag('has_stock_status_filter', true);
        $collection->addAttributeToSort('entity_id', 'ASC');
        $collection->setPageSize($count);
        $collection->setCurPage($page);


        if ($productIds)
            $collection->addAttributeToFilter('entity_id', ['in' => $productIds]);

        return $collection;
    }

    public function getProductByIds($productIds, $storeId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStore($storeId);
        $collection->addAttributeToSelect(['entity_id', 'status', 'visibility', 'type']);
        $collection->addAttributeToFilter('entity_id', ['in' => $productIds]);
        return $collection;
    }

    public function getProductsJSON($token, $storeId, $count, $page, $imageConfig, $indexOutOfStockVariations, $productIds)
    {
        if (!$this->dataHelper->checkCredentials()) {
            return $this->searchtapHelper->error("Invalid credentials");
        }

        if (!$this->dataHelper->checkPrivateKey($token)) {
            return $this->searchtapHelper->error("Invalid token");
        }

        if (!$this->dataHelper->isStoreAvailable($storeId)) {
            return $this->searchtapHelper->error("store not found for ID " . $storeId, 404);
        }

        //Start Frontend Emulation
        $this->searchtapHelper->startEmulation($storeId);

        $productCollection = $this->getProductCollection($storeId, $count, $page, $productIds);

        $data = [];

        foreach ($productCollection as $product) {
            $data[] = $this->getProductObject($product, $storeId, $imageConfig, $indexOutOfStockVariations);
        }

        //Stop Emulation
        $this->searchtapHelper->stopEmulation();

        return $this->searchtapHelper->okResult($data, $productCollection->getSize());
    }

    public function getFormattedString($string)
    {
        if ($string) return $this->searchtapHelper->getFormattedString(str_replace("\r\n", "", $string));
        return $string;
    }

    protected function getStockData($product)
    {
        if ($product->isSalable()) {
            return $this->stockRepository->getStockItem($product->getId())->getIsInStock();
        }

        return false;
    }

    public function getProductObject($product, $storeId, $imageConfig, $indexOutOfStockVariations)
    {
        $data = [];

        //Product Basic Information
        $data['id'] = $product->getId();
        $data['name'] = $this->getFormattedString($product->getName());
        $data['url'] = $product->getProductUrl();
        $data['status'] = $product->getStatus();
        $data['visibility'] = $product->getVisibility();
        $data['type'] = $product->getTypeId();
        $data['created_at'] = strtotime($product->getCreatedAt());
        $data['sku'] = $product->getSKU();
        $data['description'] = $this->getFormattedString($product->getDescription());
        $data['short_description'] = $this->getFormattedString($product->getShortDescription());

        $childInfo = $this->getChildSKUsAndName($product);
        $data['child_skus'] = $childInfo["child_skus"];
        $data['child_names'] = $childInfo["child_names"];

        $metaTitle = $product->getMetaTitle();
        $metaKeywords = $product->getMetaKeyword();
        $metaDescription = $product->getMetaDescription();

        if (isset($metaTitle)) $data['meta_title'] = $this->getFormattedString($metaTitle);
        if (isset($metaKeywords)) $data['meta_keywords'] = $this->getFormattedString($metaKeywords);
        if (isset($metaDescription)) $data['meta_description'] = $this->getFormattedString($metaDescription);

        //Product Stock Information
        $data["in_stock"] = (int)$this->getStockData($product);

        //Product Price Information
        $data['price'] = $this->getPrices($product, $storeId);

        // Product Category Information
        $data['_category_ids'] = $product->getCategoryIds();

        $categoriesData = $this->categoryHelper->getProductCategories($product, $storeId);
        $data['_categories'] = $categoriesData["_categories"];
        $data["categories_path"] = $categoriesData["categories_path"];

        //Product Images Information
        $images = $this->imageHelper->getImages($imageConfig, $product);

        //Additional Attributes Information
        $additionalAttributes = $this->attributeHelper->getProductAdditionalAttributes($product);

        //Get Product Variations Information
        if ($product->getTypeId() === \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            $associatedProducts = $this->getAssociatedProductAttributes($product, $storeId, $indexOutOfStockVariations);
            $data = array_merge($data, $associatedProducts);
        }

        $data = array_merge(
            $data,
            $images,
            $categoriesData["category_level"],
            $additionalAttributes
        );

        /***
         * Get product reviews
         */
        if ($this->configHelper->isProductReviewEnabled($storeId)) {
            $reviews = $this->getProductReviews($product, $storeId);
            $data['reviews_average'] = $reviews['reviews_average'];
            $data['reviews_count'] = $reviews['reviews_count'];
        }

        // Execute custom data massaging script
        if ($this->moduleManager->isEnabled("Bitqit_Helper")) {
            try {
                $object_manager = \Magento\Framework\App\ObjectManager::getInstance();
                $customProductHelper = $object_manager->get('\Bitqit\Helper\Helper\CustomProductHelper');
                $data = $customProductHelper->DataMassaging($data, $product, $storeId);
                return $data;
            } catch (Exception $e) {
                $this->logger->info($e->getMessage());
            }
        }
        return $data;
    }

    public function getProductReviews($product, $storeId)
    {
        $this->reviewFactory->create()->getEntitySummary($product, $storeId);
        $ratingSummary = $product->getRatingSummary()->getRatingSummary();
        $reviewCount = $product->getRatingSummary()->getReviewsCount();
        return [
            "reviews_average" => (int)$ratingSummary,
            "reviews_count" => (int)$reviewCount
        ];
    }

    public function getPrices($product, $storeId)
    {
        $regularPrice = $this->searchtapHelper->getFormattedPrice($product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue());
        $specialPrice = $this->searchtapHelper->getFormattedPrice($product->getFinalPrice());
        $priceObject = $product->getPriceInfo()->getPrice('final_price');
        $priceMin = $priceObject->getMinimalPrice()->getValue();
        $priceMax = $priceObject->getMaximalPrice()->getValue();
        $specialFromDate = $product->getSpecialFromDate();
        $specialToDate = $product->getSpecialToDate();

        $baseCurrencyCode = $this->storeManager->getStore($storeId)->getBaseCurrency()->getCode();
        $currencySymbol = $this->getCurrencySymbol($baseCurrencyCode);

        $data = [
            "price" => $regularPrice,
            "special_price" => $specialPrice,
            "currency_symbol" => $currencySymbol,
            "special_from_date" => $specialFromDate ? strtotime($specialFromDate) : false,
            "special_to_date" => $specialToDate ? strtotime($specialToDate) : false,
            "discount" => $this->getDiscountPercentage($regularPrice, $specialPrice)
        ];

        switch ($product->getTypeId()) {
            case "grouped":
                if (!$regularPrice) $data["price"] = $priceMin;
                $data["special_price"] = $priceMin;
                $data["min_price"] = $priceMin;
                $data["max_price"] = $priceMax;
                break;
            case "bundle":
                if (!$product->getPriceType()) $data["special_price"] = $priceMin;
                $data["min_price"] = $priceMin;
                $data["max_price"] = $priceMax;
                $data["price_type"] = $product->getPriceType();
                $data["price_view"] = $product->getPriceView();
                break;
        }

        return $data;
    }

    public function getDiscountPercentage($regularPrice, $specialPrice)
    {
        if ($specialPrice && $regularPrice) {
            $discount = (($regularPrice - $specialPrice) / $regularPrice) * 100;
            return round($discount);
        }
        return 0;
    }

    public function getCurrencySymbol($currencyCode)
    {
        $currency = $this->currencyFactory->load($currencyCode);
        return $currency->getCurrencySymbol();
    }

    public function getFormattedPrice($regularPrice, $specialPrice, $currencySymbol, $priceMin, $priceMax)
    {
        $data = [];

        $data['regular_price'] = $currencySymbol . $regularPrice;
        $data['special_price'] = $currencySymbol . $specialPrice;
        $data['price_range'] = $currencySymbol . $priceMin . " - " . $currencySymbol . $priceMax;

        return $data;
    }

    public function getAssociatedProductAttributes($product, $storeId, $indexOutOfStockVariations = false)
    {
        $productAttributes = [];

        $attributeCodes = array_map(function ($product) {
            return $product["attribute_code"];
        }, $product->getTypeInstance()->getConfigurableAttributesAsArray($product));

        $associatedProducts = $product->getTypeInstance()->getUsedProducts($product);

        $stockStatus = true;
        foreach ($associatedProducts as $associatedProduct) {
            if (!(boolean)json_decode($indexOutOfStockVariations))
                $stockStatus = $this->stockRepository->getStockItem($associatedProduct->getId())->getIsInStock();

            foreach ($attributeCodes as $attributeCode) {
                $inputType = $product->getResource()->getAttribute($attributeCode)->getFrontendInput();
                $attributeName = $attributeCode . "_" . $inputType;
                $value = $this->getFormattedString($associatedProduct->setStoreId($storeId)->getAttributeText($attributeCode));

                if ((!array_key_exists($attributeName, $productAttributes) || !in_array($value, $productAttributes[$attributeName]))
                    && $stockStatus && $associatedProduct->getStatus() !== 2)
                    $productAttributes[$attributeName][] = $value;
            }
        }

        foreach ($attributeCodes as $attributeCode) {
            $attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);
            if ($this->swatchHelper->isTextSwatch($attribute)) {
                $attributeName = $attributeCode . '_' . $product->getResource()->getAttribute($attributeCode)->getFrontendInput();
                if (isset($productAttributes[$attributeName])) {
                    sort($productAttributes[$attributeName]);
                    $this->_getSortedSizes($productAttributes[$attributeName]);
                }
            }
        }

        //todo: get images of child products that have color associated with them

        return $productAttributes;
    }

    private function _getSortedSizes(&$values)
    {
        $sortOrder = ['FS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '2X', 'XXXL', '3XL', '3X', 'XXXXL', '4XL', '4X', '5XL', '5X'];
        $temp = [];

        foreach ($sortOrder as $order) {
            if (in_array($order, $values))
                $temp[] = $order;
        }

        foreach ($values as $value) {
            if (!in_array($value, $temp))
                $temp[] = $value;
        }

        $values = $temp;
    }

    public function getChildSKUsAndName($product)
    {
        $skus = [];
        $names = [];

        switch ($product->getTypeId()) {
            case 'configurable':
                $variationProduct = $product->getTypeInstance()->getUsedProducts($product);
                foreach ($variationProduct as $child) {
                    if ($child->getStatus()) {
                        $skus[] = $child->getSku();
                        $names[] = $this->getFormattedString($child->getName());
                    }
                }
                break;
            case 'grouped':
                $groupedProducts = $product->getTypeInstance(true)->getAssociatedProducts($product);
                foreach ($groupedProducts as $child) {
                    if ($child->getStatus()) {
                        $skus[] = $child->getSku();
                        $names[] = $this->getFormattedString($child->getName());
                    }
                }
                break;
            case 'downloadable':
            case 'bundle':
            case 'virtual':
            case 'simple':
            default:
                $skus = [];
                $names = [];
        }

        return [
            "child_skus" => $skus,
            "child_names" => $names
        ];
    }

    public function getReindexableProductIds($storeId, $count, $page, $token)
    {
        if (!$this->dataHelper->checkCredentials()) {
            return $this->searchtapHelper->error("Invalid credentials");
        }

        if (!$this->dataHelper->checkPrivateKey($token)) {
            return $this->searchtapHelper->error("Invalid token");
        }

        if (!$this->dataHelper->isStoreAvailable($storeId)) {
            return $this->searchtapHelper->error("store not found for ID " . $storeId, 404);
        }

        //Start Frontend Emulation
        $this->searchtapHelper->startEmulation($storeId);
        $productCollection = $this->getProductCollection($storeId, $count, $page);
        $data = [];

        foreach ($productCollection as $product) {
            $data[] = $product->getId();
        }

        return $this->searchtapHelper->okResult($data, $productCollection->getSize());
    }

    public function getConfigurableProductIdFromChildProduct($productId)
    {
        $parent = $this->configurableModel->getParentIdsByChild($productId);
        if ($parent) return $parent[0];
        return 0;
    }

    public function getBundleProductIdFromSimpleProduct($productId)
    {
        $bundleProduct = $this->bundleModel->getParentIdsByChild($productId);
        if ($bundleProduct) return $bundleProduct[0];
        return 0;
    }

    public function getGroupedProductIdFromSimpleProduct($productId)
    {
        $groupedProduct = $this->groupedModel->getParentIdsByChild($productId);
        if ($groupedProduct) return $groupedProduct[0];
        return 0;
    }

    public function processImages($token, $storeId, $height, $width, $count, $page)
    {
        try {
            if (!$this->dataHelper->checkCredentials()) {
                return $this->searchtapHelper->error("Invalid credentials");
            }

            if (!$this->dataHelper->checkPrivateKey($token)) {
                return $this->searchtapHelper->error("Invalid token");
            }

            if (!$this->dataHelper->isStoreAvailable($storeId)) {
                return $this->searchtapHelper->error("store not found for ID " . $storeId, 404);
            }

            // Start Frontend Simulation
            $this->searchtapHelper->startEmulation($storeId);

            $productCollection = $this->getProductCollection($storeId, $count, $page);

            foreach ($productCollection as $product) {
                $this->imageHelper->getResizedImageUrl($product, 'product_base_image', $width, $height);
                $this->imageHelper->getResizedImageUrl($product, 'product_small_image', $width, $height);
                $this->imageHelper->getResizedImageUrl($product, 'product_thumbnail_image', $width, $height);
            }

            // Stop Simulation
            $this->searchtapHelper->stopEmulation();

            return $this->searchtapHelper->okResult("Created", $productCollection->getSize());
        } catch (\Exception $e) {
            return $e;
        }
    }
}
