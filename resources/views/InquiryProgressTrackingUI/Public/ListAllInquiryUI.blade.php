@extends('layouts.layout')

@section('head')
    <link rel="stylesheet" href="{{ asset('css/module4/public-list.css') }}">
    <style>
        /* Mengemaskan reka bentuk filter wrapper */
        .filter-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border: 1px solid #eaeded;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            font-size: 14px;
            color: #34495e;
        }

        .filter-group input[type="text"],
        .filter-group select {
            height: 38px;          
            padding: 6px 12px;     
            font-size: 14px;       
            border: 1px solid #cbd5e1;
            border-radius: 6px;    
            box-sizing: border-box;
            min-width: 180px;      
            transition: border-color 0.2s;
        }

        .filter-group input[type="text"]:focus,
        .filter-group select:focus {
            border-color: #3498db;
            outline: none;
        }

        .btn-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-reset {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 38px;
            padding: 0 18px;
            background-color: #94a3b8;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            box-sizing: border-box;
            transition: background-color 0.2s;
        }

        .btn-reset:hover {
            background-color: #64748b;
            color: white;
        }

        /* Hiasan Jadual (Table) yang lebih menarik */
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }

        th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            text-align: left;
            padding: 14px 16px;
            border-bottom: 2px solid #e2e8f0;
            font-size: 14px;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 14px;
            vertical-align: middle;
        }

        tr:hover {
            background-color: #f8fafc;
        }

        table td a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }

        table td a:hover {
            text-decoration: underline;
        }

        /* Gaya Hiasan Badge Status Berwarna Semacam Gambar Rujukan */
        .status-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }

        .badge-investigation {
            background-color: #fef9c3; /* Kuning lembut */
            color: #713f12;
        }

        .badge-verified {
            background-color: #dcfce7; /* Hijau lembut */
            color: #14532d;
        }

        .badge-fake {
            background-color: #fee2e2; /* Merah lembut */
            color: #7f1d1d;
        }

        .badge-na {
            background-color: #f1f5f9; /* Kelabu lembut */
            color: #475569;
        }

        .pagination-wrapper {
            margin-top: 25px;
            display: flex;
            flex-direction: column; /* Stack the text and arrows */
            align-items: center;    /* Center them horizontally */
            justify-content: center;
            text-align: center;     /* Center the "Showing..." text */
            width: 100%;            /* Ensure it takes full width */
        }

        /* This fixes the small Laravel arrows from becoming giant */
        .pagination-wrapper svg {
            width: 20px;
            height: 20px;
            display: inline-block;
        }

        /* Space out the "Showing..." text from the numbers */
        .pagination-wrapper nav div:first-child {
            margin-bottom: 10px;
        }
    </style>
@endsection

@section('page-name', 'List All Inquiry')

@section('content')
    <div class="assigned-inquiry-list" style="padding: 20px;">
        <h2 style="color: #1e293b; margin-bottom: 20px; font-weight: 700;">All Inquiries</h2>

        <form method="GET" action="/public/inquiry-list" class="filter-wrapper">
            <div class="filter-group">
                <label for="search">Search:</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Search by Inquiry Title...">
            </div>

            <div class="filter-group">
                <label for="status">Filter by Status:</label>
                <select name="status" id="status">
                    <option value="">-- All --</option>
                    <option value="Under Investigation" {{ request('status') == 'Under Investigation' ? 'selected' : '' }}>Under Investigation</option>
                    <option value="Verified as True" {{ request('status') == 'Verified as True' ? 'selected' : '' }}>Verified as True</option>
                    <option value="Identified as Fake" {{ request('status') == 'Identified as Fake' ? 'selected' : '' }}>Identified as Fake</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="month">Filter by Month:</label>
                <select name="month" id="month">
                    <option value="">-- All Months --</option>
                    @foreach([
                        '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
                        '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
                        '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
                    ] as $value => $label)
                        <option value="{{ $value }}" {{ request('month') == $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label for="sort">Sort By:</label>
                <select name="sort" id="sort">
                    <option value="latest" {{ request('sort') == 'latest' ? 'selected' : '' }}>Latest Submissions (Newest to Oldest)</option>
                    <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>Oldest Submissions (Oldest to Newest)</option>
                    <option value="alphabetical" {{ request('sort') == 'alphabetical' ? 'selected' : '' }}>Alphabetical (A-Z)</option>
                </select>
            </div>

            <div class="btn-actions">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 20px; background-color: #2563eb; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">Filter</button>
                <a href="/public/inquiry-list" class="btn-reset">Reset</a>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th style="width: 8%; text-align: center;">No.</th>
                    <th style="width: 45%;">Inquiry Title</th>
                    <th style="width: 25%;">Submission Date</th>
                    <th style="width: 22%; text-align: center;">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($inquiries as $key => $inq)
                    <tr>
                        
                        <td style="text-align: center; font-weight: 600; color: #64748b;">
                            {{ ($inquiries->currentPage() - 1) * $inquiries->perPage() + $loop->iteration }}
                        </td>
                        <td>
                            <a href="{{ route('details.all.inquiry', ['id' => $inq->InquiryID]) }}">
                                {{ $inq->InquiryTitle }}
                            </a>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($inq->SubmissionDate)->format('Y-m-d H:i') }}</td>
                        
                        @php
                            $statusText = $inq->VerificationStatus
                                ?? ($inq->InvestigationBeginDate ? 'Under Investigation' : 'N/A');

                            // Menentukan kelas warna berdasarkan teks status
                            $badgeClass = 'badge-na';
                            if ($statusText === 'Under Investigation') {
                                $badgeClass = 'badge-investigation';
                            } elseif ($statusText === 'Verified as True' || $statusText === 'verified') {
                                $badgeClass = 'badge-verified';
                            } elseif ($statusText === 'Identified as Fake') {
                                $badgeClass = 'badge-fake';
                            }
                        @endphp
                        
                        <td style="text-align: center;">
                            <span class="status-badge {{ $badgeClass }}">
                                {{ $statusText }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 30px; color: #94a3b8;">No inquiries found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($inquiries->hasPages())
            <div class="pagination-wrapper">
                {{ $inquiries->appends(request()->query())->links() }}
            </div>
        @endif
    </div> 
    @endsection

   