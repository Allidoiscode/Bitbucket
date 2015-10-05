<?php
// Bitbucket API
// Written by Allidoiscode aka Precise


// Reads an ini file into an array
function IniRead($file = "Repos.ini"){
	$ini = null;
	if (file_exists($file)){
		$ini = parse_ini_file($file, true);
	}
	return $ini;
}


// Writes an array to an ini file
function IniWrite($array, $file = "Repos.ini"){
    $res = array();
    foreach($array as $key => $val){
        if(is_array($val)){
            $res[] = "[$key]";
            foreach($val as $skey => $sval) $res[] = "$skey = ".(is_numeric($sval) ? $sval : '"'.$sval.'"');
        }
        else $res[] = "$key = ".(is_numeric($val) ? $val : '"'.$val.'"');
    }
    SafeWrite($file, implode("\r\n", $res));
}


// File lock safe write file
function SafeWrite($fileName, $dataToSave){    
	if ($fp = fopen($fileName, 'w')){
        $startTime = microtime(TRUE);
        do { $canWrite = flock($fp, LOCK_EX);
			if(!$canWrite) usleep(round(rand(0, 100)*1000));
        } while ((!$canWrite)and((microtime(TRUE)-$startTime) < 5));
        if ($canWrite){   
			fwrite($fp, $dataToSave);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}


// Gets the ini file changeset count for a repo
function IniChangesetCount($repouser, $reponame, $file, $ini = null){
	$filecom = 0;
	if ($ini == null){
		if (file_exists($file)){
			$ini = IniRead($file);
			$repo = $repouser . "-" . $reponame;
			$filecom = $ini[$repo]['CommitCount'];
			if (strlen($filecom) == 0){
				$filecom = 0;
			}	
		}
	}
	else {
		$repo = $repouser . "-" . $reponame;
		$filecom = $ini[$repo]['CommitCount'];
		if (strlen($filecom) == 0){
			$filecom = 0;
		}			
	}
	return $filecom;
}


// Gets the changeset count for a repo
function ChangesetCount($repouser, $reponame, $user, $pass){
	$url = "https://bitbucket.org/api/1.0/repositories/" . $repouser . "/" . $reponame . "/changesets?limit=50";
	
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);	
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	if (substr($url, 0, 5) == "https"){ curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); }
	curl_setopt($curl, CURLOPT_ENCODING, "gzip, deflate");
	curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass");
	$response = curl_exec($curl);
	curl_close($curl);
	
	$json = json_decode($response);
	$cscount = $json->count;	
	
	return $cscount;		
}


// Gets the last node from a changeset array
function LastNode($changeset){
	for($i = 0; $i < count($changeset) - 3; $i++){
		$lastnode = $changeset[$i];
	}
	return $lastnode;
}


// Gets changeset info for a single node
function Node($repouser, $reponame, $user, $pass, $node){
	$url = ChangesetUrl($repouser, $reponame, $user, $pass, $node);
	
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_TIMEOUT, 35);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 35);	
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	if (substr($url, 0, 5) == "https"){ curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); }
	curl_setopt($curl, CURLOPT_ENCODING, "gzip, deflate");
	curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass");	
	$response = curl_exec($curl);	
	curl_close($curl);		
	
	return $response;	
}


// Gets changeset info for all nodes
function Nodes($repouser, $reponame, $user, $pass, $changesets){
	$cscount = (count($changesets) - 2);	
	$urls = null;
	for($i = 0; $i < $cscount; $i++){
		$node = $changesets[$i];
		$url = ChangesetUrl($repouser, $reponame, $user, $pass, $node);
		$urls[$i] = $url;
	}
	
	$i = 0;
	$responses = null;
	foreach($urls as $url){
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_TIMEOUT, 35);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 35);	
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		if (substr($url, 0, 5) == "https"){ curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); }
		curl_setopt($curl, CURLOPT_ENCODING, "gzip, deflate");
		curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass");
		$response = curl_exec($curl);	
		curl_close($curl);		
		$responses[$i] = $response;
		$i++;
	}
	
	return $responses;	
}


// Loads a changeset node URL
function ChangesetUrl($repouser, $reponame, $user, $pass, $node){
	$url = "https://bitbucket.org/api/1.0/repositories/" . $repouser . "/" . $reponame . "/changesets";
	$url .= "/" . $node . "/diffstat";
	return $url;
}


// Gets all changeset nodes for a repository
function Changesets($repouser, $reponame, $user, $pass, $cscount = 50, $limit = 50, $cs = null, $cc = 0, $last = "", $cstr = "|", $authors = null, $counts = null, $max = 10, $mc = 0){	
	$url = "https://bitbucket.org/api/1.0/repositories/" . $repouser . "/" . $reponame . "/changesets?limit=" . $limit;
	if ($cs != null){
		$changesets = $cs;
		if ($last != ""){
			$url .= "&start=" . $last;
		}
	}	
	$curl = curl_init();	
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);	
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
	if (substr($url, 0, 5) == "https"){ curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); }
	curl_setopt($curl, CURLOPT_ENCODING, "gzip, deflate");
	curl_setopt($curl, CURLOPT_USERPWD, "$user:$pass");
	$response = curl_exec($curl);
	curl_close($curl);		
	
	$json = json_decode($response);	
	$csarr = $json->changesets;
	$i = 0;
	$last = "";
	if ($cc != 0){ $i = $cc; }
	
	foreach($csarr as $key => $val){
		$node = $val->raw_node;
		$author = $val->author;
		if (!strpos($cstr, $node)){
			$cstr .= $node . "|";
			$changesets[$i] = $node;
			$i++;
			$counts[$author]++;
			$authors[$author] .= $node . "|";
		}
		if ($last == ""){ $last = $node; }
	}
	
	$mc++;
	if ($mc > $max){
		$changesets[count($changesets)] = $authors;
		$changesets[count($changesets)] = $counts;			
		return $changesets;
	}		
	if ($i < $cscount){
		$cs = $changesets;
		$cc = $i;
		$changesets = Changesets($repouser, $reponame, $user, $pass, $cscount, $limit, $cs, $cc, $last, $cstr, $authors, $counts, $max, $mc);		
		return $changesets;
	}		
	$changesets[count($changesets)] = $authors;
	$changesets[count($changesets)] = $counts;	
	
	return $changesets;
}
?>