/**
 * ======================================================================
 * LICENSE: This file is subject to the terms and conditions defined in *
 * file 'license.txt', which is part of this source code package.       *
 * ======================================================================
 */

(function ($) {

    /**
     * Site list for sync process
     */
    var syncData = {};

    /**
     * 
     * @param {type} id
     * @returns {Boolean}
     */
    function isCurrent(id) {
        return (aamMultisite.current === id);
    }

    /**
     * 
     * @param {*} index 
     * @param {*} groups 
     */
    function triggerSync(index) {
        $.ajax(aamLocal.ajaxurl, {
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'aam',
                sub_action: 'Multisite.sync',
                _ajax_nonce: aamLocal.nonce,
                data: syncData.data,
                offset: index
            },
            success: function (response) {
                if (syncData.sites >= index) {
                    var progress = Math.round(index / syncData.sites * 100) + '%';
                    $('#multisite-sync-progress .progress-bar').css('width', progress).text(progress);
                    triggerSync(index + 1);
                } else {
                    $('#multisite-sync-progress .progress-bar').css('width', '100%').text('100%');
                    aam.notification('success', aam.__('Sync process has been completed successfully'));
                    location.reload();
                }
            },
            error: function () {
                aam.notification('danger', aam.__('Application error'));
            }
        });
    }

    /**
     * 
     * @returns {undefined}
     */
    function initialize() {
        $('#site-list').DataTable({
            autoWidth: false,
            ordering: false,
            dom: 'trip',
            pagingType: 'simple',
            processing: true,
            serverSide: true,
            ajax: {
                url: aamLocal.ajaxurl,
                type: 'POST',
                data: {
                    action: 'aam',
                    sub_action: 'Multisite.getTable',
                    _ajax_nonce: aamLocal.nonce
                }
            },
            columnDefs: [
                {visible: false, targets: [0, 1, 2, 5, 6]}
            ],
            language: {
                search: '_INPUT_',
                searchPlaceholder: aam.__('Search Site'),
                info: aam.__('_TOTAL_ sites(s)'),
                infoFiltered: ''
            },
            createdRow: function (row, data) {
                var main = (parseInt(data[5]) ? ' <small>main site</small>' : '');
                
                if (isCurrent(data[0])) {
                    $('td:eq(0)', row).html(
                        '<strong>' + data[3] + '</strong>' + main
                    );
                } else {
                    $('td:eq(0)', row).html(
                        '<span>' + data[3] + '</span>' + main
                    );
                }

                var actions = data[4].split(',');

                var container = $('<div/>', {'class': 'aam-row-actions'});
                $.each(actions, function (i, action) {
                    switch (action) {
                        case 'manage':
                            $(container).append($('<i/>', {
                                'class': 'aam-row-action ' + (parseInt(data[6]) ? 'icon-link' : 'icon-cog') + ' text-info'
                            }).bind('click', function () {
                                if (parseInt(data[6])) {
                                    window.open(data[1], '_blank');
                                } else {
                                    aamMultisite.current = data[0];
                                    aamLocal.url.site    = data[1];
                                    aamLocal.ajaxurl     = data[2];

                                    $('#site-list tbody tr strong').each(function () {
                                        $(this).replaceWith(
                                               '<span>' + $(this).text() + '</span>'
                                        ); 
                                    });
                                    $('td:eq(0) span', row).replaceWith(
                                            '<strong>' + data[3] + '</strong>'
                                    );
                                    $('i.icon-cog', container).attr(
                                        'class', 'aam-row-action icon-spin4 animate-spin'
                                    );

                                    $('i.icon-spin4', container).attr(
                                        'class', 'aam-row-action icon-cog text-info'
                                    );
                                    aam.triggerHook('refresh');
                                }
                            }).attr({'data-toggle': 'tooltip', 'title': aam.__('Manage Site')}));
                            break;
                        
                        case 'sync':
                            $(container).append($('<i/>', {
                                'class': 'aam-row-action text-warning icon-arrows-cw'
                            }).bind('click', function () {
                                $('#multisite-sync-modal').modal('show');
                            }).attr({'data-toggle': 'tooltip', 'title': aam.__('Sync Settings')}));
                            break;

                        default:
                            break;
                    }
                });
                $('td:eq(1)', row).html(container);

            }
        });

        $('#multisite-sync-btn').bind('click', function() {
            $(this).prop('disabled', true).text(aam.__('Wait...'));

            $('#multisite-sync-options').hide();
            $('#multisite-sync-progress .progress-bar').css('width', '0%');
            $('#multisite-sync-step').text(aam.__('collecting information'));
            $('#multisite-sync-progress').show();

            $.ajax(aamLocal.ajaxurl, {
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'aam',
                    sub_action: 'Multisite.prepareSync',
                    _ajax_nonce: aamLocal.nonce
                },
                success: function (response) {
                    syncData = response;

                    triggerSync(1);
                },
                error: function () {
                    aam.notification('danger', aam.__('Application error'));
                }
            });
        });
    }

    $(document).ready(function () {
        initialize();
    });

})(jQuery);