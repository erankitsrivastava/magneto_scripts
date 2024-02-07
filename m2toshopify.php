<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '50G');
error_reporting(E_ALL);

use Magento\Framework\App\Bootstrap;
$magentoDir = '/home/fragrance11/public_html/';
require $magentoDir.'/app/bootstrap.php';


$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get('Magento\Framework\App\State');
$registry = $objectManager->get('\Magento\Framework\Registry');
$registry->register("isSecureArea", true);

$state->setAreaCode('adminhtml');
$catalogFactory = $objectManager->create('Magento\Catalog\Model\ProductFactory');
$productRepository = $objectManager->create('\Magento\Catalog\Api\ProductRepositoryInterface');
$catalogCollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');

$catalogCollection->addAttributeToSelect('*');
$catalog_category_entity_varchar = [
    '1' => 'Root Catalog',
    '2' => 'Default Category',
    '3' => 'Fragrances',
    '3' => 'fragrances',
    '3' => 'fragrances',
    '4' => 'Men\'s',
    '5' => 'Women\'s',
    '6' => 'Unisex',
    '6' => 'unisex',
    '7' => 'Bath & Body',
    '8' => 'Make Up',
    '9' => 'Gift Sets',
    '10' => 'Shop By Brand',
    '11' => 'Day Care',
    '12' => 'Cleanser',
    '12' => 'cleanser',
    '13' => 'Body Care',
    '14' => 'Eye Care',
    '15' => 'Gift Set',
    '16' => 'Conditioner',
    '16' => 'conditioner',
    '17' => 'Styling',
    '17' => 'styling',
    '18' => 'Shampoo',
    '18' => 'shampoo',
    '19' => 'Styling Tools',
    '20' => 'Aromatherapy',
    '20' => 'aromatherapy',
    '20' => 'aromatherapy',
    '21' => 'Home Categories',
    '22' => 'New Arrivals',
    '23' => 'Minis',
    '23' => 'minis',
    '24' => 'Testers',
    '24' => 'testers',
    '25' => 'Samples',
    '25' => 'samples',
    '26' => 'Top 50',
    '27' => 'Giftsets',
    '27' => 'giftsets',
    '28' => 'Make Up Brush',
    '29' => 'Lips',
    '29' => 'lips',
    '30' => 'Sets',
    '30' => 'sets',
    '31' => 'Face',
    '31' => 'face',
    '32' => 'Nails',
    '32' => 'nails',
    '33' => 'Eye',
    '33' => 'eye',
    '34' => 'Palettes',
    '34' => 'palettes',
    '35' => 'Body',
    '35' => 'body',
    '36' => 'Hard to find',
    '37' => 'Skincare',
    '37' => 'skincare',
    '37' => 'skincare',
    '38' => 'Brands',
    '38' => 'brands',
    '38' => 'brands',
    '39' => 'All Brands',
    '40' => 'Haircare',
    '40' => 'haircare',
    '40' => 'haircare',
    '41' => 'Accessories',
    '41' => 'accessories',
    '41' => 'accessories',
    '42' => 'check',
    '42' => 'check',
    '42' => 'check',
    '43' => 'Niche Fragrances',
    '44' => 'ebay',
    '44' => 'ebay',
    '44' => 'ebay',
    '45' => 'Best Sellers',
    '46' => 'Popular Gift Sets',
    '47' => 'New Arrivals',
    '48' => 'New Arrivals',
    '49' => 'Top 50',
    '50' => 'Minis',
    '50' => 'minis',
    '51' => 'Testers',
    '51' => 'testers',
    '52' => 'Samples',
    '52' => 'samples',
    '53' => 'Giftsets',
    '53' => 'giftsets',
    '54' => 'Candles',
    '54' => 'candles',
    '54' => 'candles',
    '56' => 'Calvin Klein',
    '57' => 'Christian Dior',
    '58' => 'Dolce & Gabbana',
    '59' => 'Jimmy Choo',
    '60' => 'Gucci',
    '60' => 'gucci',
    '61' => 'Versace',
    '61' => 'versace'
	];
