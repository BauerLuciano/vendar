<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationActivity
{
    protected function getMetadata(): array
    {
        return [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];
    }

    public function handleLogin(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->event('login')
            ->withProperties($this->getMetadata())
            ->log('Inicio de sesión');
    }

    public function handleLogout(Logout $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->event('logout')
            ->withProperties($this->getMetadata())
            ->log('Cierre de sesión');
    }
}
