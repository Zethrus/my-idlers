@section('title', 'Misc services')
<x-app-layout>
    <div class="container" id="app">
        <div class="page-header">
            <h2 class="page-title">Misc Services</h2>
            <div class="page-actions">
                <x-export-buttons route="export.misc" />
                <a href="{{ route('misc.create') }}" class="btn btn-primary">Add misc service</a>
            </div>
        </div>

        <x-response-alerts></x-response-alerts>

        <div class="content-card">
            <div class="table-responsive">
                <table class="table data-table" id="misc-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th class="text-center">Due In</th>
                            <th class="text-center">Since</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @if(!empty($misc[0]))
                        @foreach($misc as $m)
                        <tr>
                            <td class="fw-medium">{{ $m->name }}</td>
                            <td class="text-nowrap" data-order="{{ $m->price->as_usd ?? $m->price->price }}">
                                {{ $m->price->price }} {{ $m->price->currency }}
                                <small class="text-muted">{{ \App\Process::paymentTermIntToString($m->price->term) }}</small>
                            </td>
                            <td class="text-center text-nowrap" data-order="{{ now()->diffInDays($m->price->next_due_date, false) }}">{{ now()->diffInDays($m->price->next_due_date, false) }}d</td>
                            <td class="text-center text-nowrap" data-order="{{ $m->owned_since ?? '' }}">
                                @if(!is_null($m->owned_since))
                                    {{ $m->owned_since }}
                                @endif
                            </td>
                            <td class="text-center text-nowrap">
                                <div class="action-buttons">
                                    <a href="{{ route('misc.show', $m->id) }}" class="btn btn-sm btn-action" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('misc.edit', $m->id) }}" class="btn btn-sm btn-action" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-action btn-delete" title="Delete"
                                            @click="confirmDeleteModal" id="{{ $m->id }}" data-title="{{ $m->name }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No misc services found</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>

        <x-details-footer></x-details-footer>
        <x-delete-confirm-modal></x-delete-confirm-modal>
    </div>

    <x-modal-delete-script>
        <x-slot name="uri">misc</x-slot>
    </x-modal-delete-script>

    @section('scripts')
    <x-datatable-init selector="#misc-table" :non-orderable="[4]" empty-table="No misc services found" :order="[]" />
    @endsection
</x-app-layout>
