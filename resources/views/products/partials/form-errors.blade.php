@if($errors->any())
    <div class="alert alert-danger module-alert" role="alert">
        <strong>Revisa los datos del formulario.</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
