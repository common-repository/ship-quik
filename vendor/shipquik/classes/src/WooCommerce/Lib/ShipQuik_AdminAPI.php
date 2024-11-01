<?php
/**
 * Post type Admin API file.
 *
 * @package WordPress Plugin Template/Includes
 */

namespace ShipQuik\WooCommerce\Lib;

if (!defined('ABSPATH') ) {
    exit;
}

use \ShipQuik\Shared\ShipQuik_Configuration;

/**
 * Admin API class.
 */
class ShipQuik_AdminAPI
{
    protected $parent;

    protected $config;

    /**
     * Constructor function
     */
    public function __construct($parent)
    {
        $this->parent           = $parent;
        $this->config           = $parent->config;

        add_action('save_post', array( $this, 'save_meta_boxes' ), 10, 1);
    }

    /**
     * Generate HTML for displaying fields.
     *
     * @param  array   $data Data array.
     * @param  object  $post Post object.
     * @param  boolean $echo Whether to echo the field HTML or return it.
     * @return string
     */
    public function display_field( $data = array(), $post = null, $echo = true )
    {

        $required = "";
        $disabled = "";

        // Get field required.
        if (isset($data['field']['required']) ) {
            $required = $data['field']['required'] ? 'required' : '';
        }

        // Get field disabled.
        if (isset($data['field']['disabled']) ) {
            $disabled = $data['field']['disabled'] ? 'disabled' : '';
        }

        // Get field info.
        if (isset($data['field']) ) {
            $field = $data['field'];
        } else {
            $field = $data;
        }

        // Check for prefix on option name.
        $option_name = '';
        if (isset($data['prefix']) ) {
            $option_name = $data['prefix'];
        }

        // Get saved data.
        $data = '';
        if ($post) {

            // Get saved field data.
            $option_name .= $field['id'];
            $option       = get_post_meta($post->ID, $field['id'], true);

            // Get data to display in field.
            if (isset($option) ) {
                $data = $option;
            }
        } else {

            // Get saved option.
            $option_name .= $field['id'];
            $option       = ShipQuik_Configuration::get_option($option_name);

            // Get data to display in field.
            if (isset($option) ) {
                $data = $option;
            }
        }

        // Show default data if no option saved and default is supplied.
        if (false === $data && isset($field['default']) ) {
            $data = $field['default'];
        } elseif (false === $data ) {
            $data = '';
        } elseif ('' === $field['type'] ) {
            $data = $field['default'];
        }

        $html = '';

        switch ($field['type']) {
            case 'text':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '" ' . $required . ' />' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'url':
            case 'email':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="email" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '" ' . $required . ' />' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'password':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="password" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '" ' . $required . ' />' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;
            case 'number':
                $min = '';
                if (isset($field['min']) ) {
                    $min = ' min="' . esc_attr($field['min']) . '"';
                }

                $max = '';
                if (isset($field['max']) ) {
                    $max = ' max="' . esc_attr($field['max']) . '"';
                }
                $step = '';
                if (isset($field['step']) ) {
                    $step = ' step="' . esc_attr($field['step']) . '"';
                }

                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . esc_attr($data) . '"' . $min . '' . $max . '' . $step . ' ' . $required . ' />' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="" />' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '">' . $data . '</textarea><br/>' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'checkbox':
                $checked = '';
                if ($data && 'on' === $data ) {
                    $checked = 'checked="checked"';
                }
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . esc_attr($field['type']) . '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'checkbox_multi':
                if ($field['id'] == 'suppliers') {
                    $tmp = [];
                    if ($data) {
                        foreach ($data as $options) {
                            $tmp[$options['name']] = $options['name'];
                        }  
                    }
                    $data = $tmp;
                }

                foreach ( $field['options'] as $k => $v ) {
                    $checked = false;
                    if (in_array($k, (array) $data, true) ) {
                        $checked = true;
                    }
                    $html .= '<p><label for="' . esc_attr($field['id'] . '_' . $k) . '" class="checkbox_multi"><input class="' . esc_attr($option_name) . '" type="checkbox" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '[]" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label></p> ';
                }

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'radio':
                foreach ( $field['options'] as $k => $v ) {
                    $checked = false;
                    if ($k === $data ) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="radio" ' . checked($checked, true, false) . ' name="' . esc_attr($option_name) . '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label> ';
                }

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '" ' . $disabled .'>';
                foreach ( $field['options'] as $k => $v ) {
                    $selected = false;
                    if ($k === $data ) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) . '" multiple="multiple">';
                foreach ( $field['options'] as $k => $v ) {
                    $selected = false;
                    if (in_array($k, (array) $data, true) ) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, false) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'image':
                $image_thumb = '';
                if ($data ) {
                    $image_thumb = wp_get_attachment_thumb_url($data);
                }
                $html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
                $html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __('Upload an image', 'ship-quik') . '" data-uploader_button_text="' . __('Use image', 'ship-quik') . '" class="image_upload_button button" value="' . __('Upload new image', 'ship-quik') . '" />' . "\n";
                $html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __('Remove image', 'ship-quik') . '" />' . "\n";
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;
            case 'color':
                $html .= '<div class="color-picker" style="position:relative;">';
                $html .= '<input type="text" name="' . esc_attr_e( $option_name ) . '" class="color" value="' . esc_attr_e($data) . '" />';
                $html .= '<div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;" class="colorpicker"></div>';
                $html .= '</div>';
                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;

            case 'editor':
                wp_editor(
                    $data,
                    $option_name,
                    array('textarea_name' => $option_name)
                );

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                break;
            case 'package_list':
                $html = '<div id="shippingPackageList" data-option-name="' . esc_attr($option_name). '" data-table-count="' . (is_array($data) && !empty($data) ? count($data) : 0) . '" data-delete-text="' . __('¿Estás seguro que quieres borrar este paquete?.', 'ship-quik') . '">' . "\n";
                $html .= '<div class="button button-primary add-rate mt-1">' . __('Añadir Paquete', 'ship-quik') . '</div>' . "\n";
                $html .= '<table class="table table-rates">' . "\n";
        
                $html .= '<tr class="rates-header">';
                $html .= '<td><label><b>' . __('Nombre', 'ship-quik') . '</b></label></th>';
                $html .= '<td><label><b>' . __('Largo (cm)', 'ship-quik') . '</b></label></th>';
                $html .= '<td><label><b>' . __('Ancho (cm)', 'ship-quik') . '</b></label></th>';
                $html .= '<td><label><b>' . __('Alto (cm)', 'ship-quik') . '</b></label></th>';
                $html .= '<td><label><b>' . __('Peso (kgr)', 'ship-quik') . '</b></label></th>';
                $html .= '<td colspan="2"><label><b>' . __('Paquete por defecto', 'ship-quik') . '</b></label></th>';
                $html .= '</tr>';

                if (is_array($data)) {
                    $i = 0;
                    foreach ($data as $element) {
                        $isChecked = false;
        
                        if (isset($element['default'])) {
                            if ($element['default'] == 'on') {
                                $isChecked = true;
                            }
                        }
        
                        $html .= '<tr class="rate-row rate-row-' . $i . '">';
                        $html .= '<td><input class="field" name="' . esc_attr($option_name) . '[' . $i . '][name]" type="text" value="' . esc_attr($element['name']) . '" /></td>';
                        $html .= '<td><input class="field" name="' . esc_attr($option_name) . '[' . $i . '][depth]" type="text" min="1" step="0.01" value="' . esc_attr($element['depth']) . '" /></td>';
                        $html .= '<td><input class="field" name="' . esc_attr($option_name) . '[' . $i . '][width]" type="text" min="1" step="0.01" value="' . esc_attr($element['width']) . '" /></td>';
                        $html .= '<td><input class="field" name="' . esc_attr($option_name) . '[' . $i . '][height]" type="text" min="1" step="0.01" value="' . esc_attr($element['height']) . '" /></td>';
                        $html .= '<td><input class="field" name="' . esc_attr($option_name) . '[' . $i . '][weight]" type="text" min="1" step="0.01" value="' . esc_attr($element['weight']) . '" /></td>';
                        $html .= '<td><input class="field" name="' . esc_attr($option_name) . '[' . $i . '][default]" type="checkbox" ' . ($isChecked ? 'checked=checked' : '') . '" /></td>';
                        $html .= '<td><span style="cursor: pointer" class="dashicons dashicons-dismiss delete-rate" data-id="' . $i . '" ></span></td>';
                        $html .= '</tr>';

                        ++$i;
                    }
                }
                $html .= '</table>' . "\n";

                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                $html .= '</div>' . "\n";

                break;
            default:
                if (! $post ) {
                    $html .= '<label for="' . esc_attr($field['id']) . '">' . "\n";
                }

                $html .= '<span>' . $data . '</span>' . "\n";
                $html .= '<span class="description">' . $field['description'] . '</span>' . "\n";

                if (! $post ) {
                    $html .= '</label>' . "\n";
                }

                break;
        }

        if (! $echo ) {
            return $html;
        }

     echo $html;

    }

