<x-app-layout>
    <x-slot name="header">
        <h2 class="h5 mb-0">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="container-xxl px-3 px-lg-4">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-6">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body p-4">
                            @include('profile.partials.update-profile-information-form')
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-body p-4">
                            @include('profile.partials.update-password-form')
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-body p-4">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
