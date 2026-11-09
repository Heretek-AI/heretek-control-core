<?php
/** Runtime permission checks; filters are request-local and no content is written. */
if(home_url()!=='https://elementor-mcp.test') { WP_CLI::error('Test site only.'); }
$adapter=new EMCP_Tools_Kirki_Integration();
$expect=static function($value,$code) { if(!is_wp_error($value) || $value->get_error_code()!==$code) { WP_CLI::error('Expected '.$code); } };
$deny_html=static function($caps) { $caps['unfiltered_html']=false; return $caps; };
add_filter('user_has_cap',$deny_html);
$expect($adapter->execute('create-page',array('title'=>'Must never be created')),'kirki_forbidden');
remove_filter('user_has_cap',$deny_html);
$other=static fn()=>'oxygen'; add_filter('pre_option_emcp_tools_page_builder',$other);
$expect($adapter->execute('get-context',array()),'kirki_forbidden'); remove_filter('pre_option_emcp_tools_page_builder',$other);
$user=get_current_user_id(); wp_set_current_user(0);
$expect($adapter->execute('get-context',array()),'kirki_forbidden'); wp_set_current_user($user);
$expect($adapter->execute('get-page',array('post_id'=>9999999)),'invalid_page');
WP_CLI::success('Denied unfiltered writes, inactive-builder access, anonymous access and missing pages.');
