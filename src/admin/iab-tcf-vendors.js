/**
 * IAB TCF Vendors Management JavaScript
 */

// Global variables for vendor management
let allVendors = [];
let filteredVendors = [];
let currentPage = 1;
let itemsPerPage = 50;
let currentSearchTerm = '';
let currentStatusFilter = '';
let currentSortOrder = 'name_asc';

document.addEventListener('DOMContentLoaded', function() {
    const vendorContainer = document.querySelector('.wpconsent-iab-tcf-vendors');

    if (!vendorContainer) {
        return;
    }

    // Get items per page from data attribute
    itemsPerPage = parseInt(vendorContainer.getAttribute('data-per-page')) || 50;

    // Initialize vendor management functionality
    initVendorData();
    initVendorSearch();
    initVendorFilters();
    initVendorSelection();
    initVendorDetailsToggle();
    initVendorPagination();

    // Initialize publisher declarations collapsible sections
    initPublisherDeclarationsToggle();
});

/**
 * Initialize vendor data from DOM
 */
function initVendorData() {
    const vendorItems = document.querySelectorAll('.wpconsent-vendor-item');
    allVendors = Array.from(vendorItems);
    filteredVendors = [...allVendors];

    // Initial pagination setup
    updatePagination();
    updateDisplay();
    updateResultsInfo();
}

/**
 * Initialize vendor search functionality
 */
function initVendorSearch() {
    const searchInput = document.getElementById('vendor-search');
    const searchBtn = document.getElementById('vendor-search-btn');
    const clearBtn = document.getElementById('vendor-clear-search');

    if (!searchInput) return;

    // Live search with debouncing
    let searchTimeout;

    function performLiveSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            currentSearchTerm = searchInput.value.trim().toLowerCase();
            currentPage = 1; // Reset to first page
            applyFiltersAndPagination();
        }, 300); // Debounce for 300ms
    }

    // Handle input changes for live search
    searchInput.addEventListener('input', performLiveSearch);

    // Handle search button click (for accessibility)
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            currentSearchTerm = searchInput.value.trim().toLowerCase();
            currentPage = 1;
            applyFiltersAndPagination();
        });
    }

    // Handle enter key in search input
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            currentSearchTerm = searchInput.value.trim().toLowerCase();
            currentPage = 1;
            applyFiltersAndPagination();
        }
    });

    // Handle clear search button
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            currentSearchTerm = '';
            currentPage = 1;
            applyFiltersAndPagination();
        });
    }
}

/**
 * Apply all filters and update pagination
 */
function applyFiltersAndPagination() {
    // Filter vendors
    filteredVendors = allVendors.filter(function(item) {
        // Search filter
        if (currentSearchTerm) {
            const vendorName = item.querySelector('.wpconsent-vendor-name label').textContent.toLowerCase();
            const vendorId = item.getAttribute('data-vendor-id');

            if (!vendorName.includes(currentSearchTerm) && !vendorId.includes(currentSearchTerm)) {
                return false;
            }
        }

        // Status filter
        if (currentStatusFilter) {
            const checkbox = item.querySelector('.wpconsent-vendor-checkbox');
            const isSelected = checkbox && checkbox.checked;

            if ((currentStatusFilter === 'selected' && !isSelected) ||
                (currentStatusFilter === 'not_selected' && isSelected)) {
                return false;
            }
        }

        return true;
    });

    // Sort vendors
    sortVendors();

    // Update pagination and display
    updatePagination();
    updateDisplay();
    updateResultsInfo();
    updateClearButton();
}

/**
 * Sort vendors based on current sort order
 */
function sortVendors() {
    filteredVendors.sort(function(a, b) {
        const aName = a.querySelector('.wpconsent-vendor-name label').textContent;
        const bName = b.querySelector('.wpconsent-vendor-name label').textContent;
        const aId = parseInt(a.getAttribute('data-vendor-id'));
        const bId = parseInt(b.getAttribute('data-vendor-id'));

        switch (currentSortOrder) {
            case 'name_desc':
                return bName.localeCompare(aName);
            case 'id_asc':
                return aId - bId;
            case 'id_desc':
                return bId - aId;
            case 'name_asc':
            default:
                return aName.localeCompare(bName);
        }
    });
}

