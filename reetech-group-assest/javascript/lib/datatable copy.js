/**
 * Generic DataTables Initialization with WordPress REST API
 */
class WPDataTable {
    constructor(tableId, config) {
        this.tableId = tableId;
        this.config = config;
        this.table = null;
        this.init();
    }

    init() {
        const self = this;
        const defaultConfig = {
            processing: true,
            serverSide: true,
            ajax: {
                url:'http://localhost/wordpress1/wp-json/reetech-group/v1/invoices-summary', //wpApiSettings.root + this.config.endpoint,
                type: 'GET',
                dataType: 'json',
                beforeSend: function(xhr) {
                    let a=0;
                   // xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                data: function(d) {
                    // Map DataTables parameters to WordPress API
                    return {
                        page: Math.ceil(d.start / d.length) + 1,
                        per_page: d.length,
                        search: d.search.value,
                        orderby: d.columns[d.order[0].column].data,
                        order: d.order[0].dir,
                        ...self.config.extraParams
                    };
                },
                dataSrc: function(json) {
                    // Map WordPress response to DataTables format
                    return {
                        draw: json.pagination.current_page,
                        recordsTotal: json.pagination.total_items,
                        recordsFiltered: json.pagination.total_items,
                        data: json.data
                    };
                }
            },
            columns: this.config.columns,
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            responsive: true,
            language: {
                emptyTable: "No data available in table",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)",
                lengthMenu: "Show _MENU_ entries",
                loadingRecords: "Loading...",
                processing: "Processing...",
                search: "Search:",
                zeroRecords: "No matching records found",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            initComplete: function() {
                // Apply theme after initialization
                self.applyTheme($('#theme-selector').val());
            }
        };

        this.table = $('#' + this.tableId).DataTable({
            ...defaultConfig,
            ...this.config.customConfig
        });

        this.bindEvents();
    }

    bindEvents() {
        const self = this;
        
        // Theme selector change
        $('#theme-selector').on('change', function() {
            self.applyTheme($(this).val());
        });

        // Refresh button
        $(document).on('click', '.refresh-btn', function() {
            self.table.ajax.reload();
        });
    }

    applyTheme(theme) {
        // Remove all theme classes
        $('#' + this.tableId).removeClass(
            'table-dark table-striped table-bordered'
        ).closest('.card').removeClass(
            'bg-dark text-white'
        );

        // Apply selected theme
        switch(theme) {
            case 'bootstrap-dark':
                $('#' + this.tableId).addClass('table-dark');
                $('#' + this.tableId).closest('.card').addClass('bg-dark text-white');
                break;
            case 'bootstrap-material':
                $('#' + this.tableId).addClass('table-striped table-bordered');
                break;
            default:
                // Default Bootstrap theme
                break;
        }
    }

    static create(tableId, config) {
        return new WPDataTable(tableId, config);
    }
}

/**
 * Invoice Specific Configuration
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the invoices table
    const invoiceTable = WPDataTable.create('invoices-table', {
        endpoint: 'reetech-group/v1/invoices-summary',
        columns: [
            { data: 'invoice_number', className: 'fw-bold' },
            { data: 'customer' },
            { data: 'date' },
            { 
                data: 'total',
                render: function(data, type, row) {
                    return type === 'display' ? 
                        `<span class="badge bg-primary">$${data}</span>` : 
                        data;
                }
            },
            { 
                data: 'balance',
                render: function(data, type, row) {
                    const badgeClass = parseFloat(data) > 0 ? 'bg-danger' : 'bg-success';
                    return type === 'display' ? 
                        `<span class="badge ${badgeClass}">$${data}</span>` : 
                        data;
                }
            },
            { 
                data: 'status',
                render: function(data, type, row) {
                    const statusClass = {
                        'paid': 'success',
                        'pending': 'warning',
                        'partial': 'info',
                        'overdue': 'danger'
                    }[data] || 'secondary';
                    return type === 'display' ? 
                        `<span class="badge bg-${statusClass}">${data.toUpperCase()}</span>` : 
                        data;
                }
            },
            {
                data: 'id',
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-primary view-btn" data-id="${data}">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-secondary edit-btn" data-id="${data}">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        customConfig: {
            order: [[2, 'desc']] // Default sort by date descending
        }
    });

    // Handle row actions
    $('#invoices-table').on('click', '.view-btn', function() {
        const invoiceId = $(this).data('id');
        // Implement view functionality
        console.log('View invoice:', invoiceId);
    });

    $('#invoices-table').on('click', '.edit-btn', function() {
        const invoiceId = $(this).data('id');
        // Implement edit functionality
        console.log('Edit invoice:', invoiceId);
    });
});