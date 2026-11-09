<?php
/** Native runtime smoke. Theme write is restored exactly; no pages are created here. */
if(home_url()!=='https://elementor-mcp.test') { WP_CLI::error('Local test site only.'); }
function emcp_blocksy_assert($value,$label) { if(!$value) { throw new RuntimeException($label); } WP_CLI::log('PASS '.$label); }
$theme=new EMCP_Tools_Blocksy_Theme_Integration();
$content=new EMCP_Tools_Blocksy_Content_Integration();
$mods=(array)get_theme_mods(); $existed=array_key_exists('maxSiteWidth',$mods); $before=$mods['maxSiteWidth']??null;
try {
    $settings=$theme->execute('get-settings',array());
    // Prime native request cache to exercise invalidation after the mutation.
    blocksy_get_theme_mod('maxSiteWidth',1290);
    $result=$theme->execute('update-settings',array('expected_hash'=>$settings['settings_hash'],'values'=>array('maxSiteWidth'=>1188)));
    emcp_blocksy_assert(!is_wp_error($result) && (int)blocksy_get_theme_mod('maxSiteWidth',1290)===1188,'native theme write and cache invalidation');
    $stale=$theme->execute('update-settings',array('expected_hash'=>'stale','values'=>array('maxSiteWidth'=>1200)));
    emcp_blocksy_assert(is_wp_error($stale),'stale settings rejected');
} finally {
    if($existed) { set_theme_mod('maxSiteWidth',$before); } else { remove_theme_mod('maxSiteWidth'); }
    blocksy_manager()->db->wipe_cache(); do_action('blocksy:dynamic-css:refresh-caches');
}
emcp_blocksy_assert(get_theme_mod('maxSiteWidth',null)===($existed?$before:null),'theme setting restored');
$rules=new \Blocksy\ConditionsManager();
emcp_blocksy_assert($rules->condition_matches(array(array('type'=>'include','rule'=>'everywhere','payload'=>array()))),'native condition format');
$id=get_current_user_id();
try {
    wp_set_current_user(0);
    emcp_blocksy_assert(is_wp_error($theme->execute('get-settings',array())),'anonymous theme access denied');
    emcp_blocksy_assert(is_wp_error($content->execute('create-content-block',array('title'=>'Denied','template_type'=>'header'))),'anonymous template creation denied');
} finally { wp_set_current_user($id); }
emcp_blocksy_assert(post_type_exists('ct_content_block'),'native Content Blocks registered');
WP_CLI::success('Blocksy runtime smoke passed.');
