<?php

define('DEBUG', TRUE);

function banners($s){
  echo "===========================\n";
  echo "[*] PHP Mass FTP Checker\n";
  echo "[*] Author  : vix3r\n";
  echo "[*] Usage   : php {$s} <options>\n";
  echo "[*] Example : php {$s} -f file.txt\n";
  echo "[*]\n";
  echo "[*] List Format '<host> <user> <password>'\n";
  echo "[*] Delimiter:  space ( ), colon (:), pipe(|)\n";
  echo "===========================\n\n";
}

function read_file($file){
    if(!file_exists($file)){
        echo "[!] File {$file} Not Exists\n";
        exit;
    }

    if(function_exists('file_get_contents')){
        return file_get_contents($file);
    }

    $fp = fopen($file, "r");
    if ( !$fp ) {
       throw new Exception("[!] Failed open file {$file}.\n");
       exit;
    }

    $content = fread($fp, filesize($file));
    fclose($fp);
    return $content;
}

function write_to_file($content, $filename){
    if( function_exists('file_put_contents') ){
        if( file_put_contents($filename, $content, FILE_APPEND | LOCK_EX) ){
            return true;
        }
    }
    
    if (!$fp = fopen($filename, 'a')){
        return false;
    }

    if (flock($fp, LOCK_EX)){
        fwrite($fp, $content);
        flock($fp, LOCK_UN);
    }else{
        return false;
    }

    fclose($fp);
}

function split_credentials($line){
    $result = array();
    $data  = array();
    
    if( $split = array_values(preg_split('/\s+/', $line)) ){
        
        if( count($split) == 3 ){
            $data = $split;
        }
    }else if( $split = array_values(preg_split('/:/', $line)) ){
        if( count($split) == 3 ){
            $data = $split;
        }
    }else if( $split = array_values(preg_split('/\|/', $line)) ){
        if( count($split) == 3 ){
            $data = $split;
        }
    }

    if( $data ){
        $result['host'] = trim($data[0]);
        $result['user'] = trim($data[1]);
        $result['pass'] = trim($data[2]);
    }

    return $result;
}

function ftpconn($host){
    if( !$conn = @ftp_connect($host) ){
        throw new Exception("[!] Cannot create ftp connection to {$host}\n");
        return false;
    }
    
    return $conn;
}

function ftplogin($conn, $data){
    if (!@ftp_login($conn, $data['user'], $data['pass'])) {
        throw new Exception("[!] Failed connect to {$data['host']}\n");
        return false;
    }

    return true;
}

function is_up($host){
    $is_up = FALSE;
    if( $namel = gethostbynamel($host) ){
        $is_up = TRUE;
		echo "[+] {$host} is up\n";
        if(DEBUG){
            if( count($namel) > 1 ){
                for( $i = 0;$i < count($namel);$i++ ){
                    $no = $i+1;
                    if($i == 0 ){
                        echo "[+] IP's : {$no}. {$namel[$i]}\n";
                    }else{
                        echo "           {$no}. {$namel[$i]}\n";
                    }
                }
            }else{
                echo "[+] IP   : {$namel[0]}\n";
            }
        }
    }else{
        echo "[!] {$host} seems to be down!\n";
    }
    
    return $is_up;
}

function _start($data){
    $timeout = 5;

    if( is_up($data['host']) ){
        $conn = FALSE;

        try {
            $conn = ftpconn($data['host']);
        } catch (Exception $e) {
            echo $e->getMessage();
        }

        if($conn){
            try {
                if (ftplogin($conn, $data)) {
                     $result  = "Host: {$data['host']}\n";
                     $result .= "User: {$data['user']}\n";
                     $result .= "Pass: {$data['pass']}\n";

                     echo "[+] Connected\n";
                     echo preg_replace('/(\w+:)/', '    $1', $result);

                     write_to_file("{$result}\n", "ftplive.txt");

                }
            } catch (Exception $e) {
                echo $e->getMessage();
            }


            ftp_close($conn);
        }
    }
}

banners($argv[0]);
$opt = getopt('f:');

if( isset($opt['f']) && $opt['f'] ){
    $file = $opt['f'];
    if( file_exists($file) ){
        if($read = read_file($file)){
            $list = array_values(array_map('trim',explode("\n",$read)));
            if( $list){
                $count = count($list);
                $no = 0;

                foreach($list as $line){
                    if($line){
                        $no++;
                        $num = "[{$no} of {$count}]";
                        if( $split = split_credentials($line) ){
                            echo "{$num} {$line}\n";
                            _start($split);
                        }else{
                            echo "{$num} Format failed: {$line}\n";
                        }
                        echo "--------------------------------------\n\n";
                    }
                }  
            }
        }
    }
}
