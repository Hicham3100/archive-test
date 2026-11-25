<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ocr_worker
{
    private $ci;
    private $tesseract = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    private $tessdata = 'C:\\tesseract-models';
    private $pdftoppm = 'C:\\Users\\HP\\AppData\\Local\\Microsoft\\WinGet\\Packages\\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\\poppler-26.09.0\\Library\\bin\\pdftoppm.exe';
    private $pdfinfo = 'C:\\Users\\HP\\AppData\\Local\\Microsoft\\WinGet\\Packages\\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\\poppler-26.09.0\\Library\\bin\\pdfinfo.exe';

    public function __construct()
    {
        $this->ci =& get_instance();
    }

    public function validate_language($language)
    {
        $allowed = ['fra','eng','ara','fra+eng','fra+ara','ara+eng','fra+ara+eng'];
        return in_array($language,$allowed,true) ? $language : 'fra+ara+eng';
    }

    private function safe_run($args,$timeoutSeconds)
    {
        if (!is_array($args)||empty($args)||!is_file($args[0])) return [false,'Binaire OCR introuvable'];
        $timeoutMilliseconds=max(1000,(int)$timeoutSeconds*1000);
        $command='"'.str_replace('"','', $args[0]).'"';
        for($i=1;$i<count($args);$i++){
            $arg=(string)$args[$i];
            if(preg_match('/[\r\n]/',$arg))return [false,'Argument OCR invalide'];
            $command.=' '.(preg_match('/^[A-Za-z0-9_:\\/.\\-]+$/',$arg)?$arg:'"'.str_replace('"','',$arg).'"');
        }

        $descriptors=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
        $process=proc_open($command,$descriptors,$pipes,null,['TESSDATA_PREFIX'=>$this->tessdata]);
        if(!is_resource($process))return [false,'Impossible de démarrer OCR'];
        fclose($pipes[0]);
        stream_set_blocking($pipes[1],true);stream_set_blocking($pipes[2],true);
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);

        $deadline=microtime(true)+$timeoutMilliseconds/1000;
        do{$status=proc_get_status($process);if($status['running']&&microtime(true)>=$deadline){proc_terminate($process);break;}usleep(10000);}while($status['running']);
        $status=proc_get_status($process);
        if($status['running']){proc_terminate($process);proc_close($process);return [false,'Timeout OCR'];}
        $code=proc_close($process);
        if($code!==0)return [false,'Échec OCR'];
        return [true,['stdout'=>$stdout,'stderr'=>$stderr]];
    }

    public function process_document($document,$language='fra+ara+eng',$timeout=300)
    {
        $language=$this->validate_language($language);
        $source=$document['storage_path']??'';
        if(!is_file($source))return [false,'Document introuvable'];
        $real_root=realpath('C:\\archive-ged-private');$real_source=realpath($source);
        if(!$real_root||!$real_source||strpos(str_replace('/','\\',$real_source),rtrim(str_replace('/','\\',$real_root),'\\').'\\')!==0)return [false,'Source hors stockage privé'];

        $temp_dir=sys_get_temp_dir().'\\archive-ocr-'.bin2hex(random_bytes(8));
        mkdir($temp_dir,0700,true);
        $text='';$page_count=0;

        try{
            $mime=$document['mime_type']??'';
            if($mime==='application/pdf'){
                $pdfinfo=$this->safe_run([$this->pdfinfo,$source],60);
                if(!$pdfinfo[0])return [false,$pdfinfo[1]];
                $pages=1;if(preg_match('/^Pages:\\s+(\\d+)/m',$pdfinfo[1]['stdout'],$m))$pages=(int)$m[1];
                $page_count=max(1,$pages);
                $prefix=$temp_dir.'\\page';
                $convert=$this->safe_run([$this->pdftoppm,'-jpeg','-r','150',$source,$prefix],$timeout);
                if(!$convert[0])return [false,$convert[1]];
                $images=glob($prefix.'-*.jpg')?:[];
            }else{
                $images=[$source];$page_count=1;
            }

            foreach($images as $image){
                $base=$temp_dir.'\\ocr-'.bin2hex(random_bytes(8));
                $ocr=$this->safe_run([$this->tesseract,$image,$base,'-l',$language,'--oem','1','--psm','6'],$timeout);
                if(!$ocr[0])return [false,$ocr[1]];
                $text_file=$base.'.txt';
                if(is_file($text_file))$text.=trim(file_get_contents($text_file))."\n";
            }
            $sha=hash_file('sha256',$source);
            return [true,['text'=>trim($text),'page_count'=>$page_count,'sha'=>$sha,'language'=>$language,'engine'=>'tesseract','engine_version'=>'5.4.0']];
        }finally{foreach(glob($temp_dir.'\\*')?:[] as $file){@unlink($file);}@rmdir($temp_dir);}
    }
}