/**
 * Update the display of vendors based on current page
 */
function updateDisplay() {
    // Hide all vendors first
    allVendors.forEach(function(item) {
        item.style.display = 'none';
    });

    // Calculate pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageVendors = filteredVendors.slice(startIndex, endIndex);

    // Show vendors for current page
    pageVendors.forEach(function(item) {
        item.style.display = '';
    });
}

/**
 * Update pagination controls
 */
function updatePagination() {
    const totalPages = Math.ceil(filteredVendors.length / itemsPerPage);
    const pagination = document.querySelector('.wpconsent-vendor-pagination');
    const prevBtn = document.getElementById('vendor-prev-page');
    const nextBtn = document.getElementById('vendor-next-page');
    const pageInfo = pagination.querySelector('.wpconsent-pagination-info');

    if (totalPages <= 1) {
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';

    // Update page info
    if (pageInfo) {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    // Update previous button
    if (prevBtn) {
        prevBtn.disabled = currentPage <= 1;
    }

    // Update next button
    if (nextBtn) {
        nextBtn.disabled = currentPage >= totalPages;
    }
}

/**
 * Update results info
 */
function updateResultsInfo() {
    const resultsInfo = document.querySelector('.wpconsent-vendor-results-info span');
    const selectedCount = document.querySelectorAll('.wpconsent-vendor-checkbox:checked').length;

    if (resultsInfo) {
        if (currentSearchTerm || currentStatusFilter) {
            resultsInfo.textContent = `Showing ${filteredVendors.length} vendors (${selectedCount} selected)`;
        } else {
            resultsInfo.textContent = `Showing ${allVendors.length} vendors (${selectedCount} selected)`;
        }
    }
}

/**
 * Update clear button visibility
 */
function updateClearButton() {
    const clearBtn = document.getElementById('vendor-clear-search');
    if (clearBtn) {
        clearBtn.style.display = currentSearchTerm ? 'inline-block' : 'none';
    }
}

/**
 * Initialize pagination controls
 */
function initVendorPagination() {
    const prevBtn = document.getElementById('vendor-prev-page');
    const nextBtn = document.getElementById('vendor-next-page');

    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentPage > 1) {
                currentPage--;
                updateDisplay();
                updatePagination();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            const totalPages = Math.ceil(filteredVendors.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                updateDisplay();
                updatePagination();
            }
        });
    }
}

/**
 * Initialize vendor filters functionality
 */
function initVendorFilters() {
    const statusFilter = document.getElementById('vendor-status-filter');
    const sortOrder = document.getElementById('vendor-sort-order');

    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            currentStatusFilter = this.value;
            currentPage = 1; // Reset to first page
            applyFiltersAndPagination();
        });
    }

    if (sortOrder) {
        sortOrder.addEventListener('change', function() {
            currentSortOrder = this.value;
            currentPage = 1; // Reset to first page
            applyFiltersAndPagination();
        });
    }
}

/**
 * Initialize vendor selection functionality
 */
function initVendorSelection() {
    const vendorCheckboxes = document.querySelectorAll('.wpconsent-vendor-checkbox');
    const vendorItems = document.querySelectorAll('.wpconsent-vendor-item');

    vendorCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const vendorItem = this.closest('.wpconsent-vendor-item');
            const vendorId = this.value;

            if (this.checked) {
                vendorItem.classList.add('selected');
                addSelectedVendor(vendorId);
            } else {
                vendorItem.classList.remove('selected');
                removeSelectedVendor(vendorId);
            }

            // Update results info and apply filters if status filter is active
            updateResultsInfo();
            if (currentStatusFilter) {
                applyFiltersAndPagination();
            }
        });
    });

    // Handle clicking on vendor item to toggle selection
    vendorItems.forEach(function(item) {
        const header = item.querySelector('.wpconsent-vendor-header');
        const checkbox = item.querySelector('.wpconsent-vendor-checkbox');

        if (header && checkbox) {
            header.addEventListener('click', function(e) {
                // Don't toggle if clicking on the details toggle button or links
                if (e.target.closest('.wpconsent-vendor-details-toggle') ||
                    e.target.closest('a') ||
                    e.target === checkbox) {
                    return;
                }

                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change'));
            });
        }
    });
}

