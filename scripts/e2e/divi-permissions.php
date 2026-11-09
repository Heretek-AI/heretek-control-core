<?php
// Read-only permission probes: no page creation or option updates.
if (PHP_SAPI !== 'cli') { exit; }
$_SERVER['HTTP_HOST']='elementor-mcp.test';
$_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/elementor-mcp/wp-load.php';
if (wp_parse_url(home_url(),PHP_URL_HOST)!=='elementor-mcp.test') { exit(1); }
EMCP_Tools_Bootstrap::load_mcp_surface();
$admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'));
wp_set_current_user((int)$admins[0]);
$api=new EMCP_Tools_Divi_Integration();
$checks=array('admin_access'=>!is_wp_error($api->execute('get-context',array())));
$deny=static function($caps) { $caps['edit_theme_options']=false; return $caps; };
add_filter('user_has_cap',$deny);
$checks['theme_options_capability']=is_wp_error($api->execute('get-theme-options',array()));
$checks['theme_builder_capability']=is_wp_error($api->execute('get-theme-builder',array()));
remove_filter('user_has_cap',$deny);
$off=static fn()=>'';
add_filter('pre_option_emcp_tools_page_builder',$off);
$checks['disabled_integration']=is_wp_error($api->execute('get-context',array()));
remove_filter('pre_option_emcp_tools_page_builder',$off);
wp_set_current_user(0);
$checks['anonymous_denied']=is_wp_error($api->execute('get-context',array()));
echo wp_json_encode($checks,JSON_PRETTY_PRINT);
exit(in_array(false,$checks,true)?1:0);
