(function ($) {
    function showMsg(msg, success) {
        var safeMsg = (msg === null || typeof msg === 'undefined') ? '' : String(msg);
        $('#scfpc-response')
            .show()
            .text(safeMsg)
            .css({
                background: success ? '#e7f5ea' : '#fde8e8',
                color: success ? '#0c5426' : '#c53030',
            });
    }

    $(document).ready(function () {
        var nonces = (typeof ddSolAdmin !== 'undefined' && ddSolAdmin.nonces) ? ddSolAdmin.nonces : {};
        var $overlay = $('[data-sol-overlay]');

        function closeModal() {
            $overlay.removeClass('is-active');
            $overlay.find('.sol-modal').removeClass('is-active');
        }

        function openModal(targetId) {
            var $modal = $('#' + targetId);
            if (!$modal.length) {
                return;
            }
            $overlay.addClass('is-active');
            $overlay.find('.sol-modal').removeClass('is-active');
            $modal.addClass('is-active');
            $modal.focus();
        }

        function showToast(message) {
            var $toast = $('#sol-toast');
            if (!$toast.length || !message) {
                return;
            }
            $toast.text(message).addClass('is-visible');
            setTimeout(function () {
                $toast.removeClass('is-visible');
            }, 2200);
        }

        if (typeof URLSearchParams !== 'undefined') {
            var params = new URLSearchParams(window.location.search);
            if (params.get('settings-updated') === '1' || params.get('settings-updated') === 'true') {
                showToast((ddSolAdmin.strings && ddSolAdmin.strings.saveSuccess) ? ddSolAdmin.strings.saveSuccess : 'Settings saved.');
            }
        }

        $('#scfpc-purge-all').on('click', function () {
            if (!confirm(ddSolAdmin.strings.purgeConfirm)) {
                return;
            }

            $('#scfpc-purge-spinner').addClass('is-active');
            $.post(ddSolAdmin.ajaxUrl, {
                action: 'scfpc_purge_all',
                nonce: nonces.purge,
            }, function (res) {
                $('#scfpc-purge-spinner').removeClass('is-active');
                showMsg(res.success ? (ddSolAdmin.strings.purgeSuccess || 'Purged Successfully!') : 'Error: ' + res.data, res.success);
            });
        });

        $('#scfpc-purge-home').on('click', function () {
            $('#scfpc-purge-spinner').addClass('is-active');
            $.post(ddSolAdmin.ajaxUrl, {
                action: 'scfpc_purge_homepage',
                nonce: nonces.purge,
            }, function (res) {
                $('#scfpc-purge-spinner').removeClass('is-active');
                showMsg(res.success ? (ddSolAdmin.strings.purgeHomeSuccess || 'Homepage purged!') : 'Error: ' + res.data, res.success);
            });
        });

        $('#scfpc-purge-urls').on('click', function () {
            var urls = $('#scfpc-purge-urls-input').val();
            if (!urls || !urls.trim()) {
                showMsg(ddSolAdmin.strings.purgeUrlsError || 'Please provide at least one valid URL.', false);
                return;
            }
            $('#scfpc-purge-spinner').addClass('is-active');
            $.post(ddSolAdmin.ajaxUrl, {
                action: 'scfpc_purge_urls',
                nonce: nonces.purge,
                urls: urls
            }, function (res) {
                $('#scfpc-purge-spinner').removeClass('is-active');
                showMsg(res.success ? (ddSolAdmin.strings.purgeUrlsSuccess || 'Selected URLs purged!') : 'Error: ' + res.data, res.success);
            });
        });

        $('#scfpc-update-rules').on('click', function () {
            if (!confirm(ddSolAdmin.strings.updateConfirm)) {
                return;
            }

            $('#scfpc-rules-spinner').addClass('is-active');
            $.post(ddSolAdmin.ajaxUrl, {
                action: 'scfpc_update_rules',
                nonce: nonces.rules,
            }, function (res) {
                $('#scfpc-rules-spinner').removeClass('is-active');
                showMsg(res.success ? (ddSolAdmin.strings.rulesSuccess || 'Rules Deployed!') : 'Error: ' + res.data, res.success);
            });
        });

        $(document).on('click', '[data-sol-modal]', function () {
            var target = $(this).data('solModal');
            openModal(target);
        });

        $(document).on('click', '[data-sol-close]', function () {
            closeModal();
        });

        $overlay.on('click', function (event) {
            if (event.target === this) {
                closeModal();
            }
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });




        var $lightbox = $('#sol-lightbox');
        if ($lightbox.length) {
            var $lightboxImg = $lightbox.find('img');
            function closeLightbox() {
                $lightbox.removeClass('is-active');
                $lightboxImg.attr('src', '').attr('alt', '');
            }

            $(document).on('click', '[data-sol-lightbox]', function () {
                var src = $(this).attr('src');
                var alt = $(this).attr('alt') || '';
                if (!src) {
                    return;
                }
                $lightboxImg.attr('src', src).attr('alt', alt);
                $lightbox.addClass('is-active');
            });

            $lightbox.on('click', function (event) {
                if (event.target === this) {
                    closeLightbox();
                }
            });

            $lightbox.find('.sol-lightbox-close').on('click', function () {
                closeLightbox();
            });

            $(document).on('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeLightbox();
                }
            });
        }

    });
})(jQuery);
