<?php

namespace App\Enums;

enum EpaperDownloadStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Downloaded = 'downloaded';
    case Failed = 'failed';
    case Expired = 'expired';
}
