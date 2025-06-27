@component('mail::message')
UNILAB
## Acesso à página do candidato

Novo pedido de criação de acesso à área do candidato do sistema Uniselec/seleções UNILAB.


@component('mail::button', ['url' => $resetUrl])
Definir Senha
@endcomponent

Caso tenha dificuldades com o botão "definir senha", pode copiar e colar o link abaixo diretamente no seu navegador:
[{{ $resetUrl }}]({{ $resetUrl }})

Caso desconheça ou não tenha solicitado esta operação, ignore por favor este email ou entre em contato conosco, através dos nossos canais de contato oficiais.

Por favor, não responda a este email. O mesmo foi enviado através de um endereço "No-Reply", não sendo as respostas monitoradas.

Obrigado,<br>
UNILAB
@endcomponent