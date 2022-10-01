<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'QueryHelper.php';

$qh = new QueryHelper;

$products = $qh->getImageList();
$date =  date('dmYHi');

foreach ($products as $product) {
    foreach ($product['catpathes'] as $catpath) {
        $file = getFilename($product['image']);
        $catpath = addpath($catpath,$date);
        
       echo  "<br>\nDest: ",$catpath,$product['sku'],".",$file['type'];
       echo "<br>\nSource: ","../media/catalog/product".$product['image'];
      copy ("../media/catalog/product".$product['image'], $catpath.$product['sku'].".".$file['type']);
    }
}


echo "\n";


function getFilename($image) {
    $parts = explode ("/",$image);
    $part = array_values(array_slice($parts, -1))[0];
    $parts = explode (".",$part);
    $file = array();
    $file["type"] = array_values(array_slice($parts, -1))[0];
    $file['name'] = $parts[0];
    return $file;
    
}

function addpath($catpath,$date) {
    $dirs = explode("/", $catpath);
    array_shift($dirs);
    $catpath = implode("/", $dirs);
    $catpath = $date."/".$catpath;
    if (!is_dir($catpath)) {
             mkdir($catpath,0777,true);
    }

    return $catpath;
}
