@component('mail::message')
# ¡Hola, {{ $name }}!

Te enviamos este correo para **verificar tu cuenta** en **{{ $appName }}**.

Hacé clic en el botón de abajo para activar tu cuenta y empezar a operar:

@component('mail::button', ['url' => $url, 'color' => 'primary'])
Verificar mi cuenta
@endcomponent

Si no podés hacer clic en el botón, copiá y pegá este enlace en tu navegador.

Si **no fuiste vos** quien creó esta cuenta, podés ignorar este correo sin problema.

Gracias por confiar en nosotros,<br>
El equipo de **{{ $appName }}**

@slot('subcopy')
@component('mail::subcopy')
Si no podés hacer clic en el botón, copiá y pegá este enlace en tu navegador: [{{ $url }}]({{ $url }})
@endcomponent
@endslot
@endcomponent
