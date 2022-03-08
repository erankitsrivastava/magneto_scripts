<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
ini_set('max_execution_time', 0);

use \Magento\Framework\App\Bootstrap;

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$instance = \Magento\Framework\App\ObjectManager::getInstance();
$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');
$state = $objectManager->get('\Magento\Framework\App\State');

$state->setAreaCode('adminhtml');
$productcollection = $objectManager->create('Magento\Catalog\Model\ResourceModel\Product\Collection');
$productcollection->addAttributeToFilter('entity_id', array('gt' => 29553));
$productcollection->addAttributeToFilter('status', array('eq' => '1'));
$productcollection->addAttributeToFilter('visibility', array('in' => [4,3,2]));

$file = fopen('errorlog.csv', 'w+');
$file2 = fopen('processedlog.csv', 'w+');
fputcsv($file, array('sku', 'id', 'message'));
fputcsv($file2, array('sku', 'id', 'message'));

if (count($productcollection)) {
    $i = 0;
    foreach ($productcollection as $productdata) {
        try {
            var_dump($i);
            $productRepo = $objectManager->create('Magento\Catalog\Api\ProductRepositoryInterface');
            var_dump('initiated...');;
            $product = $productRepo->getById($productdata->getEntityId(), true, 0);
            var_dump('product loaded.......');;
            $exiMediaGallery = $product->getMediaGalleryEntries();
            $newImagegalry = [];$skipThis = false;
            foreach ($exiMediaGallery as $key => &$val) {
                var_dump('processing media gallary.......');;
                /
                /*if(current($val->getTypes()) == 'thumbnail'){*/
                if(in_array(current($val->getTypes()), ['thumbnail', 'small_image'])){
                    if($val->isDisabled() == 1){
                        $skipThis = true;break;
                    }
                    $val->setDisabled(true);
                }
                $newImagegalry[$key] = $val;
            }

            if($skipThis || empty($newImagegalry)){
                fputcsv($file2, array($product->getSku(), $product->getId(), "skiped Disabling Thumbnail"));
                var_dump('skipping product save.......');
                continue;
            };


            var_dump('saving prodcut.......');;
            $product->setMediaGalleryEntries($newImagegalry);
            $product->save();
            $productRepo->save($product);
            var_dump('saved prodcut.......');;

            var_dump($product->getSku());
            var_dump($product->getId());
            $i++;
            fputcsv($file2, array($product->getSku(), $product->getId(), "Disabled Thumbnail"));
        }catch (\Exception $e){
            fputcsv($file, array($product->getSku(), $product->getId(), $e->getMessage()));
            /*echo $e;*/
        }
    }
    fclose($file);
    fclose($file2);
    var_dump($i);
    var_dump("DONE");
} else {
    echo 'No Product Found';
}

die(__FILE__.__LINE__);
