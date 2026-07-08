jQuery(document).ready(function($) {
    let isProcessing = false;
    const nonce = kaic_vars.nonce;

    function updateStats(stats) {
        $('#kaic-total-attachments').text(stats.total_attachments);
        $('#kaic-total-files').text(stats.total_files);
        $('#kaic-original-size').text(stats.original_size_formatted);
        $('#kaic-optimized-size').text(stats.optimized_size_formatted);
        $('#kaic-total-saved-percent').text(stats.total_saved_percent + '%');
        
        const percent = stats.total_attachments > 0 ? Math.round((stats.processed_count / stats.total_attachments) * 100) : 0;
        $('.kaic-progress-bar-fill').css('width', percent + '%');
        $('.kaic-progress-text').text(percent + '% (' + stats.processed_count + ' / ' + stats.total_attachments + ' Images Compressed)');
    }

    function addRow(imageData) {
        const row = $('<tr id="kaic-row-' + imageData.id + '">' +
            '<td><img src="' + imageData.thumb + '" style="width: 50px; height: auto;"></td>' +
            '<td>' + imageData.filename + '</td>' +
            '<td>' + imageData.original_size + '</td>' +
            '<td class="optimized-size">-</td>' +
            '<td class="savings">-</td>' +
            '<td class="status"><span class="spinner is-active" style="float: none; visibility: visible; margin: 0;"></span></td>' +
            '</tr>');
        $('#kaic-progress-log').append(row);
    }

    function updateRow(imageData) {
        const $row = $('#kaic-row-' + imageData.id);
        if (imageData.error) {
            $row.addClass('error');
            $row.find('.status').html('<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ' + imageData.error);
        } else {
            $row.find('.optimized-size').text(imageData.optimized_size);
            $row.find('.savings').text(imageData.saved_percent + '%');
            $row.find('.status').html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span>');
        }
    }

    function processNext() {
        if (!isProcessing) {
            return;
        }

        // Step 1: Get next image metadata
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'kaic_get_next_image',
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.finished) {
                        stopProcessing();
                        return;
                    }
                    
                    if (response.data.image) {
                        const image = response.data.image;
                        addRow(image);
                        
                        // Step 2: Process this image
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'kaic_process_image',
                                id: image.id,
                                nonce: nonce
                            },
                            success: function(procResponse) {
                                if (procResponse.success) {
                                    updateRow(procResponse.data.image);
                                    updateStats(procResponse.data.stats);
                                    
                                    if (isProcessing) {
                                        processNext();
                                    }
                                } else {
                                    // Handle internal error for this image
                                    updateRow({
                                        id: image.id,
                                        error: procResponse.data || 'Unknown error'
                                    });
                                    if (isProcessing) {
                                        processNext();
                                    }
                                }
                            },
                            error: function() {
                                updateRow({
                                    id: image.id,
                                    error: 'AJAX Error during processing'
                                });
                                if (isProcessing) {
                                    setTimeout(processNext, 2000);
                                }
                            }
                        });
                    }
                } else {
                    if (isProcessing) {
                        setTimeout(processNext, 2000);
                    }
                }
            },
            error: function() {
                if (isProcessing) {
                    setTimeout(processNext, 2000);
                }
            }
        });
    }

    function startProcessing() {
        isProcessing = true;
        $('#kaic-start').prop('disabled', true);
        $('#kaic-stop').prop('disabled', false).show();
        $('.kaic-spinner').addClass('is-active');
        $('.kaic-progress-text, .kaic-progress-bar-container, #kaic-progress-table-container').show();

        processNext();
    }

    function stopProcessing() {
        isProcessing = false;
        $('#kaic-start').prop('disabled', false);
        $('#kaic-stop').prop('disabled', true);
        $('.kaic-spinner').removeClass('is-active');
    }

    $('#kaic-start').on('click', startProcessing);
    $('#kaic-stop').on('click', stopProcessing);
});
