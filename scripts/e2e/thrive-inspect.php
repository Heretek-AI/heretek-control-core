<?php
// Read-only installed contract and permission probes; no test content on dev.
if(PHP_SAPI!=='cli') { exit; }
$_SERVER['HTTP_HOST']='msrplugins.test'; $_SERVER['REQUEST_SCHEME']='https';
require dirname(__DIR__,5).'/wp-load.php';
EMCP_Tools_Bootstrap::load_mcp_surface();
$admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID')); wp_set_current_user((int)$admins[0]);
add_filter('pre_option_emcp_tools_page_builder',static fn()=>'thrive');
$api=new EMCP_Tools_Thrive_Integration();
$context=$api->execute('get-context',array());
$elements=$api->execute('list-elements',array());
$schema=$api->execute('get-element-schema',array('type'=>$argv[1]??'text'));
$result=array('context'=>$context,'version'=>defined('TVE_VERSION')?TVE_VERSION:null,'rest'=>class_exists('TCB_Content_REST'),'cap'=>function_exists('tcb_has_external_cap')?tcb_has_external_cap():null,'element_count'=>is_wp_error($elements)?$elements:count($elements['elements']??array()),'schema'=>$schema);
wp_set_current_user(0); $result['anonymous_denied']=is_wp_error($api->execute('get-context',array()));
echo wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
