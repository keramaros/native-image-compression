jQuery(document).ready(function($) {
    let isProcessing = false;
    const nonce = kaic_vars.nonce;

    function updateStats(stats) {
        $('#kaic-total-attachments').text(stats.total_attachments);
        $('#kaic-total-files').text(stats.total_files);
        $('#kaic-processed-count').text(stats.processed_count);
        $('#kaic-original-size').text(stats.original_size_formatted);
        $('#kaic-optimized-size').text(stats.optimized_size_formatted);
        $('#kaic-total-saved-percent').text(stats.total_saved_percent + '%');
    }

    function addRow(imageData) {
        const row = $('<tr id="kaic-row-' + imageData.id + '">' +
            '<td class="column-file">' +
                '<div style="display: flex; align-items: center; gap: 10px;">' +
                    '<img src="' + imageData.thumb + '" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">' +
                    '<span>' + imageData.filename + '</span>' +
                '</div>' +
            '</td>' +
            '<td class="column-initial-size">' + imageData.original_size + '</td>' +
            '<td class="column-current-size">-</td>' +
            '<td class="column-savings">-</td>' +
            '<td class="column-status"><span class="spinner is-active" style="float: none; visibility: visible; margin: 0;"></span></td>' +
            '</tr>');
        $('#kaic-progress-log').append(row);
    }

    function updateRow(imageData) {
        const $row = $('#kaic-row-' + imageData.id);
        if (imageData.error) {
            $row.addClass('error');
            $row.find('.column-status').html('<span class="dashicons dashicons-warning" style="color: #d63638;"></span> ' + imageData.error);
        } else {
            $row.find('.column-current-size').text(imageData.optimized_size);
            $row.find('.column-savings').text(imageData.saved_percent + '%');
            $row.find('.column-status').html('<span class="dashicons dashicons-yes" style="color: #46b450;"></span>');
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
