@extends('layouts.app')
@section('title', 'Profile Saya')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card p-4">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card p-4 mt-3">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card p-4">
            @include('profile.partials.two-factor', [
                'enabled' => auth()->user()->two_factor_confirmed_at !== null,
                'remaining' => app(\App\Services\TwoFactorManager::class)->remainingRecoveryCodes(auth()->user()),
            ])
        </div>
    </div>
</div>
@endsection
