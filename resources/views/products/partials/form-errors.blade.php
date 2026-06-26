@if($errors->any())
    <div class="alert alert-danger module-alert" role="alert">
        <strong>{{ $formErrorTitle ?? 'No pudimos guardar el formulario.' }}</strong>
        @isset($formErrorLead)
            <div class="mt-1">{{ $formErrorLead }}</div>
        @endisset
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
