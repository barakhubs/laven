@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box">
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">{{ _lang('Dashboard') }}</a></li>
                    <li class="breadcrumb-item active">{{ _lang('Audit Trails') }}</li>
                </ol>
            </div>
            <h4 class="page-title">
                <i class="fas fa-shield-alt text-danger mr-1"></i>
                {{ _lang('Audit Trails') }}
                <span class="badge badge-danger ml-1" style="font-size:11px;">SUPER ADMIN ONLY</span>
            </h4>
        </div>
    </div>
</div>

{{-- Filter Card --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-2 d-flex align-items-center">
                <i class="fas fa-filter text-primary mr-2"></i>
                <strong>{{ _lang('Filter Logs') }}</strong>
                <button class="btn btn-link btn-sm ml-auto" type="button" data-toggle="collapse" data-target="#filterPanel">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="collapse show" id="filterPanel">
                <div class="card-body py-3">
                    <div class="row g-2">
                        <div class="col-md-2 col-sm-6">
                            <label class="small font-weight-bold">{{ _lang('Module') }}</label>
                            <select id="filter_module" class="form-control form-control-sm select2">
                                <option value="">{{ _lang('All Modules') }}</option>
                                @foreach($modules as $mod)
                                    <option value="{{ $mod }}">{{ $mod }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="small font-weight-bold">{{ _lang('Action') }}</label>
                            <select id="filter_action" class="form-control form-control-sm select2">
                                <option value="">{{ _lang('All Actions') }}</option>
                                @foreach($actions as $act)
                                    <option value="{{ $act }}">{{ ucfirst($act) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="small font-weight-bold">{{ _lang('User Type') }}</label>
                            <select id="filter_user_type" class="form-control form-control-sm">
                                <option value="">{{ _lang('All Types') }}</option>
                                <option value="superadmin">Super Admin</option>
                                <option value="admin">Admin</option>
                                <option value="customer">Customer</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="small font-weight-bold">{{ _lang('Date From') }}</label>
                            <input type="date" id="filter_date_from" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="small font-weight-bold">{{ _lang('Date To') }}</label>
                            <input type="date" id="filter_date_to" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="small font-weight-bold">{{ _lang('Search') }}</label>
                            <input type="text" id="filter_search" class="form-control form-control-sm" placeholder="Name, IP, record...">
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button id="btn_apply_filter" class="btn btn-primary btn-sm">
                            <i class="fas fa-search mr-1"></i> {{ _lang('Apply') }}
                        </button>
                        <button id="btn_reset_filter" class="btn btn-secondary btn-sm">
                            <i class="fas fa-undo mr-1"></i> {{ _lang('Reset') }}
                        </button>
                        <a id="btn_export_csv" href="#" class="btn btn-success btn-sm ml-auto">
                            <i class="fas fa-file-csv mr-1"></i> {{ _lang('Export CSV') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="audit_table" class="table table-hover table-striped mb-0" style="width:100%">
                        <thead class="thead-dark">
                            <tr>
                                <th>#</th>
                                <th>{{ _lang('Timestamp') }}</th>
                                <th>{{ _lang('User') }}</th>
                                <th>{{ _lang('Action') }}</th>
                                <th>{{ _lang('Module') }}</th>
                                <th>{{ _lang('Record') }}</th>
                                <th>{{ _lang('Description') }}</th>
                                <th>{{ _lang('IP Address') }}</th>
                                <th>{{ _lang('Detail') }}</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Detail Modal --}}
<div class="modal fade" id="auditDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-clipboard-list mr-2"></i>{{ _lang('Audit Entry Detail') }}</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body" id="auditDetailBody">
                {{-- Filled by JS --}}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ _lang('Close') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
$(function () {

    // ---- DataTable ----
    var table = $('#audit_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("audit_trails.data") }}',
            data: function (d) {
                d.module      = $('#filter_module').val();
                d.action      = $('#filter_action').val();
                d.user_type   = $('#filter_user_type').val();
                d.date_from   = $('#filter_date_from').val();
                d.date_to     = $('#filter_date_to').val();
                d.search_term = $('#filter_search').val();
            }
        },
        columns: [
            { data: 'id',           name: 'id',           width: '50px' },
            { data: 'created_at',   name: 'created_at' },
            { data: 'user_name',    name: 'user_name' },
            { data: 'action_badge', name: 'action',       orderable: false },
            { data: 'module',       name: 'module' },
            { data: 'record_label', name: 'record_label', orderable: false },
            { data: 'description',  name: 'description',  orderable: false },
            { data: 'ip_address',   name: 'ip_address',   orderable: false },
            { data: 'detail_btn',   name: 'detail_btn',   orderable: false, searchable: false },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: { processing: '<div class="spinner-border text-primary" role="status"></div>' },
    });

    // ---- Filter ----
    $('#btn_apply_filter').on('click', function () { table.ajax.reload(); });
    $('#btn_reset_filter').on('click', function () {
        $('#filter_module, #filter_action, #filter_user_type').val('').trigger('change');
        $('#filter_date_from, #filter_date_to, #filter_search').val('');
        table.ajax.reload();
    });

    // ---- Export CSV ----
    $('#btn_export_csv').on('click', function (e) {
        e.preventDefault();
        var params = new URLSearchParams({
            module:      $('#filter_module').val(),
            action:      $('#filter_action').val(),
            user_type:   $('#filter_user_type').val(),
            date_from:   $('#filter_date_from').val(),
            date_to:     $('#filter_date_to').val(),
            search_term: $('#filter_search').val(),
        });
        window.location = '{{ route("audit_trails.export") }}?' + params.toString();
    });

    // ---- Detail Modal ----
    $(document).on('click', '.view-detail-btn', function () {
        var log = $(this).data('log');
        var html = '';

        html += '<table class="table table-bordered table-sm">';
        html += row('ID', log.id);
        html += row('Timestamp', log.created_at);
        html += row('User', log.user_name + ' <small class="text-muted">(' + (log.user_type || '—') + ')</small>');
        html += row('Action', '<span class="badge badge-secondary">' + log.action + '</span>');
        html += row('Module', log.module);
        html += row('Record ID', log.record_id || '—');
        html += row('Record', log.record_label || '—');
        html += row('Description', log.description || '—');
        html += row('IP Address', log.ip_address || '—');
        html += row('URL', '<small style="word-break:break-all">' + (log.url || '—') + '</small>');
        html += row('User Agent', '<small style="word-break:break-all">' + (log.user_agent || '—') + '</small>');
        html += '</table>';

        if (log.old_values || log.new_values) {
            html += '<h6 class="mt-3 font-weight-bold text-primary"><i class="fas fa-exchange-alt mr-1"></i>Changes</h6>';
            html += '<div class="row">';

            if (log.old_values) {
                html += '<div class="col-md-6">'
                      + '<h6 class="text-danger small font-weight-bold">BEFORE</h6>'
                      + '<pre class="bg-light p-2 rounded small" style="max-height:250px;overflow:auto">'
                      + JSON.stringify(log.old_values, null, 2)
                      + '</pre></div>';
            }
            if (log.new_values) {
                html += '<div class="col-md-6">'
                      + '<h6 class="text-success small font-weight-bold">AFTER</h6>'
                      + '<pre class="bg-light p-2 rounded small" style="max-height:250px;overflow:auto">'
                      + JSON.stringify(log.new_values, null, 2)
                      + '</pre></div>';
            }
            html += '</div>';
        }

        $('#auditDetailBody').html(html);
    });

    function row(label, value) {
        return '<tr><th style="width:130px;background:#f8f9fa">' + label + '</th><td>' + (value ?? '—') + '</td></tr>';
    }
});
</script>
@endsection

