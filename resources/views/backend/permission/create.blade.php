@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        <form method="post" id="permissions" class="validate" autocomplete="off" action="{{ route('permission.store') }}">
            @csrf
			<div class="row">
                <div class="col-md-12">
                    <div class="card">
						<div class="card-header panel-title">
							{{ _lang('Access Control') }}
						</div>
                        <div class="card-body">
                            <div class="col-12">
                                <div class="form-group">
                                    <label class="control-label">{{ _lang('Select Role') }}</label>
                                    <select class="form-control select2" id="user_role" name="role_id" required>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        {{ create_option("roles", "id", "name", $role_id) }}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

			@if($role_id != '')

            <div class="card mt-4">
				<div class="card-header panel-title">
					{{ _lang('Permission Control') }}
				</div>
                <div class="card-body">
                    <div class="row">
						<div class="col-md-12">
							<div id="accordion">
								@php $i = 1; @endphp
								@foreach($permission as $key => $val)
								<div class="card mb-3">
									<div class="card-header">
										<h5>
											<a class="card-link" data-toggle="collapse"
												href="#collapse-{{ explode("\\",$key)[3] }}">
												<i class="fas fa-long-arrow-alt-right"></i>
												
												@php
												$string = str_replace("Controller", "", explode("\\",$key)[3]);
												$array = preg_split('/(?=[A-Z])/', $string);
 												$moduleName = implode(' ', $array);
												@endphp

												{{ $moduleName }}
											</a>
										</h5>
									</div>
									<div id="collapse-{{ explode("\\",$key)[3] }}" class="collapse">
										<div class="card-body">
											<table class="table">
												@foreach($val as $name => $url)
												<tr>
													<td>
														<div class="checkbox">
															<div class="custom-control custom-checkbox">
																<input type="checkbox" class="custom-control-input"
																	name="permissions[]" value="{{ $name }}"
																	id="customCheck{{ $i + 1 }}"
																	{{ array_search($name,$permission_list) !== FALSE ? "checked" : "" }}>
																<label class="custom-control-label"
																	for="customCheck{{ $i + 1 }}">{{ str_replace("index", "list", $name) }}</label>
															</div>
														</div>
													</td>
												</tr>
												@php $i++; @endphp
												@endforeach
											</table>
										</div>
									</div>
								</div>
								@endforeach
							</div>
						</div>

                        <div class="col-md-12 mt-4">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Permission') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
			@endif
        </form>
    </div>
</div>
@endsection