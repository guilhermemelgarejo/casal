<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\Couple;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_de_contato_publica_pode_ser_renderizada(): void
    {
        $response = $this->get(route('contact.show'));

        $response->assertOk();
        $response->assertSee('Entre em contato sobre o');
    }

    public function test_visitante_pode_enviar_mensagem_de_contato(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.send'), [
            'name' => 'Visitante Teste',
            'email' => 'visitante@example.com',
            'subject' => 'Dúvida sobre o app',
            'message' => 'Gostaria de saber mais sobre o funcionamento do DuoZen.',
        ]);

        $response->assertRedirect(route('contact.show'));
        $response->assertSessionHas('success');

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
            return $mail->hasTo('guilherme.melgarejo@gmail.com')
                && $mail->name === 'Visitante Teste'
                && $mail->email === 'visitante@example.com'
                && $mail->contactSubject === 'Dúvida sobre o app'
                && $mail->user === null
                && $mail->couple === null;
        });
    }

    public function test_mensagem_de_usuario_logado_inclui_dados_da_conta(): void
    {
        Mail::fake();

        $couple = Couple::factory()->create(['name' => 'Casa Teste']);
        $user = User::factory()->create([
            'name' => 'Usuário Teste',
            'email' => 'usuario@example.com',
            'couple_id' => $couple->id,
        ]);

        $response = $this->actingAs($user)->post(route('contact.send'), [
            'name' => 'Usuário Teste',
            'email' => 'usuario@example.com',
            'subject' => null,
            'message' => 'Mensagem enviada enquanto estou logado na minha conta.',
        ]);

        $response->assertRedirect(route('contact.show'));
        $response->assertSessionHas('success');

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) use ($couple, $user): bool {
            return $mail->hasTo('guilherme.melgarejo@gmail.com')
                && $mail->user?->is($user)
                && $mail->couple?->is($couple);
        });
    }
}
