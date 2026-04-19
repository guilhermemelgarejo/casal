<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="h5 mb-0 profile-page-title">Perfil</h2>
            <p class="small text-secondary mb-0 mt-1">Dados da conta, senha e opção de excluir o usuário. Alterações aplicam-se só a você.</p>
        </div>
    </x-slot>

    <div class="py-4 profile-page">
        <div class="container-xxl px-3 px-lg-4">
            <div class="row justify-content-center">
                <div class="col-lg-8 col-xl-7">
                    @include('profile.partials.update-profile-information-form')
                    @include('profile.partials.update-password-form')
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
