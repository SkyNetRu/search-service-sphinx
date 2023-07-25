<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Maestroerror;

class Heic extends Controller
{
    public function convertHeic2Jpg(Request $request)
    {
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        $dest = public_path() . '/temp/';
        $file->move($dest.$filename);
        $jpgFilename = str_replace('heic', 'jpg', $filename);

        Maestroerror\HeicToJpg::convert($dest.$file->getClientOriginalName())->saveAs($dest.$jpgFilename);

        $fileUrl = URL::to('/').'temp/'.$jpgFilename;

        return ['result' => true, 'message' => "File converted", 'url' => $fileUrl, 'fileName' => $jpgFilename];
    }
}

