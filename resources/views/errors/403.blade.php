<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido - VendAR</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 h-screen flex items-center justify-center font-sans">
    <div class="text-center p-10 bg-white shadow-2xl rounded-3xl border border-slate-100 max-w-lg">
        <div class="text-8xl mb-6">🚫</div>
        <h1 class="text-4xl font-black text-slate-800 uppercase tracking-tighter mb-2">Acceso Denegado</h1>
        <div class="h-1 w-16 bg-sky-500 mx-auto mb-6"></div>
        
        <p class="text-slate-500 font-medium mb-8">
            Lo sentimos, pero tu perfil de 
            <span class="text-sky-600 font-black uppercase">
                {{ auth()->user()->getRoleNames()->implode(', ') ?: 'Usuario' }}
            </span> 
            no tiene los permisos necesarios para entrar a esta sección.
        </p>

        <a href="/dashboard" class="inline-block bg-slate-900 text-white px-8 py-3 rounded-xl font-bold uppercase text-[10px] tracking-widest hover:bg-sky-600 transition-all shadow-lg">
            Volver al Inicio
        </a>
    </div>
</body>
</html>