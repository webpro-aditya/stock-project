@if (session('error'))
<div class="alert alert-danger alert-dismissible" id="alert-danger" role="alert">
    <span class="alert-text">
        {{ session('error') }}
    </span>
    <button type="button" class="close btn btn-flat" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
</div>
@endif

@if(session('success'))
    <div class="alert alert-success alert-dismissible" id="alert-success" role="alert">
        <span class="alert-text">
            {{ session('success') }}
        </span>
        <button type="button" class="close btn btn-flat" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
@endif

{{--
@if($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
--}}

<script>
document.addEventListener('click', function(e){

    const btn = e.target.closest('[data-bs-dismiss="alert"]');

    if(btn){
        const alert = btn.closest('.alert');

        alert.style.transition = "opacity .4s";
        alert.style.opacity = "0";

        setTimeout(() => alert.remove(), 400);
    }

});
</script>
