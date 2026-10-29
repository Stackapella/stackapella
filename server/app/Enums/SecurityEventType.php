<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a row in security_events records. Later milestones add theirs (sign_up, share, report,
 * payment_failed...).
 */
enum SecurityEventType: string
{
    case AdminSignIn = 'admin_sign_in';
    case SignUp = 'sign_up';
    case SignIn = 'sign_in';
    case PasswordReset = 'password_reset';
    case EmailChanged = 'email_changed';
    case AccountDeleted = 'account_deleted';
    case SignInRefused = 'sign_in_refused';
    case SecondFactorFailed = 'second_factor_failed';
    case SecondFactorLocked = 'second_factor_locked';
    case RateLimited = 'rate_limited';
    case Blocked = 'blocked';
    case TrialRefused = 'trial_refused';
    case PaymentFailed = 'payment_failed';
    case Share = 'share';
    case Report = 'report';

    public function label(): string
    {
        return __('enums.security_event_type.'.$this->value);
    }
}
