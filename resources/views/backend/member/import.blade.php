@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <form method="post" class="validate" autocomplete="off" action="{{ route('members.import') }}" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <span class="panel-title">{{ _lang('Import Members') }}</span>
                        </div>
                        <div class="card-body">
                            @csrf

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Upload XLSX File') }}</label>
                                        <input type="file" class="dropify" name="file" data-allowed-file-extensions="xlsx" required>
                                    </div>
                                </div>

                                <div class="col-md-12 mt-2">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary"><i class="ti-import mr-2"></i>{{ _lang('Import Members') }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <span class="panel-title">{{ _lang('Instructions') }}</span>
                        </div>
                        <div class="card-body">
                            <ol class="pl-3">
                                <li>{{ _lang('Only XLSX file are allowed') }}</li>
                                <li>{{ _lang('First row need to keep blank or use for column name only') }}</li>
                                <li>{{ _lang('Required field must exists otherwise entire row will be ignore') }}</li>
                                <li><a href="{{ asset('public/import_sample/members.xlsx') }}">{{ _lang('Download Sample File') }}</a></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection