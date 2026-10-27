<?php

namespace App\Enums;

enum UserRole: string
{
    case Member = 'member';
    case Staff = 'staff';
    case Admin = 'admin';
}
