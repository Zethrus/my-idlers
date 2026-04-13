<tr>
    <td>{{ $displayName }}</td>
    <td>
        <span class="badge bg-secondary">{{ $typeLabel }}</span>
    </td>
    <td>{{ $dateDisplay }}</td>
    <td>{{ $service->price }} {{ $service->currency }} {{ \App\Process::paymentTermIntToString($service->term) }}</td>
    <td class="text-center">
        <a href="{{ route($routeName, $service->service_id) }}" class="btn btn-sm btn-outline-primary">View</a>
    </td>
</tr>