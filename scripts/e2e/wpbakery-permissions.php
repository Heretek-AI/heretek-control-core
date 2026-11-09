<?php
/** Runtime permission checks; filters are request-local and no content is written. */
if(home_url()!=='https://elementor-mcp.test') { WP_CLI::error('Test site only.'); }
$adapter=new EMCP_Tools_WPBakery_Integration();
$expect=static function($value,$code) { if(!is_wp_error($value) || $value->get_error_code()!==$code) { WP_CLI::error('Expected '.$code); } };
$deny_html=static function($caps) { $caps['unfiltered_html']=false; return $caps; };
add_filter('user_has_cap',$deny_html);
$expect($adapter->execute('create-page',array('title'=>'Must never be created')),'wpbakery_forbidden');
remove_filter('user_has_cap',$deny_html);
$other=static fn()=>'oxygen'; add_filter('pre_option_emcp_tools_page_builder',$other);
$expect($adapter->execute('get-context',array()),'wpbakery_forbidden'); remove_filter('pre_option_emcp_tools_page_builder',$other);
$user=get_current_user_id(); wp_set_current_user(0);
$expect($adapter->execute('get-context',array()),'wpbakery_forbidden'); wp_set_current_user($user);
$expect($adapter->execute('get-page',array('post_id'=>9999999)),'wpbakery_forbidden');
$page=$adapter->execute('get-page',array('post_id'=>2190));
if(is_wp_error($page)) { WP_CLI::error('Read the MCP landing page before permissions testing.'); }
$deny_element=static fn($value,$shortcode)=>$shortcode==='vc_btn'?false:$value;
add_filter('vc_user_access_check-shortcode_all',$deny_element,10,2);
$expect($adapter->execute('set-page-content',array('post_id'=>2190,'expected_hash'=>$page['content_hash'],'content'=>$page['content'])),'element_forbidden');
remove_filter('vc_user_access_check-shortcode_all',$deny_element,10);
$deny_post=static fn($caps,$cap,$user_id,$params)=>$cap==='edit_post' && ($params[0]??0)===2190?array('do_not_allow'):$caps;
add_filter('map_meta_cap',$deny_post,10,4);
$expect($adapter->execute('get-page',array('post_id'=>2190)),'wpbakery_forbidden');
remove_filter('map_meta_cap',$deny_post,10);
WP_CLI::success('Denied native restricted elements and inaccessible pages.');
WP_CLI::success('Denied unfiltered writes, inactive-builder access, anonymous access and missing pages.');
