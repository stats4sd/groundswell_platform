<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class DownloadController
{

    public function download(string $file)
    {
        // $file === filepath

        return Storage::download($file);

    }
}
