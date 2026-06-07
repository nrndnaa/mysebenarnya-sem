@extends('layouts.layout')
@section('page-name', 'Inquiry Details')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/Module3/jurisdiction.css') }}">
@endpush
@section('content')
<div class="container">
    <h1>Inquiry Details</h1>

    <div class="inquiry-details-card">
        <span class="inquiry-id-badge">ID: {{ $assignment->InquiryID }}</span>
        <p><strong>Title:</strong> {{ $assignment->inquiry->InquiryTitle ?? '-' }}</p>
        <p><strong>Description:</strong> {{ $assignment->inquiry->InquiryDescription ?? '-' }}</p>
        <p><strong>Submission Link:</strong>
            <a href="{{ $assignment->inquiry->SubmissionLink ?? '#' }}" target="_blank">
                {{ $assignment->inquiry->SubmissionLink ?? '-' }}
            </a>
        </p>
        <p><strong>Assigned Date:</strong> {{ $assignment->AssignDate }}</p>

        <!-- Jurisdiction Review Section -->
        <hr style="margin: 20px 0; border: 1px solid #e9ecef;">
        <h3 style="color: #333; margin-bottom: 15px; font-size: 20px;"><i class="fas fa-gavel"></i> Jurisdiction Review</h3>
        <p style="color: #666; margin-bottom: 20px;">Does this inquiry fall under your agency's scope? Accept if yes, or reject with reason if no.</p>

        @php
            $progressStatus = $assignment->progress?->VerificationStatus ?? null;
            $isReviewed = in_array($progressStatus, ['ACCEPTED', 'Verified as True', 'REJECTED', 'Identified as Fake']);
        @endphp

        @if(!$isReviewed)
            <div class="action-buttons">
                {{-- Accept Form --}}
                <form method="POST" action="{{ route('agency.inquiry.action', $assignment->AssignmentID) }}" style="display:inline;">
                    @csrf
                    <button type="submit" name="action" value="accept" class="btn-save">
                        <i class="fas fa-check"></i> Accept
                    </button>
                </form>

                {{-- Reject Button --}}
                <button type="button" class="btn-next" onclick="showRejectForm()">
                    <i class="fas fa-times"></i> Reject Inquiry
                </button>
            </div>

            <!-- Reject Form Section -->
            <div class="reject-form-section" id="rejectForm">
                <h4><i class="fas fa-times-circle"></i> Reject Inquiry</h4>
                <form method="POST" action="{{ route('agency.inquiry.action', $assignment->AssignmentID) }}">
                    @csrf
                    <div class="form-group">
                        <label for="jurisdictionComment">Reason for Rejection:</label>
                        <textarea name="jurisdictionComment" id="jurisdictionComment" required placeholder="Please provide a detailed reason for rejecting this inquiry..."></textarea>
                    </div>
                    <div class="reject-form-buttons">
                        <button type="button" class="btn-back" onclick="hideRejectForm()">
                            <i class="fas fa-arrow-left"></i> Cancel
                        </button>
                        <button type="submit" name="action" value="reject" class="btn-save">
                            <i class="fas fa-paper-plane"></i> Submit Rejection
                        </button>
                    </div>
                </form>
            </div>
        @else
            <div style="margin-top:15px; padding: 15px; border-radius: 8px; background: #f8f9fa;">
                @if(in_array($progressStatus, ['ACCEPTED', 'Verified as True']))
                    <span style="color:#0f5132; font-weight:600; font-size:16px;">
                        <i class="fas fa-check-circle"></i> This inquiry has been Accepted.
                    </span>
                @else
                    <span style="color:#842029; font-weight:600; font-size:16px;">
                        <i class="fas fa-times-circle"></i> This inquiry has been Rejected.
                    </span>
                @endif
            </div>
        @endif
    </div>

    <div class="text-center mt-4">
        <a href="{{ route('agency.inquirylist') }}" class="btn-back">Back to List</a>
    </div>
</div>

<script>
function showRejectForm() {
    document.getElementById('rejectForm').classList.add('show');
    document.getElementById('jurisdictionComment').focus();
}
function hideRejectForm() {
    document.getElementById('rejectForm').classList.remove('show');
    document.getElementById('jurisdictionComment').value = '';
}
</script>
@endsection