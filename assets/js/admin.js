document.addEventListener('DOMContentLoaded', function () {
    jQuery('.ship-quik-create_order').on('click', function () {
        var nonce = jQuery('html').data('createordernonce');
        var order_id = jQuery(this).data('key');

        var parent = this;
        jQuery.ajax({
            type: "POST",
            url: SHIP_QUIK_Ajax.ajaxurl,
            data: {
                'action': 'create_order',
                'order_id'    : order_id,
                'nonce'       : nonce
            },
            cache: false,
            success: function(response) {
                if (response === 'ERROR') {
                    alert('Problemas para guardar el pedido, compruebe que no exista ya en Ship-Quik');
                } else {
                    jQuery('.ship-quik-create_order_' + order_id).parent().html(response);
                }
            }
        });
    });
});