<?php

namespace App\Http\Controllers;

use App\Models\EmailSMSTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationTemplateController extends Controller {
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $emailtemplates = EmailSMSTemplate::all();
        return view('backend.administration.notification_template.list', compact('emailtemplates'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id) {
        $emailtemplate = EmailSMSTemplate::find($id);

        if (!$request->ajax()) {
            return view('backend.administration.notification_template.view', compact('emailtemplate', 'id'));
        } else {
            return view('backend.administration.notification_template.modal.view', compact('emailtemplate', 'id'));
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $emailtemplate = EmailSMSTemplate::find($id);

        if (!$request->ajax()) {
            return view('backend.administration.notification_template.edit', compact('emailtemplate', 'id'));
        } else {
            return view('backend.administration.notification_template.modal.edit', compact('emailtemplate', 'id'));
        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {

        $validator = Validator::make($request->all(), [
            'name'                => 'required',
            'subject'             => 'required',
            'email_body'          => 'required_if:email_status,1',
            'sms_body'            => 'required_if:sms_status,1',
            'notification_body'   => 'required_if:notification_status,1',
        ],[
            'email_body.required_if' => _lang('Email body is required'),
            'sms_body.required_if' => _lang('SMS text is required'),
            'notification_body.required_if' => _lang('Notification text is required'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('notification_templates.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $emailtemplate                      = EmailSMSTemplate::find($id);
        $emailtemplate->name                = $request->input('name');
        $emailtemplate->subject             = $request->input('subject');
        $emailtemplate->email_body          = $request->input('email_body');
        $emailtemplate->sms_body            = $request->input('sms_body');
        $emailtemplate->notification_body   = $request->input('notification_body');
        $emailtemplate->email_status        = isset($request->email_status) ? $request->input('email_status') : 0;
        $emailtemplate->sms_status          = isset($request->sms_status) ? $request->input('sms_status') : 0;
        $emailtemplate->notification_status = isset($request->notification_status) ? $request->input('notification_status'): 0;

        $emailtemplate->save();

        if (!$request->ajax()) {
            return redirect()->route('notification_templates.index')->with('success', _lang('Updated successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated successfully'), 'data' => $emailtemplate]);
        }
    }
}
