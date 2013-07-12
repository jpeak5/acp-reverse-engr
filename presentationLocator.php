<?php


class presentationLocator{

    public $file;
    public $presentations;
    
    public function __construct($file){
        $this->file = $file;
        $this->presentations = array();
    }

    private function getFileContents(){
        return file_exists($this->file) ? file_get_contents($this->file) : false;
    }

    public function getDocumentList(){
        if(($content = $this->getFileContents()) === false){
            die('file is empty');
        }
        
        $xdoc = simplexml_load_string($content);
        
        # die(strlen($content));
        
        $xpath = '/root/Message/Array/Object/newValue/documentDescriptor';
        
        foreach($xdoc->xpath($xpath) as $doc){
            //some of these elements are empty and thus, worthless to this task
            if((string)$doc->playbackFileName == '') continue;
            
            $obj = new stdClass();
            $obj->playbackFilename = (string)$doc->playbackFileName;
            $obj->originatingSco   = (string)$doc->originatingSco;
            $obj->name             = (string)$doc->theName;
            
            //we don't need dupes
            if(array_key_exists($obj->playbackFilename, $this->presentations)){
                
                if($this->presentations[$obj->playbackFilename] != $obj){
                    die('dupe URL for diferent objects');
                }
            }
            $this->presentations[$obj->playbackFilename] = $obj;
            
        }
        return count($this->presentations) == 0 ? false : $this->presentations;
    }
    
    public function processList($list){
        $out = '<h1>'.$this->file.'</h1>';
        foreach($list as $l){
            $out.="<h2>".$l->name."</h2>";
            $out.="<ul>";
            foreach(get_object_vars($l) as $k=>$v){
                $out.="<li>";
                $out.=$k.": ".$v;
                $out.="</li>";
            }
            $out.="</ul>";
            $url = explode('/',$l->playbackFilename);
            $sco = sprintf('<fileshare>/7/%s/source',$l->originatingSco); 
            $out.="<p>";
            $out.=htmlentities(sprintf("Copy the file %s to your desktop and upload it as new content, assigning the url %s",$sco,$url[2]));
            $out.="</p>";
            
        }
        return $out;
    }
}

$loc = new presentationLocator($argv[1]);
if(($list = $loc->getDocumentList())!== false){
    printf("found %d document references\n",count($list));
    # var_dump($list);
    
    file_put_contents('out.html',$loc->processList($list));
}
exit(0);
?>