    /**
     * Validate form field
     *
     * @param  string $data Submitted value.
     * @param  string $type Type of field to validate.
     * @return string       Validated value
     */
    public function validate_field( $data = '', $type = 'text' )
    {
        switch ( $type ) {
            case 'text':
                $data = esc_attr($data);
                break;
            case 'url':
                $data = esc_url($data);
                break;
            case 'email':
                $data = is_email($data);
                break;
        }

        return $data;
    }

    /**
     * Validate form field
     *
     * @param  string $data Submitted value.
     * @param  string $type Type of field to validate.
     * @return string Validated value
     */
    public function validate_editable_table_field($data = '')
    {
        $tmp = [];

        if (is_array($data)) {

            foreach($data as $key => $element) {
                if ($element['from'] && $element['to'] && $element['value']) {
                    $tmp[] = $element;
                }
            }
        }

        return $tmp;
    }

    public function validate_package_list_field($data = '')
    {
        $tmp = [];

        if (is_array($data)) {

            foreach($data as $key => $element) {
                if ($element['name'] && $element['depth'] && $element['width'] && $element['height'] && $element['weight']) {
                    $tmp[] = $element;
                }
            }
        }

        return $tmp;
    }

    /**
     * Add meta box to the dashboard.
     *
     * @param  string $id            Unique ID for metabox.
     * @param  string $title         Display title of metabox.
     * @param  array  $post_types    Post types to which this metabox applies.
     * @param  string $context       Context in which to display this metabox ('advanced' or 'side').
     * @param  string $priority      Priority of this metabox ('default', 'low' or 'high').
     * @param  array  $callback_args Any axtra arguments that will be passed to the display function for this metabox.
     * @return void
     */
    public function add_meta_box( $id = '', $title = '', $post_types = array(), $context = 'advanced', $priority = 'default', $callback_args = null )
    {

        // Get post type(s).
        if (! is_array($post_types) ) {
            $post_types = array( $post_types );
        }

        // Generate each metabox.
        foreach ( $post_types as $post_type ) {
            add_meta_box($id, $title, array( $this, 'meta_box_content' ), $post_type, $context, $priority, $callback_args);
        }
    }

