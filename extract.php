<?php



function dir_get_contents($root){
    $dirs = array();
    foreach(scandir($root) as $file){
        $fullpath = $root.DIRECTORY_SEPARATOR.$file;
        if($file == '.' or $file == '..'){
            continue;
        }elseif(is_dir($fullpath)){
            $dirs[$file] = dir_get_contents($fullpath);
        }else{
            $dirs[] = $file;
        }
    }
    return $dirs;
}

function has_output($filesys){
    if(in_array('output', $filesys)){
        return true;
    }else{
        return false;
    }
}

function get_zip_contents($file){
    $z = new ZipArchive();
    if($z->open($file)){
        echo "success";
        echo $z-getFomName('indexstream.xml');
//        var_dump($z);

    }else{
        die(sprintf('Could not open zip %s',$file));
    }
//    $z->close();
}

$root_dir = "/home/jpeak5/Documents/salvage/salvage/";
$filesys = dir_get_contents($root_dir);
$recordings = array_filter($filesys,'has_output');

$records = array();
foreach($recordings as $id=>$files){
    foreach($files as $file){
        if($file == 'output'){
            echo sprintf("getting listing for %s\n", $root_dir.$id.'/'.$file);
            $records[] = get_zip_contents($root_dir.$id.'/'.$file);
        }
    }
    
}


?>
