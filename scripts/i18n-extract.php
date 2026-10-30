<?php
/** Development-only extraction of literal WordPress gettext calls. */
$out=[];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator('giuliomax-product-passport')) as $file){
 if($file->getExtension()!=='php'||str_contains($file,'/vendor/'))continue;
 $source=file_get_contents($file);
 preg_match_all('/\b(?:__|esc_html__|esc_attr__)\(\s*(\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")\s*,\s*[\'"]giuliomax-product-passport[\'"]\s*\)/s',$source,$matches,PREG_OFFSET_CAPTURE);
 foreach($matches[1] as [$literal,$offset]){$out[]=['id'=>eval('return '.$literal.';'),'file'=>substr((string)$file,strlen('giuliomax-product-passport/')),'line'=>substr_count(substr($source,0,$offset),"\n")+1];}
}
echo json_encode($out,JSON_UNESCAPED_UNICODE);
