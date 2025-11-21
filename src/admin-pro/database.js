window.WPConsentDeleteLogs = window.WPConsentDeleteLogs || (
    function (document, window, $) {
        const app = {
			/**
			 * Initialize
			 */
			init() {
				this.bindEvents();
			},

			/**
			 * Bind event handlers
			 */
			bindEvents() {
				$(document).on('click', '#wpconsent-delete-consent-logs', (e) => {
					this.confirmDelete(e);
				});
			},

			/**
			 * Show confirmation dialog
			 *
			 * @param {Event} e Click event
			 */
			confirmDelete(e) {
				e.preventDefault();

				const period = $('#delete_consent_period').val();
				const periodText = $('#delete_consent_period option:selected').text();

				if (!period) {
					$.alert({
						title: wpconsent.error || 'Error',
						content: wpconsent.delete_logs_period_error || 'Please select a time period',
						type: 'red'
					});
					return;
				}

				$.confirm({
					title: wpconsent.delete_logs_title || 'Delete Old Logs?',
					content: wpconsent.delete_logs_message.replace('%PERIOD%', periodText) ||
							`This will permanently delete all logs older than ${periodText}. This action cannot be undone. We recommend exporting your logs before deletion.`,
					type: 'red',
					buttons: {
						confirm: {
							text: wpconsent.delete_logs_button || 'Delete Logs',
							btnClass: 'btn-confirm',
							action: () => {
								this.startDeletion(period);
							}
						},
						cancel: {
							text: wpconsent.cancel_button || 'Cancel',
							btnClass: '',
						}
					}
				});
			},

			/**
			 * Start deletion process
			 *
			 * @param {string} period Time period to delete
			 */
			startDeletion(period) {
				// Show progress modal
				WPConsentConfirm.show_please_wait(
					wpconsent.delete_logs_deleting || 'Deleting consent logs...',
					true
				);

				// Start deletion
				$.post(ajaxurl, {
					action: 'wpconsent_delete_roc_start',
					nonce: wpconsent.nonce,
					period: period
				})
				.done((response) => {
					if (response.success) {
						this.processBatches(response.data.request_id, response.data, 1);
					} else {
						this.showError(response.data.message || 'Failed to start deletion');
					}
				})
				.fail((jqXHR) => {
					let errorMsg = 'Failed to start deletion';
					if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
						errorMsg = jqXHR.responseJSON.message;
					} else if (wpconsent.delete_logs_error) {
						errorMsg = wpconsent.delete_logs_error;
					}
					this.showError(errorMsg);
				});
			},

			/**
			 * Process deletion batches recursively
			 *
			 * @param {string} requestId The request ID
			 * @param {Object} data Delete request data
			 * @param {number} currentBatch Current batch number
			 */
			processBatches(requestId, data, currentBatch) {
				$.post(ajaxurl, {
					action: 'wpconsent_delete_roc_batch',
					nonce: wpconsent.nonce,
					request_id: requestId,
					batch_number: currentBatch,
					date_threshold: data.date_threshold
				})
				.done((response) => {
					if (response.success) {
						// Calculate total batches needed
						const totalBatches = Math.ceil(data.total_records / 1000);

						// Update progress
						WPConsentConfirm.update_progress(currentBatch, totalBatches);

						if (!response.data.is_last) {
							// Process next batch
							this.processBatches(requestId, data, currentBatch + 1);
						} else {
							// Deletion complete
							this.showSuccess(response.data.total_deleted);
						}
					} else {
						this.showError(response.data.message || 'Batch processing failed');
					}
				})
				.fail(() => {
					this.showError('Batch processing failed - server error');
				});
			},

			/**
			 * Show success message
			 *
			 * @param {number} totalDeleted Total records deleted
			 */
			showSuccess(totalDeleted) {
				WPConsentConfirm.close();

				$.alert({
					title: wpconsent.delete_logs_success_title || 'Deletion Complete',
					content: wpconsent.delete_logs_success_message.replace('%COUNT%', totalDeleted) ||
							`Successfully deleted ${totalDeleted} consent log records.`,
					type: 'green',
					buttons: {
						ok: {
							text: wpconsent.ok || 'OK',
							action: () => {
								location.reload();
							}
						}
					}
				});
			},

			/**
			 * Show error message
			 *
			 * @param {string} message Error message
			 */
			showError(message) {
				WPConsentConfirm.close();

				$.alert({
					title: wpconsent.error || 'Error',
					content: message,
					type: 'red'
				});
			}
		};

        // Initialize when document is ready
        $(document).ready(() => {
            app.init();
        });

        return app;
    }(document, window, jQuery)
);