/**
 * Initialize vendor details toggle functionality
 */
function initVendorDetailsToggle() {
    const toggleButtons = document.querySelectorAll('.wpconsent-vendor-details-toggle');

    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const vendorItem = this.closest('.wpconsent-vendor-item');
            const detailsSection = vendorItem.querySelector('.wpconsent-vendor-details');
            const icon = this.querySelector('.dashicons');

            if (detailsSection.style.display === 'none' || !detailsSection.style.display) {
                detailsSection.style.display = 'block';
                icon.classList.remove('dashicons-arrow-down-alt2');
                icon.classList.add('dashicons-arrow-up-alt2');
                this.setAttribute('aria-expanded', 'true');
            } else {
                detailsSection.style.display = 'none';
                icon.classList.remove('dashicons-arrow-up-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
                this.setAttribute('aria-expanded', 'false');
            }
        });
    });
}

/**
 * Add a vendor to the selected list
 */
function addSelectedVendor(vendorId) {
    // Function kept for consistency but no longer dispatches events
}

/**
 * Remove a vendor from the selected list
 */
function removeSelectedVendor(vendorId) {
    // Function kept for consistency but no longer dispatches events
}

/**
 * Get currently selected vendors from checkboxes
 */
function getSelectedVendors() {
    const selectedCheckboxes = document.querySelectorAll('.wpconsent-vendor-checkbox:checked');
    return Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
}

/**
 * Set selected vendors (for consistency, though not used in current implementation)
 */
function setSelectedVendors(vendorIds) {
    // This function exists for potential future use
    // Currently, the state is managed by the checkboxes themselves
}

/**
 * Update vendor counts in the UI
 */
function updateVendorCounts() {
    const selectedCount = document.querySelectorAll('.wpconsent-vendor-checkbox:checked').length;
    const totalCount = document.querySelectorAll('.wpconsent-vendor-checkbox').length;

    // Update results info if not in search mode
    const resultsInfo = document.querySelector('.wpconsent-vendor-results-info span');
    const searchInput = document.getElementById('vendor-search');

    if (resultsInfo && (!searchInput || !searchInput.value.trim())) {
        resultsInfo.textContent = `Showing ${totalCount} vendors (${selectedCount} selected)`;
    }
}

/**
 * Show save notification
 */
function showSaveNotification(message, type = 'success', autoRemove = true) {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.wpconsent-vendor-save-notification');
    existingNotifications.forEach(notification => notification.remove());

    // Determine background color based on type
    let backgroundColor;
    switch (type) {
        case 'error':
            backgroundColor = '#dc3232';
            break;
        case 'info':
            backgroundColor = '#2271b1';
            break;
        case 'success':
        default:
            backgroundColor = '#46b450';
            break;
    }

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `wpconsent-vendor-save-notification wpconsent-notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 32px;
        right: 20px;
        background: ${backgroundColor};
        color: white;
        padding: 12px 20px;
        border-radius: 4px;
        z-index: 9999;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        transition: opacity 0.3s ease;
    `;

    document.body.appendChild(notification);

    // Auto-remove after specified time if enabled
    if (autoRemove) {
        const removeTime = type === 'info' ? 1500 : 3000; // Shorter time for info messages
        setTimeout(function() {
            notification.style.opacity = '0';
            setTimeout(function() {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, removeTime);
    }
}

/**
 * Initialize publisher declarations collapsible sections functionality
 */
function initPublisherDeclarationsToggle() {
    const toggleButtons = document.querySelectorAll('.wpconsent-section-toggle');

    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const section = this.closest('.wpconsent-publisher-declarations-section');
            const content = section.querySelector('.wpconsent-section-content');
            const isExpanded = this.getAttribute('aria-expanded') === 'true';

            if (isExpanded) {
                // Collapse
                content.style.display = 'none';
                this.setAttribute('aria-expanded', 'false');
            } else {
                // Expand
                content.style.display = 'block';
                this.setAttribute('aria-expanded', 'true');
            }
        });
    });
}