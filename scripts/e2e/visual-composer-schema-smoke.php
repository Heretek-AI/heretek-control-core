<?php
if (!defined('WP_CLI')) { exit; }
require_once dirname(__DIR__,2).'/pro/includes/abilities/class-visual-composer-integration.php';
foreach(array('row','column','textBlock','singleImage','basicButton') as $tag) {
    $s=EMCP_Tools_Visual_Composer_Integration::schema($tag);
    if(is_wp_error($s)) {
        $e=vchelper('HubElements')->getElements()[$tag]; $file=dirname(rtrim($e['elementRealPath'],'/\\')).'/public/dist/element.bundle.js';
        echo $file.' exists '.(int)is_file($file)."\n";
        $text=file_get_contents($file); preg_match_all('/eval\(("(?:[^"\\\\]|\\\\.)*")\)/s',$text,$matches); echo 'matches '.count($matches[1])."\n";
        foreach($matches[1] as $encoded) { $source=json_decode($encoded,true); if(str_contains($source,'/'.$tag.'/settings.json')) { echo substr($source,0,100)."\n"; echo 'regex '.preg_match("/JSON\\.parse\\('((?:[^'\\\\]|\\\\.)*)'\\)/s",$source,$j).' '.preg_last_error_msg()."\n"; } }
        WP_CLI::error($tag.': '.$s->get_error_message());
    }
    file_put_contents(__DIR__.'/visual-composer-schema-'.$tag.'.json',wp_json_encode($s,JSON_PRETTY_PRINT));
    echo $tag.': '.count($s)." attributes\n";
}
