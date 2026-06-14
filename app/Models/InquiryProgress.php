<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InquiryProgress extends Model
{
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'inquiryprogress';

    // Set primary key if it's not the default id
    protected $primaryKey = 'StatusID';

    // Disable timestamps if the table doesn't use created_at and updated_at
    public $timestamps = false;

    // Fillable attributes for mass assignment
    protected $fillable = [
        'StatusID',
        'InquiryID',
        'AgencyID',
        'AssignmentID',
        'InvestigationBeginDate',
        'VerificationStatus',
        'VerificationDateTime',
        'InvestigationDetails',
        'InvestigationDoc',    // ✅ newly added
        'Notify',
    ];


    // Relationship to inquiry model
    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class, 'InquiryID');
    }

    // Relationship to agency model
    public function agency()
    {
        return $this->belongsTo(Agency::class, 'AgencyID');
    }

    // Relationship to inquiry assignment model
    public function inquiryAssignment()
    {
        return $this->belongsTo(InquiryAssignment::class, 'AssignmentID');
    }

    public function scopeSearch($query, $term)
    {
        return $query->whereHas('inquiry', function ($q) use ($term) {
            $q->where('InquiryTitle', 'like', "%{$term}%");
        });
    }

    /**
     * 2. Scope for Status Filtering
     * Specifically filters results by "VerificationStatus" as requested.
     */
    public function scopeFilterByStatus($query, $status)
    {
        return $query->where('VerificationStatus', $status);
    }

    /**
     * 3. Scope for Month Filtering
     * New feature to filter collections by "Filter by Month".
     */
    public function scopeFilterByMonth($query, $month)
    {
        return $query->whereHas('inquiry', function ($q) use ($month) {
            $q->whereMonth('SubmissionDate', $month);
        });
    }

    /**
     * 4. Scope for Interactive Sorting
     * Enables sorting by Title or Date to replace the static legacy table.
     */
    public function scopeSortInquiries($query, $sortType)
    {
        // Join with inquiries table to enable sorting by related table columns
        $query->join('inquiries', 'inquiryprogress.InquiryID', '=', 'inquiries.InquiryID')
              ->select('inquiryprogress.*');

        return match ($sortType) {
            'oldest' => $query->orderBy('inquiries.SubmissionDate', 'asc'),
            'alphabetical' => $query->orderBy('inquiries.InquiryTitle', 'asc'),
            default => $query->orderBy('inquiries.SubmissionDate', 'desc'), // Newest first
        };
}
}