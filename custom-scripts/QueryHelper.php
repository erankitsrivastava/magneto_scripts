<?php 
include_once "MysqlConnect.php";

class QueryHelper extends MysqlConnect{
    
    private $allcats;
    private $sku_attribute_id;
    private $cat_attribute_id;
    
    public function __construct(){
        $this->conn = $this->initiateConnection();
        $this->setAttributeIds();
//         $this->setAllCats();
    }
    
    public function __destruct(){
        $this->conn = null;
    }
    
    public function getImageList() {
        $productswithcats = $this->getProductswithCats();
//         var_dump($productswithcats);
        $spc = array();
        $counter = 0;
        foreach ($productswithcats as $pid => $cs) {
            
            $sku = $this->getSku($pid);
            
            $counter++;
//             echo "<br>pid: ",$pid," counter:",$counter ;
            $catpathids = $this->getCatPathIds($cs, $pid); // returns array of all pathes for pid
            
//             echo "<br>",$catpathids[0];
            $catpathes = $this->getCatPaths($catpathids);          
            $image = $this->getImage($pid);
            if (empty($image)) continue;
            $sku = $this->getSku($pid);
            $spc[$pid]['sku'] = $sku;
            $spc[$pid]['image'] = $image;
//             echo "<br>pid: ",$image," counter:",$counter ;
            $spc[$pid]['catpathes'] = $catpathes;
            
        } 
        return $spc;
    }
    
    private function getProductswithCats() {
        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."cataloginventory_stock_item
            INNER JOIN ".$this->table_prefix."catalog_category_product
            ON ".$this->table_prefix."cataloginventory_stock_item.product_id = ".$this->table_prefix."catalog_category_product.product_id
            WHERE ".$this->table_prefix."cataloginventory_stock_item.manage_stock != '0'
             ");
        $productswithcats =  array();
        while ($row = $stmt->fetch()) {
            $productswithcats[$row['product_id']][] = $row['category_id'];
//             echo "<br>",$row['product_id'],":",$row['category_id'],":",$row['qty'],":",$row['is_in_stock'];
        }
        return $productswithcats;
    }
    
    
    
    private function getSku($pid) {


        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."catalog_product_entity_varchar
            WHERE entity_id = $pid  AND attribute_id = ".$this->sku_attribute_id."
        ");
        if ($row = $stmt->fetch()) {

           return $row['value'];
        }
    }
    
    private function getImage($pid) {
        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."catalog_product_entity_media_gallery
            WHERE entity_id = $pid  LIMIT 1
             ");
        if ($row = $stmt->fetch()) {
            return $row['value'];
            
        }
    }
    
    private function getCatPaths($catpathids) {
        $pathes = array();
        foreach ($catpathids as $catpathid) {
           $pathlist = explode ("/",$catpathid) ;
           $path = "";
           foreach ($pathlist as $pathid) {
               $path .= $this->getCatName($pathid)."/";
           }
           $pathes[] = $path;
        }
        return $pathes;
    }
    
    private function getCatName($catid) {
        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."catalog_category_entity_varchar
            WHERE entity_id = $catid  AND attribute_id = ".$this->cat_attribute_id."
             ");
        if ($row = $stmt->fetch()) {
//             echo "<br>",$row['attribute_id'],":",$row['value'];
               return $row['value'];
            
        }
    }
    
    private function getCatPathIds($cs,$pid) {
        $ids = join("','",$cs);
        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."catalog_category_entity
            WHERE entity_id IN ('$ids')
            ORDER BY level DESC
             ");
      
        $pathids = array();
        while ($row = $stmt->fetch()) {
//             echo "\nEID",$row['entity_id'];
            $pathids[] = $row['path'];
          
        }
        return $pathids;       
    }
    
    private function setAllCats() {
        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."catalog_category_entity LIMIT 1000
             ");
        
        while ($row = $stmt->fetch()) {
            $this->allcats[$row['entity_id']] = $row;
            
        }
    }
    
    private function setAttributeIds() {
        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."eav_attribute WHERE attribute_code =  'url_key' AND entity_type_id = 3
             ");
        
        if ($row = $stmt->fetch()) {
            $this->cat_attribute_id = $row['attribute_id']; 
            
        }else {
            
            exit ("attribute not found");
        }
        
        $stmt = $this->conn->query("
            SELECT * FROM ".$this->table_prefix."eav_attribute WHERE attribute_code = 'url_key' AND entity_type_id = 4
             ");
        
        if ($row = $stmt->fetch()) {
            $this->sku_attribute_id = $row['attribute_id'];
            
        }else {
            exit ("attribute not found ");
        }
        
    }
    
}
 
