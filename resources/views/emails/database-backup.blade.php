<x-mail::message>
# Relatório de Backup do Banco de Dados

O backup do banco de dados **{{ $databaseName }}** foi gerado com sucesso, criptografado com senha e enviado para a pasta do Google Drive.

<x-mail::panel>
**Detalhes do Backup:**
- **Data e Hora:** {{ $createdAt }}
- **Arquivo:** `{{ $fileName }}`
- **Tamanho:** {{ $fileSize }}
- **Criptografia:** AES-256 (ZIP protegido por senha)
</x-mail::panel>

<x-mail::button :url="$driveLink">
Acessar Backup no Google Drive
</x-mail::button>

---

### Instruções para Restauração:
1. Faça o download do arquivo `.zip` através do botão acima.
2. Descompacte com o **7-Zip** ou **WinRAR** informando a sua senha mestra configurada.
3. Importe o arquivo `.sql` resultante no **phpMyAdmin** da Hostinger ou no seu MySQL local.

Obrigado,<br>
{{ config('app.name') }}
</x-mail::message>
