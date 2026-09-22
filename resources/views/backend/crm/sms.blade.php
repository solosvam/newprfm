@if($error)
    <div class="alert alert-danger mb-0">{{ $error }}</div>
@elseif(empty($messages))
    <div class="text-center text-muted py-4">Son 30 gün üçün SMS tapılmadı.</div>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Tarix</th>
                <th>Mesaj</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($messages as $message)
                <tr>
                    <td class="text-nowrap">{{ $message['date'] }}</td>
                    <td>{{ $message['message'] }}</td>
                    <td>{{ $message['status'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
