<div class="space-y-3">
    @if(session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif
    @if(session('error'))<x-alert type="danger">{{ session('error') }}</x-alert>@endif
    @if(session('warning'))<x-alert type="warning">{{ session('warning') }}</x-alert>@endif
    @if(session('status'))<x-alert type="info">{{ session('status') }}</x-alert>@endif
    @if($errors->any())
        <x-alert type="danger" title="Please check the form">
            <ul class="mt-1 list-disc pl-5">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </x-alert>
    @endif
</div>
