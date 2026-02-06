/**
 * WPMind Admin modules toggle handlers.
 *
 * @package WPMind
 * @since 3.3.0
 */

(function ($) {
    'use strict';

    var Admin = window.WPMindAdmin || (window.WPMindAdmin = {});
    var Toast = Admin.Toast || null;
    var notifyError = Toast ? Toast.error.bind(Toast) : function (message) {
        alert(message);
    };

    function initModuleSwitches() {
        $('.wpmind-module-switch').on('change', function () {
            var $switch = $(this);
            var moduleId = $switch.data('module-id');
            var enable = $switch.is(':checked');
            var $card = $switch.closest('.wpmind-module-card');

            $switch.prop('disabled', true);

            if (typeof wpmindData === 'undefined') {
                notifyError('配置错误');
                $switch.prop('checked', !enable).prop('disabled', false);
                return;
            }

            $.ajax({
                url: wpmindData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpmind_toggle_module',
                    nonce: wpmindData.nonce,
                    module_id: moduleId,
                    // Use string '1'/'0' instead of boolean to ensure reliable transmission.
                    // jQuery may serialize boolean false inconsistently.
                    enable: enable ? '1' : '0'
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.reload) {
                            location.reload();
                        } else {
                            $card.toggleClass('is-enabled', enable).toggleClass('is-disabled', !enable);
                        }
                    } else {
                        notifyError(response.data.message || '操作失败');
                        $switch.prop('checked', !enable);
                    }
                },
                error: function () {
                    notifyError('网络错误');
                    $switch.prop('checked', !enable);
                },
                complete: function () {
                    $switch.prop('disabled', false);
                }
            });
        });
    }

    /**
     * Initialize on document ready
     */
    $(function () {
        if (!$('.wpmind-module-switch').length) return;

        var safeInit = Admin.safeInit || function (label, fn) {
            try {
                fn();
            } catch (error) {
                console.warn('[WPMind] ' + label + ' init failed:', error);
            }
        };

        safeInit('modules', initModuleSwitches);
    });
})(jQuery);
