<?php
// Read-only native contract inspection. Never creates posts or changes settings.
if (PHP_SAPI !== 'cli') { exit; }
$_SERVER['HTTP_HOST'] = 'msrplugins.test';
$_SERVER['REQUEST_SCHEME'] = 'https';
require dirname(__DIR__, 5) . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'msrplugins.test') { exit('Wrong site'); }
$class = 'ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration';
$result = array('theme' => get_template(), 'version' => defined('ET_BUILDER_VERSION') ? ET_BUILDER_VERSION : null, 'd5' => function_exists('et_builder_d5_enabled') && et_builder_d5_enabled());
if (class_exists($class)) {
    $all = $class::get_all_core_modules_metadata();
    $result['module_count'] = count($all);
    foreach (array('section', 'row', 'column', 'heading', 'text', 'button', 'image', 'post-content', 'group') as $key) {
        $m = $all[$key] ?? array();
        $result['modules'][$key] = array_intersect_key($m, array_flip(array('name','parent','parents','childrenName','category','attributes','allowAllElements')));
    }
}
if (function_exists('et_theme_builder_get_flat_template_settings_options')) {
    $result['conditions'] = et_theme_builder_get_flat_template_settings_options();
}
if (isset($argv[1]) && $argv[1] === 'adapter') {
    EMCP_Tools_Bootstrap::load_mcp_surface();
    $result = array('supported'=>EMCP_Tools_Divi_Integration::supported());
    $schema = EMCP_Tools_Divi_Integration::module_schema('divi/heading');
    $result['heading'] = array('keys'=>array_keys($schema),'attributes'=>array_keys($schema['attributes']??array()),'defaults'=>$schema['defaults']??array());
    $opts=EMCP_Tools_Divi_Settings::read();
    $result['options_count']=count($opts['schema']);
    $result['options_writable']=count(array_filter($opts['schema'],static fn($v)=>$v['writable']));
    $result['theme_builder']=EMCP_Tools_Divi_Theme_Builder::read();
}
if (isset($argv[1]) && $argv[1] === 'options') {
    require get_template_directory() . '/options_divi.php';
    $result = array();
    foreach ($GLOBALS['options'] as $o) {
        if (isset($o['id'])) { $result[] = array_intersect_key($o, array_flip(array('id','type','std','options','name'))); }
    }
}
echo wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
