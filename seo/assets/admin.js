(function ($) {
    'use strict';
    $(function () {
        $('.ses-progress span').each(function () {
            if ($(this).width() === 0) {
                $(this).css('width', '8%');
            }
        });

        var autoScanRunning = false;
        var autoScanLastId = 0;
        var autoScanTotal = 0;
        var autoScanLimit = 0;
        var autoScanFailures = 0;
        var autoScanStats = { scanned: 0, eligible: 0, protected: 0 };

        function setAutoScanStatus(message, percent) {
            $('#ses-auto-scan-status').text(message);
            $('.ses-auto-progress span').css('width', Math.max(0, Math.min(100, percent || 0)) + '%');
        }

        function runAutoScanBatch() {
            if (!autoScanRunning) {
                return;
            }

            var limit = autoScanLimit || parseInt($('#ses-scan-limit').val(), 10) || 250;
            limit = Math.max(50, Math.min(parseInt(SESAdmin.maxScanBatch, 10) || 2000, limit));
            autoScanLimit = limit;

            $.post(SESAdmin.ajaxurl, {
                action: 'ses_scan_batch',
                nonce: SESAdmin.nonce,
                limit: limit,
                last_id: autoScanLastId,
                seconds: 8
            }).done(function (response) {
                if (!response || !response.success) {
                    retryAutoScan('Resposta invalida do servidor.');
                    return;
                }

                var data = response.data || {};
                autoScanFailures = 0;
                autoScanTotal = parseInt(data.total, 10) || autoScanTotal || 0;
                autoScanLastId = parseInt(data.next_last_id, 10) || autoScanLastId;
                autoScanStats.scanned += parseInt(data.scanned, 10) || 0;
                autoScanStats.eligible += parseInt(data.eligible, 10) || 0;
                autoScanStats.protected += parseInt(data.protected, 10) || 0;

                var percent = autoScanTotal ? (autoScanStats.scanned / autoScanTotal) * 100 : 0;
                setAutoScanStatus(
                    'Escaneadas: ' + autoScanStats.scanned +
                    ' | Elegiveis: ' + autoScanStats.eligible +
                    ' | Protegidas: ' + autoScanStats.protected +
                    ' | Ultimo ID: ' + autoScanLastId +
                    ' | Lote: ' + autoScanLimit +
                    ' | Rodadas internas: ' + (parseInt(data.turbo_rounds, 10) || 1) +
                    ' | Tempo: ' + (data.elapsed || 0) + 's',
                    percent
                );

                if (data.has_more && (!autoScanTotal || autoScanStats.scanned < autoScanTotal)) {
                    window.setTimeout(runAutoScanBatch, 150);
                    return;
                }

                autoScanRunning = false;
                $('#ses-auto-scan-start').prop('disabled', false);
                $('#ses-auto-scan-stop').prop('disabled', true);
                setAutoScanStatus(
                    'Scanner concluido. Escaneadas: ' + autoScanStats.scanned +
                    ' | Elegiveis: ' + autoScanStats.eligible +
                    ' | Protegidas: ' + autoScanStats.protected + '.',
                    100
                );
            }).fail(function () {
                retryAutoScan('Falha na requisicao AJAX.');
            });
        }

        function retryAutoScan(reason) {
            autoScanFailures++;
            autoScanLimit = Math.max(50, Math.floor((autoScanLimit || 250) / 2));

            if (autoScanFailures > 6 || (autoScanLimit <= 50 && autoScanFailures > 2)) {
                autoScanRunning = false;
                $('#ses-auto-scan-start').prop('disabled', false);
                $('#ses-auto-scan-stop').prop('disabled', true);
                setAutoScanStatus(reason + ' Scanner pausado no ultimo ID ' + autoScanLastId + '. Tente novamente com lote 50.', autoScanTotal ? (autoScanStats.scanned / autoScanTotal) * 100 : 0);
                return;
            }

            setAutoScanStatus(reason + ' Reduzindo lote para ' + autoScanLimit + ' e tentando continuar do ID ' + autoScanLastId + '...', autoScanTotal ? (autoScanStats.scanned / autoScanTotal) * 100 : 0);
            window.setTimeout(runAutoScanBatch, 2000);
        }

        $('#ses-auto-scan-start').on('click', function () {
            autoScanRunning = true;
            autoScanLastId = 0;
            autoScanTotal = parseInt(String($('#ses-scan-total').text()).replace(/\D/g, ''), 10) || 0;
            autoScanLimit = Math.max(50, Math.min(parseInt(SESAdmin.maxScanBatch, 10) || 2000, parseInt($('#ses-scan-limit').val(), 10) || 250));
            autoScanFailures = 0;
            autoScanStats = { scanned: 0, eligible: 0, protected: 0 };
            $(this).prop('disabled', true);
            $('#ses-auto-scan-stop').prop('disabled', false);
            setAutoScanStatus('Iniciando scanner automatico...', 1);
            runAutoScanBatch();
        });

        $('#ses-auto-scan-stop').on('click', function () {
            autoScanRunning = false;
            $('#ses-auto-scan-start').prop('disabled', false);
            $(this).prop('disabled', true);
            setAutoScanStatus('Scanner pausado no ultimo ID ' + autoScanLastId + '.', autoScanTotal ? (autoScanStats.scanned / autoScanTotal) * 100 : 0);
        });

        var autoEnrichRunning = false;
        var autoEnrichLastId = 0;
        var autoEnrichTotal = 0;
        var autoEnrichLimit = 0;
        var autoEnrichFailures = 0;
        var autoEnrichStats = { processed: 0, enriched: 0, errors: 0 };

        function setAutoEnrichStatus(message, percent) {
            $('#ses-auto-enrich-status').text(message);
            $('.ses-enrich-progress span').css('width', Math.max(0, Math.min(100, percent || 0)) + '%');
        }

        function runAutoEnrichBatch() {
            if (!autoEnrichRunning) {
                return;
            }

            var limit = autoEnrichLimit || parseInt($('#ses-enrich-limit').val(), 10) || 5;
            limit = Math.max(1, Math.min(parseInt(SESAdmin.maxEnrichBatch, 10) || 50, limit));
            autoEnrichLimit = limit;

            $.post(SESAdmin.ajaxurl, {
                action: 'ses_enrich_batch',
                nonce: SESAdmin.nonce,
                limit: limit,
                last_id: autoEnrichLastId,
                seconds: 8
            }).done(function (response) {
                if (!response || !response.success) {
                    retryAutoEnrich('Resposta invalida do servidor.');
                    return;
                }

                var data = response.data || {};
                autoEnrichFailures = 0;
                autoEnrichTotal = parseInt(data.total, 10) || autoEnrichTotal || 0;
                autoEnrichLastId = parseInt(data.next_last_id, 10) || autoEnrichLastId;
                autoEnrichStats.processed += parseInt(data.processed, 10) || 0;
                autoEnrichStats.enriched += parseInt(data.enriched, 10) || 0;
                autoEnrichStats.errors += parseInt(data.errors, 10) || 0;

                var remaining = autoEnrichTotal;
                var initialTotal = autoEnrichStats.processed + remaining;
                var percent = initialTotal ? (autoEnrichStats.processed / initialTotal) * 100 : 0;
                setAutoEnrichStatus(
                    'Processadas: ' + autoEnrichStats.processed +
                    ' | Enriquecidas: ' + autoEnrichStats.enriched +
                    ' | Erros: ' + autoEnrichStats.errors +
                    ' | Restantes: ' + remaining +
                    ' | Ultimo ID: ' + autoEnrichLastId +
                    ' | Lote: ' + autoEnrichLimit +
                    ' | Rodadas internas: ' + (parseInt(data.turbo_rounds, 10) || 1) +
                    ' | Tempo: ' + (data.elapsed || 0) + 's',
                    percent
                );

                if ((data.has_more || remaining > 0) && (parseInt(data.processed, 10) || 0) > 0) {
                    window.setTimeout(runAutoEnrichBatch, 350);
                    return;
                }

                autoEnrichRunning = false;
                $('#ses-auto-enrich-start').prop('disabled', false);
                $('#ses-auto-enrich-stop').prop('disabled', true);
                setAutoEnrichStatus(
                    'Enriquecimento concluido. Processadas: ' + autoEnrichStats.processed +
                    ' | Enriquecidas: ' + autoEnrichStats.enriched +
                    ' | Erros: ' + autoEnrichStats.errors + '.',
                    100
                );
            }).fail(function () {
                retryAutoEnrich('Falha na requisicao AJAX.');
            });
        }

        function retryAutoEnrich(reason) {
            autoEnrichFailures++;
            autoEnrichLimit = Math.max(1, Math.floor((autoEnrichLimit || 5) / 2));

            if (autoEnrichFailures > 6 || (autoEnrichLimit <= 1 && autoEnrichFailures > 2)) {
                autoEnrichRunning = false;
                $('#ses-auto-enrich-start').prop('disabled', false);
                $('#ses-auto-enrich-stop').prop('disabled', true);
                setAutoEnrichStatus(reason + ' Enriquecimento pausado no ultimo ID ' + autoEnrichLastId + '. Tente novamente com lote 1.', 0);
                return;
            }

            setAutoEnrichStatus(reason + ' Reduzindo lote para ' + autoEnrichLimit + ' e tentando continuar do ID ' + autoEnrichLastId + '...', 0);
            window.setTimeout(runAutoEnrichBatch, 2500);
        }

        $('#ses-auto-enrich-start').on('click', function () {
            autoEnrichRunning = true;
            autoEnrichLastId = 0;
            autoEnrichTotal = parseInt(String($('#ses-enrich-total').text()).replace(/\D/g, ''), 10) || 0;
            autoEnrichLimit = Math.max(1, Math.min(parseInt(SESAdmin.maxEnrichBatch, 10) || 50, parseInt($('#ses-enrich-limit').val(), 10) || 5));
            autoEnrichFailures = 0;
            autoEnrichStats = { processed: 0, enriched: 0, errors: 0 };
            $(this).prop('disabled', true);
            $('#ses-auto-enrich-stop').prop('disabled', false);
            setAutoEnrichStatus('Iniciando enriquecimento automatico...', 1);
            runAutoEnrichBatch();
        });

        $('#ses-auto-enrich-stop').on('click', function () {
            autoEnrichRunning = false;
            $('#ses-auto-enrich-start').prop('disabled', false);
            $(this).prop('disabled', true);
            setAutoEnrichStatus('Enriquecimento pausado no ultimo ID ' + autoEnrichLastId + '.', 0);
        });
    });
})(jQuery);
