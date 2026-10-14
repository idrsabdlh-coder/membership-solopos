<?php
// app/Enums/EditionStatus.php
namespace App\Enums;

enum EditionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}