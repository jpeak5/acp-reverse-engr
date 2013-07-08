<?php

class user{
    public $fullName;
    public $email;
    public $login;
    public $isMobileUser;       //is mobile user
    public $isRegisteredUser;   //is registered user
    public $pID;                //ACP principal ID
    public $role;               //owner|viewer
    public $acpProfile;          // convenience link to user profile page
    public $mtime;
    public $hmdate;
    public $hmtime;

    
    public static function instantiate($params){
        $inst = new self();
        $keys = get_object_vars($inst);
        if(!is_array($params)){
            $params = (array)$params;
        }
        foreach($params as $k=>$v){
            if(array_key_exists($k, $keys)){
                $inst->$k = $v;
            }
        }
        return $inst;
    }
    
}

function dir_get_contents($root){
    $dirs = array();
    foreach(scandir($root) as $file){
        $fullpath = $root.DIRECTORY_SEPARATOR.$file;
        $mtime = filemtime($fullpath);
        $inRange = $mtime >= 1363737600 and $mtime <=1368316800;
        if($file == '.' or $file == '..' or !$inRange){
            if(!$inRange) logmsg( sprintf("skipping %s, mtime %d not in range.\n", $file, $mtime));
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

function mergeUsers(user $a,user $b){
    $fields = get_class_vars('user');
    $aa = clone $a;
    foreach($fields as $field){
        
        if(!empty($b->$field)){
            if(empty($aa->$field)){
                $aa->$field = $b->$field;
            }else{
                if($aa->$field != $b->$field){
                    logmsg( sprintf("differences exist in user objects for user pID = %d at field %s", $field, $a->pID));
                    return false;
                }
            }
        }
    }
    return $aa;
}

function get_zip_contents($file){
    $z = new ZipArchive();
    if($z->open($file)){
        $xdoc   = new DOMDocument();
        $index = 'indexstream.xml';
        $zstr = $z->getFromName($index);
        if(strlen($zstr)<=1){
            $z->close();
            return false;
        }
        $stat = $z->statName($index);
        $right_time = $stat['mtime'] > 1363737600 and $stat['mtime'] < 1368316800;
        if(!$right_time){
            return false;
        }
        $xdoc->loadXML($zstr);
        $xpath  = new DOMXPath($xdoc);

        $users_nodes  = $xpath->query('//Object[role][isRegisteredUser="true"]');
        $users = array();
        if(!is_null($users_nodes)){
            logmsg("extracting metadata\n");
            foreach($users_nodes as $user_node){
                $d= array();
                $details = $user_node->childNodes;
                foreach($details as $detail){
                    $d += array($detail->nodeName => $detail->nodeValue);
                }
                
                $u = user::instantiate($d);
                if($u->role == 'owner'){
                    $u->acpProfile = htmlentities('https://connect.lsu.edu/admin/administration/user/principal/info?account-id=7&principal-id='.$u->pID);
                }
                if(!isset($users[$u->pID])){
                    $users[$u->role][$u->pID] = $u;
                }else{
                    $merge = mergeUsers($users[$u->role][$u->pID], $u);
                    if($merge){
                        $users[$u->role][$u->pID] = $merge;
                    }else{
                        continue;
                    }
                }
                $u->mtime = filemtime($file);
                $u->hmdate = strftime('%F',$u->mtime);
                $u->hmtime = strftime('%T',$u->mtime);
            }
            return $users;
        }else{
            logmsg( "xpath is null");
        }
    }else{
        die(sprintf('Could not open zip %s',$file));
    }
//    $z->close();
}


/**
   * 
   * @param stdClass $object
   * @param string $element_name name of the element to return
   */
  function toXMLElement($object, $element_name){
      $tmp = new DOMDocument('1.0', 'UTF-8');
      $e   = $tmp->createElement($element_name);
      assert(get_class($object)=='user');

      foreach(get_object_vars($object) as $key => $value){

              $e->appendChild($tmp->createElement($key, $value));
      }

      return $e;
      
  }
  
  
  function logmsg($msg){
      if(($handle = fopen('log.txt', 'a'))!=false){
          fwrite($handle, $msg);
      }else{
          die("could not open fiel for writing");
      }
      echo $msg;
      fclose($handle);
  }
  
//    /**
//   * 
//   * @param tbl_model[] $records
//   * @param string $root_name the name that the inheriting report uses as its XML root element
//   * @param string $child_name name that the inheriting report uses as child container element
//   * @return DOMDocument Description
//   */
//  function toXMLDoc($records, $root_name, $child_name){
//      $xdoc = new DOMDocument();
//      $root = $xdoc->createElement($root_name);
//      
//      if(empty($records)){
//          return false;
//      }
//      
//      foreach($records as $record){
//
//          $elemt = $xdoc->importNode(,true);
//          $root->appendChild($elemt);
//      }
//      $xdoc->appendChild($root);
//      return $xdoc;
//  }

//$root_dir = "/home/jpeak5/acp-reverse-engr/orphans";
  $root_dir = "/mnt/salvage/7";

//truncate log file
if(($handle = fopen('log.txt','w'))!=false){
    ftruncate($handle, 0);
}else{
    die("Couldn't open file for truncate");
}
$filesys = dir_get_contents($root_dir);
$recordings = array_filter($filesys,'has_output');

$records = array();
foreach($recordings as $id=>$files){
    foreach($files as $file){
        
        if($file == 'output'){
            
            $src = $root_dir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR.$file;
            $target = 'renamed/';
            logmsg( sprintf("unzipping dir %s\n", $src));
            $users = get_zip_contents($src);
            
            
//            $enough_space = disk_free_space($target) > filesize($src) + 2048;
//            if($enough_space){
//                logmsg( sprintf("copying %s (%f Mb) to %s; free space (Gb) remaining: %f/%f\n", $file, filesize($src)/(1024*1024),$target.$id.'.zip',disk_free_space($target)/(1024*1024*1024), disk_total_space($target)/(1024*1024*1024)));
//                $copyResult = copy($root_dir.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR.$file, $target.$id.'.zip');
//                if($copyResult){
//                    logmsg( "done!\n");
//                }else{
//                    die("FAIL\n");
//                }
//            }else{
//                die(sprintf("not enough space remians on disk: %f/%f free", disk_free_space($target), disk_total_space($target)));
//            }
            if(!$users){
                continue;
            }else{
                $records[$id] = $users;
            }
        }
        logmsg("---\n");
    }
    
    $xdoc = new DOMDocument();
    $root = $xdoc->createElement('files');
    //now cast to XML
    foreach($records as $id=>$groups){
        $fnode = $xdoc->createElement('file');
        $fnode->setAttribute('id', $id);
        
        foreach($groups as $group=>$users){
            $gnode = $xdoc->createElement($group);
            foreach($users as $user){
                $element = $xdoc->importNode(toXMLElement($user, 'user'),true);
                $gnode->appendChild($element);
                
            }
            $fnode->appendChild($gnode);
        }
        $root->appendChild($fnode);
    }
    $xdoc->appendChild($root);
    
    
    
}

$filename = 'userdata.xml';
if(($handle = fopen($filename,'w')) == false){
    die("couldn't open file for writing");
}else{
    fwrite($handle, $xdoc->saveXML());
    logmsg("writing out XML to {$filename}\n");
}
fclose($handle);


logmsg("done working; exit()\n");
exit(0);
?>
