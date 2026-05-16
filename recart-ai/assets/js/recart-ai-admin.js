/**
 * ReCart AI - Admin Panel Scripts
 *
 * @package ReCart_AI
 * @version 1.0.0
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // Initialize color pickers
        if ($.fn.wpColorPicker) {
            $('.recart-ai-color-picker').wpColorPicker();
        }

        // Confirm dangerous actions
        $('.button-link-delete').on('click', function (e) {
            if (!confirm($(this).data('confirm') || 'Are you sure?')) {
                e.preventDefault();
                return false;
            }
        });

        // Toggle dependent fields
        $('input[name="recart_ai_free_delivery_enabled"]').on('change', function () {
            var $threshold = $('input[name="recart_ai_free_delivery_threshold"]').closest('tr');
            if ($(this).is(':checked')) {
                $threshold.show();
            } else {
                $threshold.hide();
            }
        }).trigger('change');

        $('input[name="recart_ai_webhook_enabled"]').on('change', function () {
            var $fields = $('input[name="recart_ai_webhook_url"], input[name="recart_ai_webhook_secret"], input[name="recart_ai_abandonment_timeout"]');
            $fields.closest('tr').toggle($(this).is(':checked'));
        }).trigger('change');

        $('input[name="recart_ai_formspree_enabled"]').on('change', function () {
            var $field = $('input[name="recart_ai_formspree_endpoint"]');
            $field.closest('tr').toggle($(this).is(':checked'));
        }).trigger('change');
    });

})(jQuery);
