<?php
set_time_limit(0);ini_set('memory_limit','768M');require '/wordpress/wp-load.php';wp_set_current_user(1);
use Giuliomax\Passport\{Jobs,Store,Api};
$start=microtime(true);$csv="sku,name,materials,care\n";for($i=0;$i<10000;$i++)$csv.='PERF-'.str_pad($i,5,'0',STR_PAD_LEFT).',Product '.$i.",Wood,Damp cloth\n";
$p=Jobs::csv($csv,[],'create','sku',false);$preview=microtime(true)-$start;$job=Jobs::create('csv',$p,count($p['rows']));
header('Content-Type: application/json');echo wp_json_encode(['job_id'=>$job['id'],'preview_seconds'=>$preview,'rows'=>10000,'memory_mb'=>memory_get_peak_usage(true)/1048576]);
