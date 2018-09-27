
<?php
define('CURDIR', dirname(__FILE__));

error_reporting(E_ALL);
date_default_timezone_set('Asia/ShangHai');

$inPath = CURDIR . "/../csv";
$outPath = CURDIR . "/../Temp/";

parseCSVConf($inPath, $outPath);

echo "ok";
//类名
function nameToClassName($filename){
    $arr = explode('_', $filename);
    $classname = '';
    for($i = 1; $i < count($arr); $i++){
        $classname .= ucfirst($arr[$i]);
    }
    return 'Temp' . $classname;
}

function parseCSVConf($inPath, $outPath) {
    echo $inPath;
   $files = scandir($inPath);
   foreach($files as $f) {
     $fullFile = $inPath."/".$f;
     if (is_file($fullFile)) {
       $type = strtolower(pathinfo($f, PATHINFO_EXTENSION));
       if( $type=='txt'||$type=='csv' ){
            $basename = basename($f, "." . $type);
            $classname = nameToClassName($basename);
            $myfile = fopen($outPath.$classname.".php", "w");
            $str = rCsvFile($fullFile);
            // 打开导入的文件
            $str =  "<?php\nnamespace Temp;\n\nclass " . $classname . $str . "\t];\n}\n";
            fwrite($myfile, $str);
            fclose($myfile);
       }
     }
   }
}

function rCsvFile($filename){
    $str = "\n{ \n\tconst init = [\n";
    $file = fopen($filename,'r');
    while ($data = fgetcsv($file)) { //每次读取CSV里面的一行内容
        $list[] = $data;
    }
    
    //格式化表
    if(count($list) > 1){
        $names = $list[0];
        
        for($i = 1; $i < count($list); $i++){
            $info = $list[$i];
            $row_str = "\t\t'". $info[0] . "' => [";
            
            for($j = 0; $j < count($info); $j++){
                if(empty($info[$j]) && $info[$j] != 0){
                    $info[$j] = nil;
                }
                if (!is_numeric($info[$j])){
                    $info[$j] = "\"" . $info[$j] . "\"";
                }
                
                if($j < count($info) - 1){

                    $row_str .= "'".$names[$j] . "' => " . $info[$j] . ", ";
                }else{
                    if($i < count($list) - 1){
                        $row_str .= "'". $names[$j] . "' => " . $info[$j] . "],\n";
                    }else{
                        $row_str .= "'".$names[$j] . "' => " . $info[$j] . "]\n";
                    }
                }
                
            }
            $str .= $row_str;
        }
    }
        
    return $str;
}
