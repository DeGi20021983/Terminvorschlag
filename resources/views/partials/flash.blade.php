@if (session('status'))
    <div class="alert alert--success" role="status">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert--error" role="alert">
        <p><strong>Die Aktion konnte nicht ausgeführt werden:</strong></p>
        <ul>
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
