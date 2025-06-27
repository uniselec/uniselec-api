@component('mail::message')
UNILAB
## Acesso à página administrativa do sistema Uniselec

Novo pedido de criação de acesso à área administrativa da área do sistema Uniselec


@component('mail::button', ['url' => $resetUrl])
Redefinir Senha
@endcomponent

Caso tenha dificuldades com o botão “redefinir senha”, pode copiar e colar o link abaixo diretamente no seu browser:
[{{ $resetUrl }}]({{ $resetUrl }})

Caso desconheça ou não tenha solicitado esta operação, ignore por favor este email ou entre em contato conosco, através dos nossos canais de contato oficiais.

Por favor, não responda a este email. O mesmo foi enviado através de um endereço “No-Reply”, não sendo as respostas monitoradas.

Obrigado,<br>
UNILAB
@endcomponent