<?php
// Read-only capability and catalog verification against the real installed builder.
if (!defined('WP_CLI')) { exit; }
require_once dirname(__DIR__,2).'/pro/includes/abilities/class-visual-composer-integration.php';
$id=absint($args[0]??0); if(!$id || !str_contains(home_url(),'elementor-mcp.test')) { WP_CLI::error('Specify a test-site page.'); }
add_filter('option_emcp_tools_page_builder',static fn()=>'visual-composer');
$api=new EMCP_Tools_Visual_Composer_Integration();
$initial=$api->execute('get-page',array('post_id'=>$id)); if(is_wp_error($initial)) { WP_CLI::error($initial->get_error_message()); }
foreach(array('edit_posts','edit_post','unfiltered_html','publish_pages') as $cap) {
    $deny=static function($caps,$requested) use($cap) { return $requested===$cap?array('do_not_allow'):$caps; };
    add_filter('map_meta_cap',$deny,99,2);
    $slug=$cap==='publish_pages'?'publish-page':'get-page';
    $r=$api->execute($slug,array('post_id'=>$id,'expected_hash'=>$initial['content_hash'],'confirm'=>true));
    remove_filter('map_meta_cap',$deny,99);
    if(!is_wp_error($r)) { WP_CLI::error('Permission unexpectedly allowed: '.$cap); }
    echo 'PASS denied '.$cap."\n";
}
$user=get_current_user_id(); wp_set_current_user(0);
$r=$api->execute('get-page',array('post_id'=>$id)); wp_set_current_user($user);
if(!is_wp_error($r)) { WP_CLI::error('Anonymous access allowed.'); }
$after=$api->execute('get-page',array('post_id'=>$id));
if($initial['content_hash']!==$after['content_hash']) { WP_CLI::error('Denied operations changed the page.'); }
echo "PASS anonymous denial and unchanged page\n";
