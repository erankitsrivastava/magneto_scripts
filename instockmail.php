<?php

$fileName = __DIR__ ."/instock_log.csv";
$emailId = "01aakashsrivastava@gmail.com";

/*$emailId = "emile@green4ever.shop";*/

ini_set('memory_limit','-1');
ini_set('display_errors', 1);
try {
    unset($_GET['scripttest']);

    require __DIR__ . '/../app/bootstrap.php';
} catch (\Exception $e) {
    echo $e->getMessage();
    exit(1);
}

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$app = $bootstrap->createApplication('Magento\Framework\App\Http');
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get('\Magento\Framework\App\State');
$state->setAreaCode('frontend');

$resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
$connection = $resource->getConnection();

$cpe_table = $resource->getTableName('catalog_product_entity');
$csi_table = $resource->getTableName('cataloginventory_stock_item');

$file = fopen($fileName, 'r');
$oldSkuWithNoStock = fgetcsv($file);
if(!$oldSkuWithNoStock || !is_array($oldSkuWithNoStock)){
    $oldSkuWithNoStock = [];
}
fclose($file);

$sqlQuery = "SELECT cpe.sku FROM $cpe_table cpe RIGHT JOIN $csi_table csi ON cpe.entity_id = csi.item_id where csi.qty = 0  GROUP BY cpe.sku ORDER BY cpe.sku ASC";

$skuWithNoStock = $connection->fetchCol($sqlQuery);

$file = fopen($fileName, 'w');
fputcsv($file, array_filter($skuWithNoStock));
fclose($file);
 
$skuWithPreviouslyNoStock = [];
if(!empty($skuWithNoStock)){
    $skusString = implode('","', array_filter($skuWithNoStock));
    $skusString = '"'.$skusString.'"';

    $sqlQuery = "SELECT cpe.sku, csi.qty FROM $cpe_table cpe RIGHT JOIN $csi_table csi ON cpe.entity_id = csi.item_id where csi.qty > 0 AND cpe.sku IN ('G4E_00711') GROUP BY cpe.sku ORDER BY cpe.sku ASC";

    $skuWithPreviouslyNoStock = $connection->fetchCol($sqlQuery);
    $neededHeaderKey = $mailContent = false;
    $header = $csvData = [];


}
die(__FILE__);

if($mailContent){
    $mailContent .= "</ol></body></html>";
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    mail($emailId,"Article NA notification", $mailContent, $headers);
    echo "Send a Artical notification mail";
}else{
    echo "No new Artical available for notification mail";
}
