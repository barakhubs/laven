<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use DataTables;

class AuditTrailController extends Controller
{
    public function __construct()
    {
        // Extra safety: only superadmin can touch this controller
        $this->middleware(function ($request, $next) {
            if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
                abort(403, 'Only Super Admins can access audit trails.');
            }
            return $next($request);
        });
    }

    /**
     * Display the audit trail index page.
     */
    public function index(Request $request)
    {
        $modules = AuditLog::select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        $actions = AuditLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('backend.audit_trails.index', compact('modules', 'actions'));
    }

    /**
     * Serve DataTables JSON for the audit log list.
     */
    public function get_table_data(Request $request)
    {
        $query = AuditLog::query()->latest();

        // Filters
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search_term')) {
            $query->search($request->search_term);
        }

        return Datatables::eloquent($query)
            ->addColumn('action_badge', function ($log) {
                return '<span class="badge ' . $log->action_badge . ' px-2 py-1">'
                    . strtoupper(htmlspecialchars($log->action)) . '</span>';
            })
            ->editColumn('user_name', function ($log) {
                $type = $log->user_type ? '<small class="text-muted d-block">' . htmlspecialchars($log->user_type) . '</small>' : '';
                return '<strong>' . htmlspecialchars($log->user_name ?? 'System') . '</strong>' . $type;
            })
            ->editColumn('record_label', function ($log) {
                return $log->record_label
                    ? '<span class="text-dark">' . htmlspecialchars($log->record_label) . '</span>'
                    : '<span class="text-muted">—</span>';
            })
            ->editColumn('description', function ($log) {
                return $log->description
                    ? '<span title="' . htmlspecialchars($log->description) . '">'
                        . htmlspecialchars(str($log->description)->limit(60)) . '</span>'
                    : '<span class="text-muted">—</span>';
            })
            ->editColumn('created_at', function ($log) {
                return $log->created_at
                    ? '<span title="' . $log->created_at . '">'
                        . \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i:s') . '</span>'
                    : '—';
            })
            ->addColumn('detail_btn', function ($log) {
                return '<button class="btn btn-xs btn-outline-info view-detail-btn"
                            data-id="' . $log->id . '"
                            data-toggle="modal"
                            data-target="#auditDetailModal"
                            data-log=\'' . htmlspecialchars(json_encode([
                                'id'           => $log->id,
                                'user_name'    => $log->user_name,
                                'user_type'    => $log->user_type,
                                'action'       => $log->action,
                                'module'       => $log->module,
                                'record_id'    => $log->record_id,
                                'record_label' => $log->record_label,
                                'description'  => $log->description,
                                'old_values'   => $log->old_values,
                                'new_values'   => $log->new_values,
                                'ip_address'   => $log->ip_address,
                                'user_agent'   => $log->user_agent,
                                'url'          => $log->url,
                                'created_at'   => optional($log->created_at)->toDateTimeString(),
                            ]), ENT_QUOTES, 'UTF-8') . '\'>
                            <i class="ti-eye"></i> View
                        </button>';
            })
            ->rawColumns(['action_badge', 'user_name', 'record_label', 'description', 'created_at', 'detail_btn'])
            ->make(true);
    }

    /**
     * Export audit logs as CSV (superadmin only).
     */
    public function export(Request $request)
    {
        $query = AuditLog::query()->latest();

        if ($request->filled('module'))      { $query->where('module', $request->module); }
        if ($request->filled('action'))      { $query->where('action', $request->action); }
        if ($request->filled('user_type'))   { $query->where('user_type', $request->user_type); }
        if ($request->filled('date_from'))   { $query->whereDate('created_at', '>=', $request->date_from); }
        if ($request->filled('date_to'))     { $query->whereDate('created_at', '<=', $request->date_to); }
        if ($request->filled('search_term')){ $query->search($request->search_term); }

        $logs = $query->get();

        $filename = 'audit_trails_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'User', 'User Type', 'Action', 'Module',
                'Record ID', 'Record', 'Description',
                'Old Values', 'New Values',
                'IP Address', 'URL', 'Timestamp',
            ]);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->user_name,
                    $log->user_type,
                    $log->action,
                    $log->module,
                    $log->record_id,
                    $log->record_label,
                    $log->description,
                    $log->old_values ? json_encode($log->old_values) : '',
                    $log->new_values ? json_encode($log->new_values) : '',
                    $log->ip_address,
                    $log->url,
                    optional($log->created_at)->toDateTimeString(),
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}

