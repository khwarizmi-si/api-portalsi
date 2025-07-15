<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MediaController extends Controller
{
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'media' => 'required|file|mimes:jpeg,png,jpg,mp4,mov,avi|max:20480', // max 20MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $path = $request->file('media')->store('uploads', 'public');
        $url = asset('storage/' . $path);

        return response()->json([
            'message' => 'Media uploaded successfully',
            'url' => $url
        ]);
    }
}
