<?php

namespace BookStack\Http\Controllers;

use BookStack\Uploads\WordImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class WordPasteController extends Controller
{
    public function images(Request $request, WordImportService $importer)
    {
        abort_unless($this->isSignedIn(), 403);
        $this->checkPermission('image-create-all');
        $this->validate($request, ['clipboard' => 'required|file|max:20480']);
        try {
            $html = $importer->convertClipboard($request->file('clipboard'));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['clipboard' => $exception->getMessage()]);
        }
        return response()->json(['html' => $html]);
    }
}
