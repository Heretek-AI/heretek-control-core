<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
$_SERVER['HTTP_HOST'] = 'msrplugins.test';
$_SERVER['REQUEST_SCHEME'] = 'https';
require dirname(__DIR__, 5) . '/wp-load.php';
if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'msrplugins.test') {
    exit('Wrong site');
}
EMCP_Tools_Bootstrap::load_mcp_surface();
$admins = get_users(array('role' => 'administrator', 'number' => 1));
wp_set_current_user($admins[0]->ID);
$selection = get_option('emcp_tools_page_builder', null);
add_filter('pre_option_emcp_tools_disabled_tools', static function () {
    return array();
});
$id = 0;
$fixtures = array();
$slider_id = 0;
function avada_check($ok, $label)
{
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "PASS {$label}\n";
}
function avada_run($slug, $args = array())
{
    $ability = wp_get_ability('emcp-tools/avada-' . $slug);
    if (!$ability) {
        throw new RuntimeException('Missing ' . $slug);
    }
    $r = $ability->execute($args);
    if (is_wp_error($r)) {
        throw new RuntimeException($slug . ': ' . $r->get_error_message());
    }
    return $r;
}
try {
    update_option('emcp_tools_page_builder', 'avada');
    avada_check(avada_run('get-context')['version'] === '3.16.1', 'native version');
    avada_check(count(avada_run('list-elements')['elements']) > 50, 'native catalog');
    $schema = avada_run('get-element-schema', array('type' => 'fusion_title'));
    avada_check(!empty($schema['fields']), 'native schema');
    avada_check(isset($schema['fields']['font_size']), 'composite typography attributes');
    avada_check(avada_run('get-context')['core_version'] === '5.16.1', 'Avada Core version');
    foreach (array('fusion_faq', 'fusion_portfolio', 'fusion_fusionslider') as $core_type) {
        $core_schema = avada_run('get-element-schema', array('type' => $core_type));
        avada_check($core_schema['authoring_supported'] && $core_schema['provider'] === 'avada-core', 'Core schema ' . $core_type);
    }
    foreach (array('portfolio', 'faq', 'slider') as $kind) {
        avada_check(isset(avada_run('list-core-content', array('kind' => $kind))['items']), 'Core discovery ' . $kind);
    }
    foreach (array('faq' => 'avada_faq', 'portfolio' => 'avada_portfolio') as $kind => $post_type) {
        $fixture = wp_insert_post(array(
            'post_type' => $post_type,
            'post_status' => 'publish',
            'post_title' => 'EMCP Core ' . $kind . ' fixture',
            'post_content' => 'Temporary integration fixture',
        ), true);
        if (is_wp_error($fixture)) {
            throw new RuntimeException($fixture->get_error_message());
        }
        $fixtures[] = $fixture;
        avada_check(in_array($fixture, array_column(avada_run('list-core-content', array('kind' => $kind))['items'], 'post_id'), true), 'Core item discovery ' . $kind);
        $shortcode = $kind === 'faq' ? '[fusion_faq number_posts="10"][/fusion_faq]' : '[fusion_portfolio layout="grid" number_posts="10" portfolio_title_display="all"][/fusion_portfolio]';
        avada_check(str_contains(do_shortcode($shortcode), 'EMCP Core ' . $kind . ' fixture'), 'Core native content render ' . $kind);
    }
    $slider = wp_insert_term('EMCP integration slider', 'slide-page', array('slug' => 'emcp-integration-' . wp_generate_password(8, false)));
    if (is_wp_error($slider)) {
        throw new RuntimeException($slider->get_error_message());
    }
    $slider_id = $slider['term_id'];
    $slider_term = get_term($slider_id, 'slide-page');
    avada_check(in_array($slider_term->slug, array_column(avada_run('list-core-content', array('kind' => 'slider'))['items'], 'slug'), true), 'Core slider slug discovery');
    $s = avada_run('create-page', array('title' => 'EMCP Avada Integration Test'));
    $id = $s['post_id'];
    $tree = EMCP_Tools_Avada_Tree::parse('[fusion_builder_container hundred_percent="yes"][fusion_builder_row][fusion_builder_column type="1_1" type="1_1" first="true" last="true" border_position="all"][fusion_title size="1" font_size="48px" content_align="left" content_align_small="center"]Avada meets EMCP[/fusion_title][fusion_text]<p>Native elements. Thoughtful design.</p>[/fusion_text][/fusion_builder_column][/fusion_builder_row][/fusion_builder_container]');
    avada_check(!is_wp_error($tree), 'shortcode parse');
    $s = avada_run('set-page-tree', array(
        'post_id' => $id,
        'expected_hash' => $s['content_hash'],
        'tree' => $tree,
    ));
    avada_check(get_post_status($id) === 'draft' && !empty($s['revision_id']), 'draft and prior revision');
    avada_check(str_contains(do_shortcode($s['content']), 'Avada meets EMCP'), 'native render');
    $stale = $s['content_hash'];
    $s = avada_run('update-element', array(
        'post_id' => $id,
        'expected_hash' => $stale,
        'element_id' => 5,
        'properties' => array('content' => 'Built with Avada. Connected with EMCP.'),
    ));
    avada_check(str_contains($s['content'], 'Built with Avada.'), 'element update');
    $denied = wp_get_ability('emcp-tools/avada-remove-element')->execute(array(
        'post_id' => $id,
        'expected_hash' => $stale,
        'element_id' => 6,
    ));
    avada_check(is_wp_error($denied) && $denied->get_error_code() === 'stale_page', 'stale edit rejected');
    avada_check(!wp_get_ability('emcp-tools/breakdance-get-context') && (bool) wp_get_ability('emcp-tools/list-blocks'), 'exclusive builder with Gutenberg');
    $s = avada_run('add-element', array(
        'post_id' => $id,
        'expected_hash' => $s['content_hash'],
        'parent_id' => 4,
        'type' => 'fusion_button',
        'properties' => array('attributes' => array('link' => '#demo'), 'content' => 'Explore'),
    ));
    avada_check(str_contains($s['content'], '[fusion_button'), 'add native button');
    $s = avada_run('move-element', array(
        'post_id' => $id,
        'expected_hash' => $s['content_hash'],
        'element_id' => 7,
        'parent_id' => 4,
        'position' => 0,
    ));
    avada_check($s['tree']['root']['children'][0]['children'][0]['children'][0]['children'][0]['data']['type'] === 'fusion_button', 'move and regenerate IDs');
    $s = avada_run('remove-element', array(
        'post_id' => $id,
        'expected_hash' => $s['content_hash'],
        'element_id' => 5,
    ));
    avada_check(!str_contains($s['content'], '[fusion_button'), 'remove subtree');
    foreach (array('fusion_faq', 'fusion_portfolio', 'fusion_fusionslider') as $core_type) {
        $s = avada_run('add-element', array(
            'post_id' => $id,
            'expected_hash' => $s['content_hash'],
            'parent_id' => 4,
            'type' => $core_type,
            'properties' => array('attributes' => $core_type === 'fusion_fusionslider' ? array('name' => $slider_term->slug) : array()),
        ));
        avada_check(str_contains($s['content'], '[' . $core_type), 'Core element save ' . $core_type);
        $s = avada_run('remove-element', array(
            'post_id' => $id,
            'expected_hash' => $s['content_hash'],
            'element_id' => 7,
        ));
    }
    foreach (array(array('attributes' => array('invented' => 'x')), array('attributes' => array('main_typography' => '48px')), array('content' => '<script>alert(1)</script>'), array('content' => '[unknown]')) as $props) {
        $r = wp_get_ability('emcp-tools/avada-update-element')->execute(array(
            'post_id' => $id,
            'expected_hash' => $s['content_hash'],
            'element_id' => 5,
            'properties' => $props,
        ));
        avada_check(is_wp_error($r), 'invalid content rejected');
        avada_check(avada_run('get-page', array('post_id' => $id))['content_hash'] === $s['content_hash'], 'rejected edit preserves content');
    }
    add_option('emcp_avada_lock_' . $id, time(), '', false);
    try {
        $locked = wp_get_ability('emcp-tools/avada-remove-element')->execute(array(
            'post_id' => $id,
            'expected_hash' => $s['content_hash'],
            'element_id' => 6,
        ));
        avada_check(is_wp_error($locked) && $locked->get_error_code() === 'post_locked', 'concurrent save lock enforced');
    } finally {
        delete_option('emcp_avada_lock_' . $id);
    }
    $throw_on_save = static function ($post_id) use ($id) {
        if ($post_id === $id) {
            throw new RuntimeException('Simulated save hook failure');
        }
    };
    add_action('save_post', $throw_on_save, 999);
    try {
        $failed = wp_get_ability('emcp-tools/avada-update-element')->execute(array(
            'post_id' => $id,
            'expected_hash' => $s['content_hash'],
            'element_id' => 5,
            'properties' => array('content' => 'Recovery test'),
        ));
        avada_check(is_wp_error($failed) && $failed->get_error_code() === 'save_failed' && !empty($failed->get_error_data()['revision_id']), 'save hook failure retains recovery revision');
        avada_check(!get_option('emcp_avada_lock_' . $id), 'failed save releases lock');
    } finally {
        remove_action('save_post', $throw_on_save, 999);
    }
    $s = avada_run('get-page', array('post_id' => $id));
    $s = avada_run('update-element', array(
        'post_id' => $id,
        'expected_hash' => $s['content_hash'],
        'element_id' => 5,
        'properties' => array('content' => 'Built with Avada. Connected with EMCP.'),
    ));
    $deny_tools = static function () {
        return array('emcp-tools/avada-update-element');
    };
    add_filter('pre_option_emcp_tools_disabled_tools', $deny_tools, 20);
    $r = (new EMCP_Tools_Avada_Integration())->execute('update-element', array('post_id' => $id));
    avada_check(is_wp_error($r) && $r->get_error_code() === 'tool_disabled', 'disabled tool enforced');
    remove_filter('pre_option_emcp_tools_disabled_tools', $deny_tools, 20);
    $deny_builder = static function ($cap, $context, $sub) {
        return $sub === 'backed_builder_edit' ? 'do_not_allow' : $cap;
    };
    add_filter('awb_role_manager_access_capability', $deny_builder, 99, 3);
    avada_check(is_wp_error((new EMCP_Tools_Avada_Integration())->execute('get-page', array('post_id' => $id))), 'native role manager enforced');
    remove_filter('awb_role_manager_access_capability', $deny_builder, 99);
    wp_set_current_user(0);
    avada_check(is_wp_error((new EMCP_Tools_Avada_Integration())->execute('get-page', array('post_id' => $id))), 'anonymous denied');
    wp_set_current_user($admins[0]->ID);
    update_option('emcp_tools_page_builder', '');
    avada_check(is_wp_error((new EMCP_Tools_Avada_Integration())->execute('get-page', array('post_id' => $id))), 'retained callback selection enforced');
    update_option('emcp_tools_page_builder', 'avada');
    echo json_encode(array(
        'post_id' => $id,
        'preview' => $s['preview_url'],
        'editor' => $s['editor_url'],
    )) . "\n";
    if (in_array('--keep', $argv, true)) {
        $id = 0;
    }
} finally {
    foreach ($fixtures as $fixture) {
        wp_delete_post($fixture, true);
    }
    if ($slider_id) {
        wp_delete_term($slider_id, 'slide-page');
    }
    if ($id) {
        wp_delete_post($id, true);
    }
    if (null === $selection) {
        delete_option('emcp_tools_page_builder');
    } else {
        update_option('emcp_tools_page_builder', $selection);
    }
}
