<?php 

namespace App\Services;
/**
* 
*/
trait ApiPlatformTraitService
{
   public function saveDataToFile($data, $fileName, $ext = 'txt')
   {
        $filePath = storage_path().'/app/ApiPlatform/'.$this->getPlatformId().'/'.$fileName.'/'.date('Y');
        if (!file_exists($filePath)) {
            mkdir($filePath, 0755, true);
        }
        $file = $filePath .'/'. date('Y-m-d-H-i') .'.'. $ext;
        //write json data into data.json file
        if (file_put_contents($file, $data)) {
            //echo 'Data successfully saved';
            return $data;
        }
        return false;
    }

    public function removeApiFileSystem($expireDate)
    {
        $directoryList = array(
             "api_platform" => storage_path().'/app/ApiPlatform/',
             "xml_directory" => \Storage::disk('xml')->getDriver()->getAdapter()->getPathPrefix()
        );
        return $this->removeFileSystem($directoryList,$expireDate);
    }

    public function removeFileSystem($directoryList,$expireDate)
    {
        $expireDateString = strtotime($expireDate);
        foreach($directoryList as $directory){
            $allFiles = $this->findAllFiles($directory);
            if($allFiles){
                foreach($allFiles as $file){
                    if(filemtime($file) < $expireDateString){
                        $deleteFiles[]= $file;
                    }
                }
            }
            if(isset($deleteFiles)){
                \File::delete($deleteFiles);
            }
        }
    }

    public function findAllFiles($dir)
    {
        if(file_exists($dir)){
            $result = "";
            $root = scandir($dir);
            foreach($root as $value)
            {
                if($value === '.' || $value === '..') {continue;}
                if(is_file("$dir/$value")) {
                    $result[]="$dir/$value";continue;
                }
                if($subFile = $this->findAllFiles("$dir/$value")){
                    foreach($subFile as $value)
                    {
                        $result[]=$value;
                    }
                }
            }
            return $result;
        }
    }

    public function sendMailMessage($alertEmail, $subject, $message)
    {
        mail("{$alertEmail}, jimmy.gao@eservicesgroup.com", $subject, $message, $headers = 'From: admin@shop.eservciesgroup.com');
    }

    public function generateMultipleSheetsExcel($fileName,$cellDataArr,$path)
    {
        $excelFile = \Excel::create($fileName, function($excel) use ($cellDataArr) {
            // Our first sheet
            foreach($cellDataArr as $key => $cellData){
                if($cellData){
                    $excel->sheet($key, function($sheet) use ($cellData) {
                        $sheet->rows($cellData);
                    });
                }
            }
        })->store("xlsx",$path);
        if($excelFile){
            $attachment = array("path"=>$this->getDateReportPath(),"file_name"=>$fileName.".xlsx");
            return $attachment;
        }
    }

}