@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-flex align-items-center">
				<span class="header-title">{{ _lang('Database Backups') }}</span>
				<a class="btn btn-primary btn-xs ml-auto" href="{{ route('database_backups.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Create New Backup') }}</a>
			</div>
			<div class="card-body">
				<table id="database_backups_table" class="table table-bordered data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Created At') }}</th>
						    <th>{{ _lang('File') }}</th>
							<th>{{ _lang('Created By') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($databasebackups as $databasebackup)
					    <tr data-id="row_{{ $databasebackup->id }}">
							<td class='created_at'>{{ $databasebackup->created_at }}</td>
							<td class='file'>{{ $databasebackup->file }}</td>
							<td class='user_id'>{{ $databasebackup->created_by->name }}</td>

							<td class="text-center">
								<span class="dropdown">
									<button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									{{ _lang('Action') }}

									</button>
									<form action="{{ route('database_backups.destroy_database_backup', $databasebackup['id']) }}" method="post">
										@csrf
										<input name="_method" type="hidden" value="DELETE">

										<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
											<a href="{{ route('database_backups.download', $databasebackup['id']) }}" class="dropdown-item dropdown-view"><i class="ti-download"></i>&nbsp;{{ _lang('Download') }}</a>
											<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
										</div>
									</form>
								</span>
							</td>
					    </tr>
					    @endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection