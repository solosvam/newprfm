@if($refunds->isEmpty())
    <div class="text-center text-muted py-4">Geri ödəmə yoxdur.</div>
@else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>#</th>
                <th>Tarix</th>
                <th>Məbləğ</th>
                <th>Bank əməliyyat ID</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($refunds as $refund)
                <tr>
                    <td>{{ $refund->id }}</td>
                    <td>{{ $refund->created_at?->format('d.m.Y H:i') }}</td>
                    <td class="fw-semibold">{{ number_format((float)$refund->amount, 2) }} ₼</td>
                    <td>{{ $refund->bank_action_id ?: '—' }}</td>
                    <td><span class="badge bg-success">Uğurlu</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
