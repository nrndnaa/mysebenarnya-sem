@extends('layouts.layout')
@section('page-name', 'Assigned Inquiries')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/Module3/agency-inquiry-list.css') }}">
    <style>
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 6px;
        }
        .badge-pending   { background-color: #fff3cd; color: #856404; }
        .badge-accepted  { background-color: #d1e7dd; color: #0f5132; }
        .badge-rejected  { background-color: #f8d7da; color: #842029; }
    </style>
@endpush
@section('content')
    <h2><i class="fas fa-tasks"></i> Assigned Inquiries</h2>
    <p>Below are the inquiries assigned to your agency for investigation and verification:</p>
    <div class="inquiry-grid">
        @forelse($assignments as $assign)
           @php
    $status = $assign->progress?->VerificationStatus ?? null;

    if ($status === 'ACCEPTED' || $status === 'Verified as True') {
        $badgeClass = 'badge-accepted';
        $badgeText  = 'Accepted';
        $badgeIcon  = 'fa-check-circle';
    } elseif ($status === 'REJECTED' || $status === 'Identified as Fake') {
        $badgeClass = 'badge-rejected';
        $badgeText  = 'Rejected';
        $badgeIcon  = 'fa-times-circle';
    } else {
        $badgeClass = 'badge-pending';
        $badgeText  = 'Pending Review';
        $badgeIcon  = 'fa-clock';
    }
@endphp
            <div class="inquiry-card" onclick="window.location='{{ route('agency.inquiry.details', $assign->AssignmentID) }}'">
                <div class="inquiry-card-header">
                    <h4>{{ $assign->inquiry?->InquiryTitle ?? '-' }}</h4>
                    <span class="status-badge {{ $badgeClass }}">
                        <i class="fas {{ $badgeIcon }}"></i> {{ $badgeText }}
                    </span>
                    <div class="inquiry-card-meta">
                        <span><i class="fas fa-calendar"></i> {{ \Carbon\Carbon::parse($assign->AssignDate)->format('Y-m-d') }}</span>
                        <span><i class="fas fa-user"></i> {{ $assign->agency->AgencyName }}</span>
                    </div>
                </div>
                <div class="inquiry-card-body">
                    <p><strong>ID:</strong> {{ $assign->InquiryID }}</p>
                </div>
            </div>
        @empty
            <p>No assigned inquiries found.</p>
        @endforelse
    </div>
@endsection