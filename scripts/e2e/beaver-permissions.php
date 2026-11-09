<?php
/** Request-local permission regression checks; rejected operations must not write. */
if(home_url()!=='https://elementor-mcp.test') { WP_CLI::error('Test site only.'); }
$state=json_decode(file_get_contents(__DIR__.'/.beaver-state.json'),true); $id=(int)$state['page_id'];
$adapter=new EMCP_Tools_Beaver_Integration();
$expect=static function($result,$code) { if(!is_wp_error($result) || $result->get_error_code()!==$code) { WP_CLI::error('Expected '.$code.', got '.(is_wp_error($result)?$result->get_error_code():'success')); } };
$page=$adapter->execute('get-page',array('post_id'=>$id)); if(is_wp_error($page)) { WP_CLI::error($page->get_error_message()); }
$edit=array('post_id'=>$id,'expected_hash'=>$page['content_hash']);
$deny_html=static function($caps) { $caps['unfiltered_html']=false; return $caps; };
add_filter('user_has_cap',$deny_html); $expect($adapter->execute('create-page',array('title'=>'Must not be created')),'beaver_forbidden'); remove_filter('user_has_cap',$deny_html);
$registered=FLBuilderUserAccess::get_registered_settings();
if(FLBuilderUserAccess::get_raw_settings()) { WP_CLI::error('This request-local default-role fixture requires unsaved native role overrides.'); }
$deny=$registered['builder_access']; $deny['default']=false;
FLBuilderUserAccess::register_setting('builder_access',$deny); $expect($adapter->execute('get-context',array()),'beaver_forbidden'); FLBuilderUserAccess::register_setting('builder_access',$registered['builder_access']);
$deny=$registered['unrestricted_editing']; $deny['default']=false;
FLBuilderUserAccess::register_setting('unrestricted_editing',$deny); $expect($adapter->execute('create-page',array('title'=>'Must not be created')),'beaver_forbidden'); FLBuilderUserAccess::register_setting('unrestricted_editing',$registered['unrestricted_editing']);
$deny_module=static fn($modules)=>array_values(array_diff($modules,array('heading')));
add_filter('fl_builder_enabled_modules',$deny_module); $expect($adapter->execute('set-page-layout',$edit+array('nodes'=>$page['nodes'])),'unsupported_module'); remove_filter('fl_builder_enabled_modules',$deny_module);
$deny_publish=static function($caps) { $caps['publish_pages']=false; return $caps; };
add_filter('user_has_cap',$deny_publish); $expect($adapter->execute('publish-page',$edit),'beaver_forbidden'); remove_filter('user_has_cap',$deny_publish);
$deny_post=static fn($caps,$cap,$user,$params)=>$cap==='edit_post' && ($params[0]??0)===$id?array('do_not_allow'):$caps;
add_filter('map_meta_cap',$deny_post,10,4); $expect($adapter->execute('get-page',array('post_id'=>$id)),'beaver_forbidden'); remove_filter('map_meta_cap',$deny_post,10);
$other=static fn()=>'wpbakery'; add_filter('pre_option_emcp_tools_page_builder',$other); $expect($adapter->execute('get-context',array()),'beaver_forbidden'); remove_filter('pre_option_emcp_tools_page_builder',$other);
$user=get_current_user_id(); wp_set_current_user(0); $expect($adapter->execute('get-context',array()),'beaver_forbidden'); wp_set_current_user($user);
$after=$adapter->execute('get-page',array('post_id'=>$id)); if($after['content_hash']!==$page['content_hash']) { WP_CLI::error('Denied checks changed the page.'); }
WP_CLI::success('Native role/module restrictions, WordPress capabilities, anonymous and inactive-builder access denied; page unchanged.');
