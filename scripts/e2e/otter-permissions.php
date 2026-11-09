<?php
// Verify denial paths without modifying the page or persistent capabilities.
if(!defined('WP_CLI')) { exit; }
$id=absint($args[0]??0);
if(!$id || home_url()!=='https://elementor-mcp.test') { WP_CLI::error('Provide a test-site page ID.'); }
require_once dirname(__DIR__,2).'/pro/includes/abilities/class-otter-integration.php';
add_filter('option_emcp_tools_block_packs',static fn($p)=>array_merge((array)$p,array('otter')));
$api=new EMCP_Tools_Otter_Integration(); $before=$api->execute('get-post-blocks',array('post_id'=>$id));
if(is_wp_error($before)) { WP_CLI::error($before->get_error_message()); }
foreach(array('edit_posts'=>'get-post-blocks','edit_post'=>'get-post-blocks','unfiltered_html'=>'rebuild-page-styles','publish_pages'=>'publish-page','manage_options'=>'get-settings','edit_pages'=>'create-page') as $cap=>$tool) {
    $deny=static fn($caps,$requested)=>$requested===$cap?array('do_not_allow'):$caps;
    add_filter('map_meta_cap',$deny,99,2);
    $result=$api->execute($tool,array('post_id'=>$id,'expected_hash'=>$before['content_hash'],'confirm'=>true,'title'=>'Must not be created'));
    remove_filter('map_meta_cap',$deny,99);
    if(!is_wp_error($result)) { WP_CLI::error('Unexpected permission: '.$cap); }
    echo 'PASS denied '.$cap."\n";
}
$user=get_current_user_id(); wp_set_current_user(0); $r=$api->execute('get-context',array()); wp_set_current_user($user);
if(!is_wp_error($r)) { WP_CLI::error('Anonymous access allowed.'); }
if($before['content_hash']!==$api->execute('get-post-blocks',array('post_id'=>$id))['content_hash']) { WP_CLI::error('Denied operations changed content.'); }
WP_CLI::success('Anonymous/capability denials left content unchanged.');
