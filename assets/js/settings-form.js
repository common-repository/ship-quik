
document.addEventListener('DOMContentLoaded', function () {
    jQuery(document).on('submit', 'form',
        function () {

            if (jQuery(this).data('activate') == '1') return true;

            var result = true;

            // Evaluating no rows empty
            jQuery('#shippingTableRates .rate-row').each(function (index) {
                jQuery(jQuery(this).find('.field')).each(function (index) {
                    if (jQuery(this).val().trim() == '' || jQuery(this).val().trim() == '0') {
                        jQuery(this).focus();

                        result = false;
                    }
                });
            });

            jQuery('#shippingPackageList .rate-row').each(function (index) {
                jQuery(jQuery(this).find('.field')).each(function (index) {
                    if (jQuery(this).val().trim() == '' || jQuery(this).val().trim() == '0') {
                        jQuery(this).focus();

                        result = false;
                    }
                });
            });

            if (result == false) return result;

            // Evaluating a rows, first field always hight than second
            jQuery('#shippingTableRates .rate-row').each(function (index) {
                var first = parseFloat(jQuery(this).find('.field').eq(0).val());
                var last = parseFloat(jQuery(this).find('.field').eq(1).val());

                if (first >= last) {
                    alert('Error en tabla de rangos: Rango erroneo en fila ' + index);
                    jQuery(this).find('.field').eq(0).focus();

                    result =  false;
                }

                return;
            });

            if (result == false) return result;

            // Evaluating row agaist row, , first field always hight than last field previous row
            var sw = true;
            jQuery('#shippingTableRates .rate-row').each(function (index) {
                if (sw == true) {
                    var first = parseFloat(jQuery(this).find('.field').eq(1).val());
                    sw = false;
                } else {
                    var last = parseFloat(jQuery(this).find('.field').eq(0).val());

                    if (last <= first) {
                        alert('Error en tabla de rangos: Rango erroneo en fila ' + index);
                        jQuery(this).find('.field').eq(0).focus();

                        result =  false;
                    }
                    first = parseFloat(jQuery(this).find('.field').eq(1).val());
                }

                return;
            });

            var sw2 = false;
            jQuery(this).find('._ship_quik_suppliers').each(function (index) {
                if (jQuery(this).prop('checked')) {
                    sw2 = true;
                }
            });
            if (!sw2) {
                alert('Debe seleccionar un proveedor de servicios');
                result = false;
            }

            if (jQuery(this).find('#shippingPackageList input').length == 0) {
                alert('Debe especificar un paquete por defecto');
                result = false;
            };

            return result;
        }
    );
});
