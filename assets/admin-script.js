/**
 * ReTextify Plugin - Admin JavaScript
 * Author: Imponi
 */

jQuery(document).ready(function($) {
    
    // Initialisierung
    initializeReTextifyInterface();
    
    function initializeReTextifyInterface() {
        // Vorschau automatisch beim Laden anzeigen
        updatePreview();
        
        // Event-Listener für Checkbox-Änderungen
        $('.retexify-post-type-checkbox, .retexify-content-checkbox, .retexify-status-checkbox').on('change', function() {
            updatePreview();
        });
        
        // Smooth Animationen für Checkboxen
        $('.retexify-checkbox-item').each(function(index) {
            $(this).css('opacity', '0').delay(index * 50).animate({
                opacity: 1
            }, 300);
        });
    }
    
    // Vorschau automatisch aktualisieren
    function updatePreview() {
        var selections = getSelections();
        
        // Mindestens eine Auswahl erforderlich
        if (selections.post_types.length === 0 || 
            selections.content_types.length === 0 || 
            selections.post_status.length === 0) {
            $('#retexify-preview-result').hide().text('');
            return;
        }
        
        // AJAX-Request für Vorschau
        $.ajax({
            url: retexify_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'retexify_get_preview',
                nonce: retexify_ajax.nonce,
                selections: JSON.stringify(selections)
            },
            success: function(response) {
                if (response.success) {
                    var previewText = '📊 <strong>' + response.data.total_items + '</strong> Einträge werden exportiert';
                    if (response.data.posts_count > 0) {
                        previewText += ' (' + response.data.posts_count + ' Posts/Seiten';
                        if (response.data.images_count > 0) {
                            previewText += ', ' + response.data.images_count + ' Bilder';
                        }
                        previewText += ')';
                    }
                    
                    $('#retexify-preview-result').html(previewText).fadeIn(300);
                } else {
                    $('#retexify-preview-result').hide();
                }
            },
            error: function() {
                $('#retexify-preview-result').hide();
            }
        });
    }
    
    // AJAX-Test
    $('#retexify-test-btn').on('click', function() {
        var $result = $('#retexify-test-result');
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $result.html('🔄 Teste AJAX-Verbindung...');
        
        $.ajax({
            url: retexify_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'retexify_test_ajax',
                nonce: retexify_ajax.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false);
                $result.html('<div style="color: green; background: #d1e7dd; padding: 10px; border-radius: 5px;">✅ AJAX funktioniert! Plugin ist bereit.</div>');
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false);
                $result.html('<div style="color: red; background: #f8d7da; padding: 10px; border-radius: 5px;">❌ AJAX-Fehler: ' + error + '</div>');
            }
        });
    });
    
    // Manuelle Vorschau anzeigen (falls gewünscht)
    $('#retexify-preview-btn').on('click', function() {
        var $result = $('#retexify-preview-result');
        var $btn = $(this);
        
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Lade...');
        
        var selections = getSelections();
        
        if (selections.post_types.length === 0) {
            alert('Bitte wählen Sie mindestens einen Post-Typ aus.');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Vorschau anzeigen');
            return;
        }
        
        if (selections.content_types.length === 0) {
            alert('Bitte wählen Sie mindestens einen Inhaltstyp aus.');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Vorschau anzeigen');
            return;
        }
        
        if (selections.post_status.length === 0) {
            alert('Bitte wählen Sie mindestens einen Status aus.');
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Vorschau anzeigen');
            return;
        }
        
        $.ajax({
            url: retexify_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'retexify_get_preview',
                nonce: retexify_ajax.nonce,
                selections: JSON.stringify(selections)
            },
            success: function(response) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Vorschau anzeigen');
                
                if (response.success) {
                    $result.html('📊 <strong>' + response.data.total_items + '</strong> Einträge werden exportiert ' +
                        '(' + response.data.posts_count + ' Posts/Seiten, ' + response.data.images_count + ' Bilder)')
                        .addClass('show');
                } else {
                    $result.html('❌ Fehler: ' + (response.data || 'Unbekannter Fehler')).addClass('show');
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Vorschau anzeigen');
                $result.html('❌ AJAX-Fehler: ' + error).addClass('show');
            }
        });
    });
    
    // Export mit Auswahl
    $('#retexify-export-btn').on('click', function() {
        var $result = $('#retexify-export-result');
        var $btn = $(this);
        
        var selections = getSelections();
        
        // Validierung der Auswahl
        if (selections.post_types.length === 0) {
            showAlert('Bitte wählen Sie mindestens einen Post-Typ aus.', 'warning');
            return;
        }
        
        if (selections.content_types.length === 0) {
            showAlert('Bitte wählen Sie mindestens einen Inhaltstyp aus.', 'warning');
            return;
        }
        
        if (selections.post_status.length === 0) {
            showAlert('Bitte wählen Sie mindestens einen Status aus.', 'warning');
            return;
        }
        
        // Loading-State
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Exportiere...');
        $result.html('<div style="color: #0073aa; padding: 10px; background: #f0f6fc; border-radius: 5px;">🔄 Export wird gestartet...</div>');
        
        // Progress Animation starten
        showProgress('export');
        
        $.ajax({
            url: retexify_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'retexify_export_texts',
                nonce: retexify_ajax.nonce,
                selections: JSON.stringify(selections)
            },
            success: function(response) {
                hideProgress('export');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Auswahl exportieren');
                
                if (response.success) {
                    $result.html('<div style="color: green; background: #d1e7dd; padding: 15px; border-radius: 5px;">' +
                        '✅ <strong>' + response.data.message + '</strong><br>' +
                        '<small>Insgesamt ' + response.data.total_items + ' Einträge exportiert</small><br>' +
                        '<a href="' + response.data.download_url + '" style="background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin-top: 10px; display: inline-block;">' +
                        '<span class="dashicons dashicons-download"></span> CSV-Datei herunterladen</a>' +
                        '</div>');
                    
                    // Erfolgs-Animation
                    animateSuccess($result);
                    playSuccessSound();
                    
                } else {
                    showError($result, response.data || 'Unbekannter Fehler');
                }
            },
            error: function(xhr, status, error) {
                hideProgress('export');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Auswahl exportieren');
                showError($result, 'AJAX-Fehler: ' + error);
            }
        });
    });
    
    // Import-Datei-Auswahl
    $('#retexify-import-file').on('change', function() {
        var file = this.files[0];
        var $btn = $('#retexify-import-btn');
        var $info = $('#retexify-file-info');
        
        if (file) {
            var extension = file.name.toLowerCase().split('.').pop();
            var isValidFile = (extension === 'csv' || extension === 'xlsx');
            
            if (isValidFile) {
                $btn.prop('disabled', false);
                $('#retexify-import-preview-btn').prop('disabled', false);
                
                // Detaillierte Dateiinfo anzeigen
                var fileInfo = '📁 Datei ausgewählt: <strong>' + file.name + '</strong><br>';
                fileInfo += '<small>Größe: ' + formatFileSize(file.size) + ' • ';
                fileInfo += 'Typ: ' + (file.type || 'unbekannt') + ' • ';
                fileInfo += 'Geändert: ' + new Date(file.lastModified).toLocaleString() + '</small>';
                
                $info.html(fileInfo).css('color', '#0a8f0a').fadeIn(300);
                
                // CSV-Validierung
                if (extension === 'csv') {
                    validateCSVFile(file);
                }
                
            } else {
                $btn.prop('disabled', true);
                $('#retexify-import-preview-btn').prop('disabled', true);
                $info.html('❌ Ungültiges Format. Bitte wählen Sie eine CSV- oder Excel-Datei (.xlsx) aus.')
                    .css('color', '#d63384').fadeIn(300);
            }
        } else {
            $btn.prop('disabled', true);
            $('#retexify-import-preview-btn').prop('disabled', true);
            $info.fadeOut(300).html('');
        }
    });
    
    // Import-Vorschau
    $('#retexify-import-preview-btn').on('click', function() {
        var file = $('#retexify-import-file')[0].files[0];
        var $btn = $(this);
        var $preview = $('#retexify-import-preview');
        var $previewContent = $('#retexify-import-preview-content');
        
        if (!file) {
            showAlert('Bitte wählen Sie zuerst eine Datei aus.', 'warning');
            return;
        }
        
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Analysiere...');
        $preview.hide();
        
        var formData = new FormData();
        formData.append('action', 'retexify_import_preview');
        formData.append('nonce', retexify_ajax.nonce);
        formData.append('preview_file', file);
        
        $.ajax({
            url: retexify_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Vorschau anzeigen');
                
                if (response.success) {
                    var data = response.data;
                    var previewHtml = '';
                    
                    previewHtml += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;">';
                    previewHtml += '<div style="text-align: center; padding: 10px; background: #e7f3ff; border-radius: 5px;">';
                    previewHtml += '<div style="font-size: 24px; font-weight: bold; color: #004085;">' + data.posts_to_update + '</div>';
                    previewHtml += '<div style="font-size: 12px;">Posts zu aktualisieren</div>';
                    previewHtml += '</div>';
                    
                    previewHtml += '<div style="text-align: center; padding: 10px; background: #e7f3ff; border-radius: 5px;">';
                    previewHtml += '<div style="font-size: 24px; font-weight: bold; color: #004085;">' + data.images_to_update + '</div>';
                    previewHtml += '<div style="font-size: 12px;">Bilder zu aktualisieren</div>';
                    previewHtml += '</div>';
                    
                    previewHtml += '<div style="text-align: center; padding: 10px; background: #e7f3ff; border-radius: 5px;">';
                    previewHtml += '<div style="font-size: 24px; font-weight: bold; color: #004085;">' + data.total_rows + '</div>';
                    previewHtml += '<div style="font-size: 12px;">Gesamt-Zeilen</div>';
                    previewHtml += '</div>';
                    previewHtml += '</div>';
                    
                    if (data.post_types && Object.keys(data.post_types).length > 0) {
                        previewHtml += '<div style="margin-bottom: 15px;"><strong>Betroffene Post-Typen:</strong><br>';
                        for (var postType in data.post_types) {
                            previewHtml += '<span style="background: #f0f0f1; padding: 3px 8px; margin: 2px; border-radius: 3px; display: inline-block;">';
                            previewHtml += postType + ' (' + data.post_types[postType] + ')';
                            previewHtml += '</span>';
                        }
                        previewHtml += '</div>';
                    }
                    
                    if (data.warnings && data.warnings.length > 0) {
                        previewHtml += '<div style="background: #fff3cd; padding: 10px; border-radius: 5px; border: 1px solid #ffc107;">';
                        previewHtml += '<strong>⚠️ Warnungen:</strong><ul style="margin: 5px 0 0 20px;">';
                        data.warnings.forEach(function(warning) {
                            previewHtml += '<li>' + warning + '</li>';
                        });
                        previewHtml += '</ul></div>';
                    }
                    
                    $previewContent.html(previewHtml);
                    $preview.fadeIn(300);
                    
                } else {
                    showAlert('Fehler bei der Vorschau: ' + (response.data || 'Unbekannter Fehler'), 'error');
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Vorschau anzeigen');
                showAlert('AJAX-Fehler bei der Vorschau: ' + error, 'error');
            }
        });
    });
    
    // CSV-Datei vorab validieren
    function validateCSVFile(file) {
        if (file.size > 100 * 1024) return; // Nur kleine Dateien validieren
        
        var reader = new FileReader();
        reader.onload = function(e) {
            var content = e.target.result;
            var lines = content.split('\n');
            
            if (lines.length < 2) {
                showAlert('CSV-Datei scheint leer oder unvollständig zu sein.', 'warning');
                return;
            }
            
            var header = lines[0];
            var expectedColumns = ['ID', 'Typ', 'URL', 'Titel'];
            var hasValidHeader = expectedColumns.some(col => header.includes(col));
            
            if (!hasValidHeader) {
                showAlert('CSV-Header nicht erkannt. Stellen Sie sicher, dass die Datei von diesem Plugin exportiert wurde.', 'warning');
            } else if (retexify_ajax.debug) {
                console.log('CSV-Validierung erfolgreich - Header erkannt:', header.substring(0, 100));
            }
        };
        
        reader.readAsText(file.slice(0, 1024)); // Nur erste 1KB lesen
    }
    
    // Import mit automatischem Backup
    $('#retexify-import-btn').on('click', function() {
        var file = $('#retexify-import-file')[0].files[0];
        var $result = $('#retexify-import-result');
        var $btn = $(this);
        
        if (!file) {
            showAlert('Bitte wählen Sie zuerst eine Datei aus.', 'warning');
            return;
        }
        
        if (!confirm('⚠️ WARNUNG: Dieser Import überschreibt bestehende Texte!\n\nEs wird automatisch ein Backup erstellt.\n\nMöchten Sie den Import durchführen?')) {
            return;
        }
        
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Erstelle Backup...');
        $result.html('<div style="color: #0073aa; padding: 10px; background: #f0f6fc; border-radius: 5px;">🔄 Backup wird erstellt...</div>');
        
        // Schritt 1: Backup erstellen
        $.ajax({
            url: retexify_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'retexify_create_backup',
                nonce: retexify_ajax.nonce
            },
            success: function(backupResponse) {
                if (backupResponse.success) {
                    $result.html('<div style="color: #0073aa; padding: 10px; background: #f0f6fc; border-radius: 5px;">✅ Backup erstellt • 🔄 Import wird gestartet...</div>');
                    $btn.html('<span class="dashicons dashicons-update"></span> Importiere...');
                    
                    // Schritt 2: Import durchführen
                    var formData = new FormData();
                    formData.append('action', 'retexify_import_texts');
                    formData.append('nonce', retexify_ajax.nonce);
                    formData.append('import_file', file);
                    formData.append('backup_file', backupResponse.data.filename);
                    
                    $.ajax({
                        url: retexify_ajax.ajax_url,
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        timeout: 180000, // 3 Minuten Timeout
                        success: function(importResponse) {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Texte importieren');
                            
                            if (importResponse.success) {
                                $result.html('<div style="color: green; background: #d1e7dd; padding: 15px; border-radius: 5px;">' +
                                    '✅ <strong>' + importResponse.data.message + '</strong><br>' +
                                    '<small>Posts/Seiten: ' + importResponse.data.updated_posts + ' • Bilder: ' + importResponse.data.updated_images + '</small><br>' +
                                    '<small>📁 Backup verfügbar: ' + backupResponse.data.filename + '</small>' +
                                    '</div>');
                                
                                // UI zurücksetzen
                                $('#retexify-import-file').val('');
                                $('#retexify-file-info').fadeOut(300).html('');
                                $('#retexify-import-preview').hide();
                                $('#retexify-import-preview-btn').prop('disabled', true);
                                $btn.prop('disabled', true);
                                
                                // Erfolgs-Animation
                                animateSuccess($result);
                                playSuccessSound();
                                
                                // Seite nach 3 Sekunden neu laden um aktuelle Daten zu zeigen
                                setTimeout(function() {
                                    if (confirm('Import erfolgreich! Seite neu laden um aktuelle Statistiken zu sehen?')) {
                                        location.reload();
                                    }
                                }, 3000);
                                
                            } else {
                                showError($result, 'Import-Fehler: ' + (importResponse.data || 'Unbekannter Fehler') + '<br><small>Backup verfügbar: ' + backupResponse.data.filename + '</small>');
                            }
                        },
                        error: function(xhr, status, error) {
                            $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Texte importieren');
                            
                            var errorMsg = 'Import-Fehler: ';
                            if (status === 'timeout') {
                                errorMsg += 'Timeout - Der Import dauerte zu lange. Bitte versuchen Sie es mit einer kleineren Datei.';
                            } else {
                                errorMsg += error;
                            }
                            errorMsg += '<br><small>Backup verfügbar: ' + backupResponse.data.filename + '</small>';
                            
                            showError($result, errorMsg);
                        }
                    });
                    
                } else {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Texte importieren');
                    showError($result, 'Backup-Fehler: ' + (backupResponse.data || 'Backup konnte nicht erstellt werden'));
                }
            },
            error: function(xhr, status, error) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Texte importieren');
                showError($result, 'Backup-Fehler: ' + error);
            }
        });
    });
    
    // Hilfsfunktionen
    function getSelections() {
        var post_types = [];
        $('.retexify-post-type-checkbox:checked').each(function() {
            post_types.push($(this).val());
        });
        
        var content_types = [];
        $('.retexify-content-checkbox:checked').each(function() {
            content_types.push($(this).val());
        });
        
        var post_status = [];
        $('.retexify-status-checkbox:checked').each(function() {
            post_status.push($(this).val());
        });
        
        return {
            post_types: post_types,
            content_types: content_types,
            post_status: post_status
        };
    }
    
    function showProgress(type) {
        // Hier könnten individuelle Progress-Bars für Export/Import angezeigt werden
        // Aktuell wird die Animation im CSS gehandhabt
    }
    
    function hideProgress(type) {
        // Progress-Bars verstecken
    }
    
    function showError($result, message) {
        $result.html('<div style="color: red; background: #f8d7da; padding: 15px; border-radius: 5px;">' +
            '❌ <strong>Fehler:</strong><br>' + message + '</div>');
        
        // Shake-Animation für Fehler
        $result.effect('shake', { times: 3 }, 600);
    }
    
    function animateSuccess($element) {
        $element.fadeOut(300).fadeIn(300).fadeOut(300).fadeIn(300);
    }
    
    function showAlert(message, type) {
        var bgColor = type === 'warning' ? '#fff3cd' : '#f8d7da';
        var textColor = type === 'warning' ? '#856404' : '#842029';
        var icon = type === 'warning' ? '⚠️' : '❌';
        
        var $alert = $('<div>')
            .html('<strong>' + icon + ' ' + message + '</strong>')
            .css({
                'position': 'fixed',
                'top': '50px',
                'right': '20px',
                'background': bgColor,
                'color': textColor,
                'padding': '15px 20px',
                'border-radius': '5px',
                'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                'z-index': '9999',
                'max-width': '400px',
                'font-size': '14px'
            });
        
        $('body').append($alert);
        
        $alert.fadeIn(300).delay(3000).fadeOut(300, function() {
            $(this).remove();
        });
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    // Erfolgs-Sound (optional)
    function playSuccessSound() {
        try {
            var audioContext = new (window.AudioContext || window.webkitAudioContext)();
            var oscillator = audioContext.createOscillator();
            var gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (e) {
            // Browser unterstützt Web Audio API nicht
        }
    }
    
    // Drag & Drop für Import-Datei
    var $importCard = $('.retexify-import-card');
    
    $importCard.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('retexify-drag-over');
    });
    
    $importCard.on('dragleave dragend', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('retexify-drag-over');
    });
    
    $importCard.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('retexify-drag-over');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            var file = files[0];
            var extension = file.name.toLowerCase().split('.').pop();
            
            if (extension === 'csv' || extension === 'xlsx') {
                var $fileInput = $('#retexify-import-file')[0];
                var dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                $fileInput.files = dataTransfer.files;
                
                $('#retexify-import-file').trigger('change');
                
                showAlert('Datei erfolgreich ausgewählt: ' + file.name, 'success');
            } else {
                showAlert('Bitte verwenden Sie eine CSV- oder Excel-Datei.', 'warning');
            }
        }
    });
    
    // Keyboard-Shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl+E für Export
        if (e.ctrlKey && e.key === 'e' && !e.shiftKey) {
            e.preventDefault();
            $('#retexify-export-btn').click();
        }
        
        // Ctrl+I für Import (wenn Datei ausgewählt)
        if (e.ctrlKey && e.key === 'i' && !e.shiftKey) {
            e.preventDefault();
            if (!$('#retexify-import-btn').prop('disabled')) {
                $('#retexify-import-btn').click();
            }
        }
        
        // Ctrl+Shift+A für alle auswählen
        if (e.ctrlKey && e.shiftKey && e.key === 'A') {
            e.preventDefault();
            $('.retexify-post-type-checkbox, .retexify-content-checkbox, .retexify-status-checkbox').prop('checked', true);
            updatePreview();
        }
        
        // Ctrl+Shift+D für alle abwählen
        if (e.ctrlKey && e.shiftKey && e.key === 'D') {
            e.preventDefault();
            $('.retexify-post-type-checkbox, .retexify-content-checkbox, .retexify-status-checkbox').prop('checked', false);
            updatePreview();
        }
    });
    
    // Benachrichtigung wenn Seite verlassen wird während eines Prozesses
    window.addEventListener('beforeunload', function(e) {
        if ($('.button:disabled').length > 0) {
            var message = 'Ein Export oder Import läuft noch. Möchten Sie die Seite wirklich verlassen?';
            e.returnValue = message;
            return message;
        }
    });
    
    // Smooth reveal animation für Cards
    $('.retexify-card').each(function(index) {
        $(this).css({
            'opacity': '0',
            'transform': 'translateY(20px)'
        }).delay(index * 150).animate({
            opacity: 1
        }, 400).animate({
            transform: 'translateY(0)'
        }, 400);
    });
    
    // Tooltip für bessere UX (falls jQuery UI verfügbar)
    if ($.fn.tooltip) {
        $('[title]').tooltip({
            position: { my: "center bottom-20", at: "center top" },
            hide: { duration: 200 }
        });
    }
    
    // Auto-Update der Vorschau alle 30 Sekunden (falls sich Inhalte geändert haben)
    setInterval(function() {
        if (!document.hidden && $('.retexify-post-type-checkbox:checked').length > 0) {
            updatePreview();
        }
    }, 30000);
    
    // Abschließende Initialisierung
    console.log('ReTextify Plugin von Imponi erfolgreich geladen');
    
    // Debug-Informationen ausgeben
    if (retexify_ajax.debug) {
        console.log('Debug-Modus aktiviert');
        console.log('AJAX URL:', retexify_ajax.ajax_url);
        console.log('Nonce:', retexify_ajax.nonce);
        
        // Debug-Button hinzufügen
        $('<button>')
            .text('🔍 Debug-Info')
            .addClass('button')
            .css('margin-left', '10px')
            .click(function() {
                showDebugInfo();
            })
            .insertAfter('#retexify-test-btn');
    }
    
    // Debug-Informationen anzeigen
    function showDebugInfo() {
        var info = 'ReTextify Plugin - Debug-Informationen\n\n';
        info += '=== PLUGIN-INFORMATIONEN ===\n';
        info += 'Plugin-Version: ' + (retexify_ajax.version || 'unbekannt') + '\n';
        info += 'WordPress-URL: ' + window.location.origin + '\n';
        info += 'AJAX-URL: ' + retexify_ajax.ajax_url + '\n';
        info += 'Debug-Modus: ' + (retexify_ajax.debug ? 'Aktiv' : 'Inaktiv') + '\n\n';
        
        info += '=== SYSTEM-INFORMATIONEN ===\n';
        info += 'User-Agent: ' + navigator.userAgent + '\n';
        info += 'Bildschirmauflösung: ' + screen.width + 'x' + screen.height + '\n';
        info += 'Browser-Sprache: ' + navigator.language + '\n';
        info += 'Lokale Zeit: ' + new Date().toString() + '\n';
        info += 'Zeitzone: ' + Intl.DateTimeFormat().resolvedOptions().timeZone + '\n\n';
        
        info += '=== BROWSER-SUPPORT ===\n';
        info += 'File API Support: ' + (window.File && window.FileReader ? 'Ja' : 'Nein') + '\n';
        info += 'Drag & Drop Support: ' + ('draggable' in document.createElement('span') ? 'Ja' : 'Nein') + '\n';
        info += 'FormData Support: ' + (window.FormData ? 'Ja' : 'Nein') + '\n';
        info += 'Local Storage Support: ' + (window.localStorage ? 'Ja' : 'Nein') + '\n';
        info += 'Web Audio API Support: ' + (window.AudioContext || window.webkitAudioContext ? 'Ja' : 'Nein') + '\n\n';
        
        info += '=== ERKANNTE PLUGINS ===\n';
        if (retexify_ajax.detected_plugins) {
            for (var plugin in retexify_ajax.detected_plugins) {
                info += '- ' + retexify_ajax.detected_plugins[plugin] + '\n';
            }
        } else {
            info += 'Keine Plugin-Informationen verfügbar\n';
        }
        info += '\n';
        
        info += '=== PAGE BUILDER ===\n';
        if (retexify_ajax.supported_builders) {
            for (var builder in retexify_ajax.supported_builders) {
                info += '- ' + retexify_ajax.supported_builders[builder] + '\n';
            }
        } else {
            info += 'Keine Page Builder erkannt\n';
        }
        info += '\n';
        
        // Aktuelle Auswahl
        var selections = getSelections();
        info += '=== AKTUELLE AUSWAHL ===\n';
        info += 'Post-Typen: ' + selections.post_types.join(', ') + '\n';
        info += 'Inhaltsfelder: ' + selections.content_types.join(', ') + '\n';
        info += 'Status: ' + selections.post_status.join(', ') + '\n\n';
        
        info += '=== PERFORMANCE ===\n';
        if (window.performance && window.performance.memory) {
            info += 'Heap-Größe: ' + Math.round(window.performance.memory.totalJSHeapSize / 1024 / 1024) + ' MB\n';
            info += 'Genutzter Heap: ' + Math.round(window.performance.memory.usedJSHeapSize / 1024 / 1024) + ' MB\n';
        }
        info += 'Connection Type: ' + (navigator.connection ? navigator.connection.effectiveType : 'unbekannt') + '\n';
        
        // In neuem Fenster oder Alert anzeigen
        if (confirm('Debug-Informationen in neuem Fenster öffnen?\n\n(Abbrechen = In Zwischenablage kopieren)')) {
            var debugWindow = window.open('', 'debug', 'width=700,height=600,scrollbars=yes,resizable=yes');
            debugWindow.document.write('<html><head><title>ReTextify - Debug</title></head><body>');
            debugWindow.document.write('<pre style="font-family: monospace; padding: 20px; font-size: 12px; line-height: 1.4;">' + info + '</pre>');
            debugWindow.document.write('</body></html>');
        } else {
            // In Zwischenablage kopieren (falls möglich)
            if (navigator.clipboard) {
                navigator.clipboard.writeText(info).then(function() {
                    showAlert('Debug-Informationen in Zwischenablage kopiert', 'success');
                });
            } else {
                // Fallback für ältere Browser
                var textArea = document.createElement('textarea');
                textArea.value = info;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    showAlert('Debug-Informationen in Zwischenablage kopiert', 'success');
                } catch (err) {
                    alert(info);
                }
                document.body.removeChild(textArea);
            }
        }
    }
    
});

// CSS für Drag & Drop visuelles Feedback
const dragDropCSS = `
    .retexify-drag-over {
        border-color: #2271b1 !important;
        box-shadow: 0 0 15px rgba(34, 113, 177, 0.3) !important;
        transform: scale(1.02);
        transition: all 0.2s ease;
    }
    
    .retexify-drag-over .retexify-card-header {
        background: #e3f2fd !important;
    }
`;

// CSS dynamisch hinzufügen
if (typeof document !== 'undefined') {
    const style = document.createElement('style');
    style.textContent = dragDropCSS;
    document.head.appendChild(style);
}