    /**
     * Display metabox content
     *
     * @param  object $post Post object.
     * @param  array  $args Arguments unique to this metabox.
     * @return void
     */
    public function meta_box_content( $post, $args )
    {

        $fields = apply_filters($post->post_type . '_custom_fields', array(), $post->post_type);

        if (! is_array($fields) || 0 === count($fields) ) {
            return;
        }

        echo '<div class="custom-field-panel">' . "\n";

        foreach ( $fields as $field ) {

            if (! isset($field['metabox']) ) {
                continue;
            }

            if (! is_array($field['metabox']) ) {
                $field['metabox'] = array( $field['metabox'] );
            }

            if (in_array($args['id'], $field['metabox'], true) ) {
                $this->display_meta_box_field($field, $post);
            }
        }

        echo '</div>' . "\n";

    }

    /**
     * Dispay field in metabox
     *
     * @param  array  $field Field data.
     * @param  object $post  Post object.
     * @return void
     */
    public function display_meta_box_field( $field = array(), $post = null )
    {

        if (! is_array($field) || 0 === count($field) ) {
            return;
        }

        $field = '<p class="form-field"><label for="' . $field['id'] . '">' . $field['label'] . '</label>' . $this->display_field($field, $post, false) . '</p>' . "\n";

     echo $field;
    }

    /**
     * Save metabox fields.
     *
     * @param  integer $post_id Post ID.
     * @return void
     */
    public function save_meta_boxes( $post_id = 0 )
    {

        if (! $post_id ) {
            return;
        }

        $post_type = get_post_type($post_id);

        $fields = apply_filters($post_type . '_custom_fields', array(), $post_type);

        if (! is_array($fields) || 0 === count($fields) ) {
            return;
        }

        foreach ($fields as $field) {
            if (isset($_REQUEST[ $field['id']])) { 
                $id = sanitize_title($_REQUEST[$field['id']]);
                $type = sanitize_title($_REQUEST[$field['type']]);

                update_post_meta($post_id, $id, $this->validate_field($id, $type)); 
            } else {
                update_post_meta($post_id, $id, '');
            }
        }
    }
}
