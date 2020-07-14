<?php
$path = '/home/tonewo6/public_html/pub/media/catalog/product/';
$dir = "$path*.jpg";
//get the list of all files with .jpg extension in the directory and safe it in an array named $images
$images = glob( $dir );

//extract only the name of the file without the extension and save in an array named $find
var_dump(count($images));
foreach( $images as $image ):
    $img = explode($path, $image);
    if(isset($img[1])){
        $subdir = substr($img[1], 0, 1) .'/'.substr($img[1], 1, 1);
        $nimg =  $subdir.'/'. $img[1];
        if(file_exists($image) && !file_exists($path.$nimg)){
            
            echo "<p>".$image. " </p>";  
            echo "<p>".$path.$nimg. " </p>"; 
            echo "<p>".$path.$subdir. " </p>"; 
            if(!is_dir($path.$subdir)){
                mkdir($path.$subdir, 0777, true);
            }
                
            if(!copy($image, $path.$nimg)){
                die("unable to copy $image to the $path $nimg");
            }
        }
        
        
    }
endforeach;
