jQuery(document).ready(function($) {
    let isProcessing = false;
    const nonce = mosc_vars.nonce;

    function updateStats(stats) {
        $('#mosc-total-attachments').text(stats.total_attachments);
        $('#mosc-total-files').text(stats.total_files);
        $('#mosc-original-size').text(stats.original_size_formatted);
        $('#mosc-optimized-size').text(stats.optimized_size_formatted);
        $('#mosc-total-saved-percent').text(stats.total_saved_percent + '%');
        
        const percent = stats.total_attachments > 0 ? Math.round((stats.processed_count / stats.total_attachments) * 100) : 0;
        $('.mosc-progress-bar-fill').css('width', percent + '%');
        $('.mosc-progress-text').text(percent + '% (' + stats.processed_count + ' / ' + stats.total_attachments + ' Images Compressed)');
    }

    function processNext() {
        if (!isProcessing) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mosc_process_image',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.finished) {
                        stopProcessing();
                        return;
                    }
                    
                    updateStats(response.data.stats);
                    
                    if (isProcessing) {
                        processNext();
                    }
                } else {
                    // Even on hard error, try to continue to next image
                    if (isProcessing) {
                        setTimeout(function() {
                            processNext();
                        }, 1000);
                    }
                }
            },
            error: function() {
                // Even on AJAX failure, try to continue
                if (isProcessing) {
                    setTimeout(function() {
                        processNext();
                    }, 2000);
                }
            }
        });
    }

    function startProcessing() {
        isProcessing = true;
        $('#mosc-start').prop('disabled', true);
        $('#mosc-stop').prop('disabled', false).show();
        $('.mosc-spinner').addClass('is-active');
        $('.mosc-progress-text, .mosc-progress-bar-container').show();

        processNext();
    }

    function stopProcessing() {
        isProcessing = false;
        $('#mosc-start').prop('disabled', false);
        $('#mosc-stop').prop('disabled', true);
        $('.mosc-spinner').removeClass('is-active');
    }

    $('#mosc-start').on('click', startProcessing);
    $('#mosc-stop').on('click', stopProcessing);
});
