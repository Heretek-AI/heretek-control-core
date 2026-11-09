<?php
// Read-only inspection of the installed vendor APIs before adapter implementation.
if (!defined('WP_CLI')) { exit; }
$elements = vchelper('HubElements')->getElements();
$out = array('version'=>VCV_VERSION, 'prefix'=>VCV_PREFIX, 'page_access'=>vchelper('AccessUserCapabilities')->isEditorEnabled('page'), 'elements'=>array_keys($elements), 'schemas'=>array(), 'types'=>array_values(array_filter(get_post_types(),static fn($s)=>str_starts_with($s,'vcv_'))));
foreach (array('row','column','textBlock','singleImage','basicButton') as $tag) { $out['schemas'][$tag]=$elements[$tag]??null; }
file_put_contents(__DIR__.'/visual-composer-inspect.json',wp_json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo wp_json_encode(array_diff_key($out,array('schemas'=>true)),JSON_PRETTY_PRINT);
