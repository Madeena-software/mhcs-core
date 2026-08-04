<?php

declare(strict_types=1);

namespace App\Modules\Member\Application\Data;

enum InteractiveLoginState: string
{
    case NormalMemberSession = 'normal_member_session';
    case PasswordChangeRequired = 'password_change_required';
    case Failure = 'failure';
}
