<?php
/** Read-only runtime assertions on the designated test draft. */
if (PHP_SAPI!=='cli') { exit; }
$_SERVER['HTTP_HOST']='elementor-mcp.test'; $_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/elementor-mcp/wp-load.php';
EMCP_Tools_Bootstrap::load_mcp_surface();
$admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID')); wp_set_current_user((int)$admins[0]);
$adapter=new EMCP_Tools_Oxygen_Integration(); $checks=array();
$checks['oxygen_available']=EMCP_Tools_Page_Builders::enabled('oxygen');
$checks['breakdance_excluded']=!EMCP_Tools_Breakdance_Integration::supported();
$checks['gutenberg_available']=EMCP_Tools_Page_Builders::enabled('gutenberg');
$checks['draft']=get_post_status(2001)==='draft';
$checks['native_oxygen_data']=metadata_exists('post',2001,'_oxygen_data');
$checks['no_breakdance_data']=!metadata_exists('post',2001,'_breakdance_data');
$checks['prior_revisions']=count(wp_get_post_revisions(2001))>=2;
$checks['foreign_element_rejected']=!$adapter->element_schema('EssentialElements\\Heading');
$off=static fn()=>''; add_filter('pre_option_emcp_tools_page_builder',$off);
$checks['inactive_denied']=is_wp_error($adapter->execute('get-context',array()));
remove_filter('pre_option_emcp_tools_page_builder',$off);
$disabled=static fn()=>array('emcp-tools/oxygen-get-context'); add_filter('pre_option_emcp_tools_disabled_tools',$disabled);
$checks['disabled_denied']=is_wp_error($adapter->execute('get-context',array()));
remove_filter('pre_option_emcp_tools_disabled_tools',$disabled);
$deny=static function($caps,$cap,$user_id,$args) { return $cap==='edit_post'?array('do_not_allow'):$caps; };
add_filter('map_meta_cap',$deny,10,4);
$checks['post_capability_denied']=is_wp_error($adapter->execute('get-page',array('post_id'=>2001)));
remove_filter('map_meta_cap',$deny,10);
wp_set_current_user(0);
$checks['anonymous_denied']=is_wp_error($adapter->execute('get-context',array()));
echo wp_json_encode($checks,JSON_PRETTY_PRINT);
exit(in_array(false,$checks,true)?1:0);
