<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('memory_limit', '5G');
error_reporting(E_ALL);

use Magento\Framework\App\Bootstrap;

require '/home/klohthing/public_html/app/bootstrap.php';

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

$objectManager = $bootstrap->getObjectManager();

$state = $objectManager->get('Magento\Framework\App\State');
$registry = $objectManager->get('\Magento\Framework\Registry');
$registry->register("isSecureArea", true);

$state->setAreaCode('adminhtml');
$catcollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Category\Collection');
$skipCUstomAttrs = ['category_ids', 'export_product'];
$errorData = [];
$productFactory = $objectManager->create('\Magento\Catalog\Model\ProductFactory');

/*@note: uncomment to delete all the products*/
/*foreach($productFactory->create()->getCollection() as $pro){
    var_dump($pro->getId());
    $pro->delete();
}die;*/
$dir = $objectManager->get('Magento\Framework\App\Filesystem\DirectoryList');

$productResource = $objectManager->get(\Magento\Catalog\Model\ResourceModel\Product::class);

$categoryFactory = $objectManager->create('\Magento\Catalog\Model\CategoryFactory');

$handle = fopen('/home/klohthing/public_html/migration/products/catalog_category_flat_store_11.csv', 'rw');
$categoryCsv = fopen('/home/klohthing/public_html/migration/products/categoryData.csv', 'rw');
$errorlog = fopen('/home/klohthing/public_html/migration/products/product_migration_error.csv', 'w');
$categoryData = [];
fgetcsv($categoryCsv, 1000, ",");
while (($data = fgetcsv($categoryCsv, 1000, ",")) !== FALSE) {
    $categoryData[$data[0]] = $data[3];
}
$manufacturer = getF11AttrData('manufacturer');
$itemType = getF11AttrData('item_type');



$eavConfig = $objectManager->create('\Magento\Eav\Model\Config');
$manufacturerAttr = $eavConfig->getAttribute('catalog_product', 'manufacturer');
$manufacturerAttrOptions = [];
foreach($manufacturerAttr->getSource()->getAllOptions() as $option){
    $manufacturerAttrOptions[$option['label']] = $option['value'];
}
$itemTypeAttr = $eavConfig->getAttribute('catalog_product', 'item_type');
$itemTypeOptions = [];
foreach($itemTypeAttr->getSource()->getAllOptions() as $option){
    $itemTypeOptions[$option['label']] = $option['value'];
}

$totalItems = 48491;
$itemsPerPage = 20;
$limit = ceil($totalItems / $itemsPerPage);

for ($page = 1; $page <= $limit; $page++) {
    var_dump('Processing page : '.$page);
    $products = getProductsfromF11($page);
    if(isset($products['items']) && !empty($products['items'])){
        foreach ($products['items'] as $product){
            try{
                $datum = $product;
                $extensionAttr = $datum['extension_attributes'];
                $mediaGall = $datum['media_gallery_entries'];
                $customAttr = $datum['custom_attributes'];
                $datum['export_product'] = 1;

                unset($datum['id']);
                unset($datum['extension_attributes']);
                unset($datum['media_gallery_entries']);
                unset($datum['custom_attributes']);
                unset($datum['product_links']);
                unset($datum['options']);
                unset($datum['tier_prices']);

                /* @var $productModel \Magento\Catalog\Model\Product */
                $productModel = $productFactory->create();
                $entityId = $productModel->getIdBySku($datum['sku']);

                if($entityId){
//                $productModel->delete();
                    continue;
                }
                $productModel = $productModel->load($entityId);
                var_dump("Processing page : $page AND Product : ".$product['sku']);

                $productModel = $productFactory->create();
                $productModel->setData($datum);
                $productModel->setWebsiteIds([1]);
                $productModel->setQuantityAndStockStatus(getProductsQty($datum['sku']));

                foreach ($customAttr as $attr){
                    if(in_array($attr['attribute_code'], $skipCUstomAttrs)){
                        ${$attr['attribute_code']} = $attr['value'];
                        continue;
                    }
                    if($attr['attribute_code'] == 'gender'){
                        $gender = [3 => 50, 4 => 51, 5=> 52];
                        $productModel->setData('gender', $gender[$attr['value']]);
                    }else if($attr['value'] == (int)$attr['value']){
                        $val = $attr['value'];

                        if($attr['attribute_code'] == 'item_type'){

                            $val = $itemType[$attr['value']];
                            $val = $itemTypeOptions[$itemType[$attr['value']]];
                        }

                        if($attr['attribute_code'] == 'manufacturer'){
                            $val = $manufacturer[$attr['value']];
                           
                            $val = $manufacturerAttrOptions[$manufacturerAttr[$attr['value']]];
                        }
                        $productModel->setData($attr['attribute_code'], $val);
                    } else {
                        $productModel->setData($attr['attribute_code'], $attr['value']);
                    }
                }


                if(isset($category_ids)){
                    $catIds =   [];
                    foreach ($category_ids as $cid){
                        if(!isset($categoryData[$cid]))continue;
                        $catIds[] = $categoryData[$cid];
                    }
                    $productModel->setCategoryIds($catIds);
                }
//            die(__FILE__.__LINE__);

                /*$productModel->save();
                $productModel = $productFactory->create();
                $productModel->load($datum['sku'], 'sku');*/

                foreach ($mediaGall as $imgs){
                    $imagePath = $dir->getPath('media').'/product_images'.$imgs['file'];
                    if(!file_exists($imagePath)){
                        continue;
                    }
                    $types = $imgs['types'];
                    if(!empty($types)){
                        $types[] = 'swatch_image';
                    }
                    $productModel->addImageToMediaGallery($imagePath, $imgs['types'], false, false);
                }
                $productResource->save($productModel);
            }catch (\Exception $e){
                echo $e;
                fputcsv($errorlog, [$datum['sku'], $e->getMessage()]);
            }
        }
    }else{
        var_dump('ERROR PROCESSING PAGE '.$page);
        continue;
    }
}
fclose($errorlog);
fclose($categoryCsv);
function getF11AttrData($attrCode){

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://xxxxxxxxx.com/rest/V1/products/attributes/'.$attrCode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Cookie: PHPSESSID=e77125a143975ab79f234cdf0ae84abd'
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $options = [];
    foreach (json_decode($response, 1)['options'] as $datum){
        $options[$datum['value']] = $datum['label'];
    }
    return  $options;
}

function getProductsQty($sku){

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://xxxxxxxxx.com/rest//V1/stockStatuses/'.$sku,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer xxxxxxxxxxxxxxxxxxxx',
            'Cookie: PHPSESSID=faabcb94309db3bd1685ea4a5d6791eb'
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    $response = json_decode($response, 1);
    return ['qty' => $response['qty'],'is_in_stock' => $response['stock_status']];
}
function getProductsfromF11($page = 1, $pageCount=20){
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://xxxxxxxxx.com/rest/V1/products?searchCriteria[pageSize]=$pageCount&searchCriteria[currentPage]=".$page,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Cookie: PHPSESSID=faabcb94309db3bd1685ea4a5d6791eb'
        ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);
    return json_decode($response, 1);
}
