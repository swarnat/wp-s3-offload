(function($) {
    'use strict';

    $('#test_connection').on('click', function(e) {
        $.ajax({
            type: 'POST',
            url: 'admin-post.php',
            data: {
                action: 's3_test_connection'
            }
        }).then(function(response) {
            if(response != "OK") {
                alert(response)
            } else {
                alert("Test successfully.")
            }

            
        })

    })

    $('#start_batch_migration').on('click', function(e) {
        const step = Math.round(100 / $('#migration_log').data('count'));
        let currentPercent = 0;

        function updateProgressBar() {
            $('#progress_bar_bar').css('width', currentPercent + '%');
            $('#progress_bar_percent').text(currentPercent + ' %')
        }


        function batchSync() {
            $.ajax({
                type: 'POST',
                url: 'admin-post.php',
                dataType: 'json',
                data: {
                    action: 's3_sync_batch'
                }
            }).then(function(response) {
                if(response.done.length > 0) {
                    let html = '';
                    for(const entry of response.done) {
                        html += '<div><strong>#' + entry.id + ' - ' + entry.name + '</strong> done</div>';
                        currentPercent += step;
                    }

                    $('#migration_protocol').append(html);                    

                    updateProgressBar();
                    setTimeout(batchSync, 500);
                } else {
                    currentPercent = 100;
                    updateProgressBar();
                }
                
            })
        }

        updateProgressBar();
        $('#migration_log').show();

        batchSync();
    });

    $('.s3-sync-file-btn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var url = $(e.currentTarget).data('url');

        jQuery.get(url, function() {
            $(e.currentTarget).html('<span class="dashicons dashicons-saved"></span>');
        });
    });
})(jQuery);