$csvHeader = ["Handle","Title","Body (HTML)","Vendor","Product Category","Type","Tags","Published","Option1 Name","Option1 Value","Option2 Name","Option2 Value","Option3 Name","Option3 Value","Variant SKU","Variant Grams","Variant Inventory Tracker","Variant Inventory Qty","Variant Inventory Policy","Variant Fulfillment Service","Variant Price","Variant Compare At Price","Variant Requires Shipping","Variant Taxable","Variant Barcode","Image Src","Image Position","Image Alt Text","Gift Card","SEO Title","SEO Description","Google Shopping / Google Product Category","Google Shopping / Gender","Google Shopping / Age Group","Google Shopping / MPN","Google Shopping / Condition","Google Shopping / Custom Product","Google Shopping / Custom Label 0","Google Shopping / Custom Label 1","Google Shopping / Custom Label 2","Google Shopping / Custom Label 3","Google Shopping / Custom Label 4","Variant Image","Variant Weight Unit","Variant Tax Code","Cost per item","Included / Canada","Price / Canada","Compare At Price / Canada","Included / International","Price / International","Compare At Price / International","Included / United States","Price / United States","Compare At Price / United States","Status"];
$imgBaseUrl ='https://fragrance11.com/media/catalog/product/';

$shopifyFeed = fopen(__DIR__."/shopify_feed/shopify_feed_07022024_1.csv", "w");
fputcsv($shopifyFeed, $csvHeader);
$c = 1;
$page = 1;
foreach($catalogCollection as $product){

    $stockItem = $product->getExtensionAttributes()->getStockItem();
    $productStockObj = $objectManager->get('Magento\CatalogInventory\Api\StockRegistryInterface')->getStockItem($product->getId());

    var_dump($c.'. processing SKU .'.$product->getSku());
    $c++;
    /* @var $product Magento\Catalog\Model\Product */
    $shopifyCatalog = [];
    $cat = '';
    foreach($product->getCategoryIds() as $id){
        if(isset($catalog_category_entity_varchar[$id])){
            if($cat != ''){
                $cat .= ' > ';
            }
            $cat .= $catalog_category_entity_varchar[$id];
        }
    }
 
    $shopifyCatalog = [
        "Handle" => $product->getUrlKey(),
        "Title" => $product->getName(),
        "Body (HTML)" => $product->getDescription(),
        "Vendor" => $product->getAttributeText('manufacturer'),
        "Product Category" => $cat,
        "Type" => $product->getiItemType(),
        "Tags" => $product->getMetaTags(),
        "Published" => 'TRUE',
        "Option1 Name" => '',
        "Option1 Value" => '',
        "Option2 Name" => '',
        "Option2 Value" => '',
        "Option3 Name" =>'',
        "Option3 Value" => '',
        "Variant SKU" => $product->getSku(),
        "Variant Grams" => $product->getWeight(),
        "Variant Inventory Tracker" => 'shopify',
        "Variant Inventory Qty" => (int)$productStockObj->getData('qty'),
        "Variant Inventory Policy" => 'deny',
        "Variant Fulfillment Service" => 'manual',
        "Variant Price" => $product->getPrice(),
        "Variant Compare At Price" => '',
        "Variant Requires Shipping" => 'TRUE',
        "Variant Taxable" => 'TRUE',
        "Variant Barcode" => $product->getUpc(),
        "Image Src" => (!!$product->getImage())?$imgBaseUrl.$product->getImage():'',
        "Image Position" => '',
        "Image Alt Text" => '',
        "Gift Card" => '',
        "SEO Title" => $product->getMetaTitle(),
        "SEO Description" => $product->getMetaDescription(),
        "Google Shopping / Google Product Category" => '',
        "Google Shopping / Gender" => '',
        "Google Shopping / Age Group" => '',
        "Google Shopping / MPN" => '',
        "Google Shopping / Condition" => '',
        "Google Shopping / Custom Product" => '',
        "Google Shopping / Custom Label 0" => '',
        "Google Shopping / Custom Label 1" => '',
        "Google Shopping / Custom Label 2" => '',
        "Google Shopping / Custom Label 3" => '',
        "Google Shopping / Custom Label 4" => '',
        "Variant Image" => '',
        "Variant Weight Unit" => 'KG',
        "Variant Tax Code" => '',
        "Cost per item" => '',
        "Included / Canada" => '',
        "Price / Canada" => '',
        "Compare At Price / Canada" => '',
        "Included / International" => '',
        "Price / International" => '',
        "Compare At Price / International" => '',
        "Included / United States" => '',
        "Price / United States" => '',
        "Compare At Price / United States" => '',
        "Status" => ($product->getStatus() == 1)?'active':'draft'
    ];

    if($c > 9000){
        $c = 1;
        $page++;
        $shopifyFeed = fopen(__DIR__."/shopify_feed/shopify_feed_07022024_$page.csv", "w");
        fputcsv($shopifyFeed, $csvHeader);
    }

    fputcsv($shopifyFeed, $shopifyCatalog);
}
fclose($shopifyFeed);
