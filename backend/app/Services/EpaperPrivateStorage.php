<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EpaperPrivateStorage
{
    public function disk(): FilesystemAdapter
    {
        $name = config('epaper.storage_disk');
        $visibility = is_string($name) ? config("filesystems.disks.{$name}.visibility") : null;

        if (! is_string($name) || $name === '' || $name === 'public' || $visibility === 'public') {
            throw new RuntimeException('E-Paper storage must use a private filesystem disk.');
        }

        return Storage::disk($name);
    }

    public function copyToTemporaryFile(string $path, string $prefix): string
    {
        $input = $this->disk()->readStream($path);
        if (! is_resource($input)) {
            throw new RuntimeException('Unable to read a private E-Paper file.');
        }

        $temporary = tempnam(sys_get_temp_dir(), $prefix);
        if ($temporary === false) {
            fclose($input);
            throw new RuntimeException('Unable to allocate a temporary E-Paper file.');
        }

        $output = fopen($temporary, 'wb');
        if ($output === false) {
            fclose($input);
            unlink($temporary);
            throw new RuntimeException('Unable to open a temporary E-Paper file.');
        }

        $copied = false;
        try {
            if (stream_copy_to_stream($input, $output) === false) {
                throw new RuntimeException('Unable to copy a private E-Paper file.');
            }
            $copied = true;
        } finally {
            fclose($input);
            fclose($output);
            if (! $copied && file_exists($temporary)) {
                unlink($temporary);
            }
        }

        return $temporary;
    }
}
