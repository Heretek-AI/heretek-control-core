<?php
// Read-only checks against the installed test-site runtime.
if (PHP_SAPI !== 'cli') { exit; }
$_SERVER['HTTP_HOST']='elementor-mcp.test';
$_SERVER['REQUEST_SCHEME']='https';
require 'F:/laragon/www/elementor-mcp/wp-load.php';
if (wp_parse_url(home_url(),PHP_URL_HOST)!=='elementor-mcp.test') { exit(1); }
EMCP_Tools_Bootstrap::load_mcp_surface();
$admins=get_users(array('role'=>'administrator','number'=>1,'fields'=>'ID'));
wp_set_current_user((int)$admins[0]);
$api=new EMCP_Tools_Thrive_Integration();
$checks=array('admin_access'=>!is_wp_error($api->execute('get-context',array())));
// Regression: setting body parameters must not overwrite the route's post ID.
$req=new WP_REST_Request('POST');
$req->set_url_params(array('id'=>1979));
$req->set_body_params(array('tve_content'=>'example'));
$checks['request_id_survives_body']=$req['id']===1979;
$page=$api->execute('get-page',array('post_id'=>1979));
$checks['draft_saved_native']=!is_wp_error($page) && $page['status']==='draft' && count($page['elements'])>50;
$checks['editor_url']=!is_wp_error($page) && strpos($page['editor_url'],'action=architect')!==false;
$checks['library_read']=!is_wp_error($api->execute('list-library',array()));
$deny=static function($caps) { $caps['edit_pages']=false; return $caps; };
add_filter('user_has_cap',$deny);
$checks['edit_pages_required']=is_wp_error($api->execute('get-context',array()));
remove_filter('user_has_cap',$deny);
$deny_post=static function($caps,$cap,$user_id,$args) { return $cap==='edit_post' ? array('do_not_allow') : $caps; };
add_filter('map_meta_cap',$deny_post,10,4);
$checks['per_post_permission']=is_wp_error($api->execute('get-page',array('post_id'=>1979)));
remove_filter('map_meta_cap',$deny_post,10);
$off=static fn()=>'';
add_filter('pre_option_emcp_tools_page_builder',$off);
$checks['disabled_integration']=is_wp_error($api->execute('get-context',array()));
$checks['gutenberg_still_enabled']=EMCP_Tools_Page_Builders::enabled('gutenberg');
remove_filter('pre_option_emcp_tools_page_builder',$off);
wp_set_current_user(0);
$checks['anonymous_denied']=is_wp_error($api->execute('get-context',array()));
echo wp_json_encode($checks,JSON_PRETTY_PRINT);
exit(in_array(false,$checks,true)?1:0);
