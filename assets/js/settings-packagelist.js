document.addEventListener('DOMContentLoaded', function () {
    /***** Package list *****/
    if (jQuery('#shippingPackageList').length) {
        function rowEmpty() {
            var result = true;
            jQuery('#shippingPackageList .rate-row').each(function (index) {
                jQuery(jQuery(this).find('.field')).each(function (index) {
                    if (jQuery(this).val().trim() == '') {
                        result = false;
                    }
                });
            });

            return result;
        }

        jQuery(document).on('click', '#shippingPackageList input[type="checkbox"]',
            function () {
                jQuery('#shippingPackageList input[type="checkbox"]').each(function (index) {
                    jQuery(this).prop('checked', false);
                });
                jQuery(this).prop('checked', true);
            }
        )

        var i = jQuery('#shippingPackageList').data('table-count');
        var option_name = jQuery('#shippingPackageList').data('option-name');
        var delete_text = jQuery('#shippingPackageList').data('delete-text');

        if (jQuery('#shippingPackageList .rate-row').length == 0 ) {
            jQuery('#shippingPackageList .rates-header ').hide();
        }

        jQuery(document).on('click', '#shippingPackageList .add-rate',
            function () {
                if (rowEmpty() == true) {
                    html = '<tr class="rate-row rate-row-' + i + '">';
                    html += '<td><input class="field" name="' + option_name + '[' + i + '][name]" type="text" /></td>';
                    html += '<td><input class="field" name="' + option_name + '[' + i + '][depth]" type="text" min="1" step="0.01" /></td>';
                    html += '<td><input class="field" name="' + option_name + '[' + i + '][width]" type="text" min="1" step="0.01" /></td>';
                    html += '<td><input class="field" name="' + option_name + '[' + i + '][height]" type="text" min="1" step="0.01" /></td>';
                    html += '<td><input class="field" name="' + option_name + '[' + i + '][weight]" type="text" min="1" step="0.01" /></td>';
                    html += '<td><input class="field" name="' + option_name + '[' + i + '][default]" type="checkbox" /></td>';
                    html += '<td><span style="cursor: pointer" class="dashicons dashicons-dismiss delete-rate" data-id="' + i + '" ></span></td>';
                    html += '</tr>';
                    jQuery(this).next().append(html);
                    jQuery('#shippingPackageList .rate-row-' + i).find('input:first').focus();

                    if (jQuery('#shippingPackageList input[type="checkbox"]:checked').length == 0) {
                        jQuery('#shippingPackageList').find('input[type=checkbox]').prop('checked', true);
                    }

                    if (jQuery('#shippingPackageList .rate-row').length > 0 ) {
                        jQuery('#shippingPackageList .rates-header').show();
                    }

                    i++;
                }
            }
        );
        jQuery(document).on('click', '#shippingPackageList .delete-rate',
            function () {
                if (confirm(delete_text)) {
                    jQuery('#shippingPackageList .rate-row-' + jQuery(this).data('id')).remove();

                    if (jQuery('#shippingPackageList .rate-row').length == 0 ) {
                        jQuery('#shippingPackageList .rates-header ').hide();
                    }
                    if (jQuery('#shippingPackageList .rate-row').length == 1 ) {
                        if (jQuery('#shippingPackageList input[type="checkbox"]:checked').length == 0) {
                            jQuery('#shippingPackageList').find('input[type=checkbox]').prop('checked', true);
                        }
                    }
                }
            }
        );
    }
});
