<?php

namespace App\Http\Controllers;

use App\Models\Memo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoAudioController extends Controller
{
    public function __invoke(Request $request, Memo $memo): StreamedResponse
    {
        Gate::authorize('view', $memo);

        $disk = Storage::disk($memo->disk);
        abort_unless($disk->exists($memo->disk_path), 404);

        $filename = 'memo-'.$memo->id.'.'.pathinfo($memo->disk_path, PATHINFO_EXTENSION);

        if ($request->boolean('download')) {
            return $disk->download($memo->disk_path, $filename, [
                'Content-Type' => $memo->mime,
            ]);
        }

        return $disk->response($memo->disk_path, $filename, [
            'Content-Type' => $memo->mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
