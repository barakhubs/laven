@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card">

            <div class="card-header d-flex align-items-center">
                <span class="header-title">{{ _lang('User List') }}</span>
                <a class="btn btn-primary btn-xs ml-auto"
                    href="{{ route('users.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
            </div>

            <div class="card-body">
                <table id="users_table" class="table table-bordered data-table">
                    <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th>{{ _lang('Name') }}</th>
                            <th>{{ _lang('Email') }}</th>
                            <th>{{ _lang('User Type') }}</th>
                            <th>{{ _lang('Role') }}</th>
                            <th>{{ _lang('Status') }}</th>
                            <th class="text-center">{{ _lang('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr data-id="row_{{ $user->id }}">
                            <td class='profile_picture text-center'><img
                                    src="{{ profile_picture($user->profile_picture) }}" class="thumb-sm img-thumbnail">
                            </td>
                            <td class='name'>{{ $user->name }}</td>
                            <td class='email'>{{ $user->email }}</td>
                            <td class='user_type'>{{ strtoupper($user->user_type) }}</td>
                            <td class='role_id'>{{ $user->role->name }}</td>
                            <td class='status'>{!! xss_clean(user_status($user->status)) !!}</td>
                            <td class="text-center">
                                <span class="dropdown">
                                    <button class="btn btn-primary dropdown-toggle btn-xs" type="button"
                                        id="dropdownMenuButton" data-toggle="dropdown">
                                        {{ _lang('Action') }}
                                        
                                    </button>
                                    <form action="{{ route('users.destroy', $user['id']) }}" method="post">
                                        {{ csrf_field() }}
                                        <input name="_method" type="hidden" value="DELETE">

                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a href="{{ route('users.edit', $user['id']) }}"        
                                                class="dropdown-item"><i class="ti-pencil-alt"></i>
                                                {{ _lang('Edit') }}</a>
                                            <a href="{{ route('users.show', $user['id']) }}"
                                                data-title="{{ _lang('User Details') }}"
                                                class="dropdown-item ajax-modal"><i class="ti-eye"></i>
                                                {{ _lang('View') }}</a>
                                            <button class="btn-remove dropdown-item" type="submit"><i
                                                    class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
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