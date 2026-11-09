<?php
// Read-only native contract probe. No options or page content are changed.
$blocks = array();
foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block ) {
    if ( str_starts_with($name, 'themeisle-blocks/') || str_starts_with($name, 'atomic-wind/') ) {
        $blocks[$name] = array('title'=>$block->title,'dynamic'=>$block->is_dynamic(),'attributes'=>$block->get_attributes(),'parent'=>$block->parent);
    }
}
$patterns = array();
foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $p ) {
    if (in_array('otter-blocks', $p['categories']??array(), true)) { $patterns[]=array('name'=>$p['name'],'title'=>$p['title'],'categories'=>$p['categories'],'length'=>strlen($p['content']??'')); }
}
$settings=array();
foreach(get_registered_settings() as $key=>$setting) {
    if(str_starts_with($key,'themeisle_blocks_settings_') && ($setting['type']??'')==='boolean') { $settings[$key]=array('description'=>$setting['description']??'','default'=>$setting['default']??null); }
}
echo wp_json_encode(array('free'=>defined('OTTER_BLOCKS_VERSION')?OTTER_BLOCKS_VERSION:null,'pro'=>defined('OTTER_PRO_VERSION')?OTTER_PRO_VERSION:null,'blocks'=>$blocks,'patterns'=>$patterns,'settings'=>$settings),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
