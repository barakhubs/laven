<?php
namespace App\Http\Controllers;

use App\Models\MemberDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberDocumentController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id) {
        $memberdocuments = MemberDocument::where('member_id', $id)->orderBy('id', 'desc')->get();
        return view('backend.member_documents.list', compact('memberdocuments', 'id'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id, Request $request) {
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.member_documents.modal.create', compact('id'));
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required',
            'name'      => 'required',
            'document'  => 'required|mimes:png,jpg,jpeg,pdf|max:10000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('member_documents.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $document = '';
        if ($request->hasfile('document')) {
            $file     = $request->file('document');
            $document = time() . uniqid() . '-' . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/documents/", $document);
        }

        $memberdocument            = new MemberDocument();
        $memberdocument->member_id = $request->input('member_id');
        $memberdocument->name      = $request->input('name');
        $memberdocument->document  = $document;

        $memberdocument->save();

        //Prefix Output
        $memberdocument->document = '<a target="_blank" href="' . asset('public/uploads/documents/' . $memberdocument->document) . '">' . $memberdocument->document . '</a>';

        if (! $request->ajax()) {
            return redirect()->route('member_documents.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $memberdocument, 'table' => '#member_documents_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $memberdocument = MemberDocument::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.member_documents.modal.edit', compact('memberdocument', 'id'));
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
            'member_id' => 'required',
            'name'      => 'required',
            'document'  => 'nullable|mimes:png,jpg,jpeg,pdf|max:10000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('member_documents.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('document')) {
            $file     = $request->file('document');
            $document = time() . uniqid() . '-' . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/documents/", $document);
        }

        $memberdocument            = MemberDocument::find($id);
        $memberdocument->member_id = $request->input('member_id');
        $memberdocument->name      = $request->input('name');
        if ($request->hasfile('document')) {
            $memberdocument->document = $document;
        }

        $memberdocument->save();

        //Prefix Output
        $memberdocument->document = '<a target="_blank" href="' . asset('public/uploads/documents/' . $memberdocument->document) . '">' . $memberdocument->document . '</a>';

        if (! $request->ajax()) {
            return back()->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $memberdocument, 'table' => '#member_documents_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $document = MemberDocument::find($id);
        if (file_exists(public_path('uploads/documents/' . $document->document))) {
            unlink(public_path('uploads/documents/' . $document->document));
        }
        $document->delete();
        return back()->with('success', _lang('Deleted Successfully'));
    }
}