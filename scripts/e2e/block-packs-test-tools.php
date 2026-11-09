<?php
// Temporarily enable only block-pack writes on the designated local test site.
if(PHP_SAPI!=='cli') { exit; }
$_SERVER['HTTP_HOST']='elementor-mcp.test'; $_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/elementor-mcp/wp-load.php';
if(wp_parse_url(home_url(),PHP_URL_HOST)!=='elementor-mcp.test') { exit(1); }
$option='emcp_tools_disabled_tools'; $snapshot='emcp_block_pack_test_disabled_snapshot';
if(($argv[1]??'')==='restore') {
    $saved=get_option($snapshot,null);
    if(is_array($saved)) { update_option($option,$saved); delete_option($snapshot); }
    echo "Tool settings restored.\n"; exit;
}
if(($argv[1]??'')!=='enable') { exit(1); }
$disabled=(array)get_option($option,array());
add_option($snapshot,$disabled,'',false);
$test_tools=array();
foreach(array('spectra','kadence-blocks','generateblocks') as $pack) {
    foreach(array('add-block','update-block','move-block','remove-block') as $op) { $test_tools[]='emcp-tools/'.$pack.'-'.$op; }
}
update_option($option,array_values(array_diff($disabled,$test_tools)));
echo "Twelve test write tools enabled; original settings saved.\n";
