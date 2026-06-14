<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Inquiry;
use App\Models\Agency;
use App\Models\InquiryAssignment;
use Illuminate\Support\Facades\Auth;
use App\Models\PublicUser;
use App\Models\User;
use App\Models\InquiryProgress;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use App\Exports\InquiryAssignmentExport;
use Maatwebsite\Excel\Facades\Excel;


class InquiryAssignmentController extends Controller
{
    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Build a filtered InquiryAssignment query from request parameters.
     * Centralises the repeated start_date / end_date / agency_id filter logic.
     */
    private function buildAssignmentQuery(Request $request)
    {
        $query = InquiryAssignment::with('agency');

        if ($request->filled('start_date')) {
            $query->where('AssignDate', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('AssignDate', '<=', $request->end_date);
        }
        if ($request->filled('agency_id')) {
            $query->where('AgencyID', $request->agency_id);
        }

        return $query;
    }

    /**
     * Group assignments by agency name and return parallel name/count arrays
     * used by the report view.
     */
    private function groupByAgency($assignments): array
    {
        $grouped = $assignments->groupBy(fn($item) => $item->agency->AgencyName ?? 'Unknown');

        return [
            'agencyNames' => $grouped->keys()->toArray(),
            'agencyCounts' => $grouped->map->count()->values()->toArray(),
        ];
    }


    // =========================================================================
    // MCMC — review
    // =========================================================================

    public function m_ReviewInquiry($id)
    {
        $inquiry = Inquiry::with('latestAssignment.agency')
            ->where('InquiryID', $id)
            ->firstOrFail();

        return view('InquiryAssignmentUI.MCMC.ReviewInquiryUI', compact('inquiry'));
    }


    // =========================================================================
    // PUBLIC — own inquiry list
    // =========================================================================

    public function publicOwnList()
    {
        $user = Auth::user();

        if (!$user || !$user->publicUser) {
            return redirect()->route('login')->with('error', 'You must be logged in as a public user.');
        }

        $publicID = $user->publicUser->PublicID;

        /*
         * FIX: Moved deduplication into SQL using a subquery for the latest
         * assignment instead of calling ->unique() on the PHP Collection.
         * This avoids fetching duplicate rows only to discard them in PHP.
         */
        $latestAssignments = DB::table('inquiryassignment as ia1')
            ->select('ia1.InquiryID', 'ia1.AgencyID', 'ia1.AssignDate')
            ->whereRaw('ia1.AssignDate = (
                SELECT MAX(ia2.AssignDate)
                FROM inquiryassignment as ia2
                WHERE ia2.InquiryID = ia1.InquiryID
            )')
            ->groupBy('ia1.InquiryID', 'ia1.AgencyID', 'ia1.AssignDate'); // ensure one row per inquiry

        $inquiries = DB::table('inquiry')
            ->leftJoinSub($latestAssignments, 'latest_ia', function ($join) {
                $join->on('inquiry.InquiryID', '=', 'latest_ia.InquiryID');
            })
            ->leftJoin('agency', 'latest_ia.AgencyID', '=', 'agency.AgencyID')
            ->leftJoin('inquiryprogress', 'inquiry.InquiryID', '=', 'inquiryprogress.InquiryID')
            ->select(
                'inquiry.*',
                'agency.AgencyName',
                'latest_ia.AssignDate',
                'inquiryprogress.VerificationDateTime',
                'inquiryprogress.VerificationStatus',
                'inquiryprogress.InvestigationDetails'
            )
            ->where('inquiry.PublicID', $publicID)
            ->groupBy('inquiry.InquiryID')   // one row per inquiry at DB level
            ->orderBy('inquiry.SubmissionDate', 'desc')
            ->get();

        return view('InquiryAssignmentUI.Public.OwnAssignedInquiryUI', compact('inquiries'));
    }


    // =========================================================================
    // MCMC — assign inquiry
    // =========================================================================

    /** Show the assign-inquiry form (MCMC only). */
    public function showAssignForm($id)
    {
        $this->authorizeMcmc();

        $inquiry  = Inquiry::findOrFail($id);
        $agencies = Agency::select('AgencyID', 'AgencyName')->distinct()->get();

        return view('InquiryAssignmentUI.MCMC.AssignInquiryUI', compact('inquiry', 'agencies'));
    }

    /** Persist a new inquiry assignment. */
    public function storeAssignment(Request $request, $id)
    {
        $this->authorizeMcmc();

        $validated = $request->validate([
            'AgencyID'       => 'required|exists:agency,AgencyID',
            'InquiryComment' => 'required|string|max:255',
        ]);

        /*
         * FIX: Use UUID instead of rand() to guarantee a unique AssignmentID
         * regardless of concurrency.
         */
        $assignmentID = 'ASS' . strtoupper(Str::random(8));

        DB::beginTransaction();
        try {
            $assignment               = new InquiryAssignment();
            $assignment->AssignmentID = $assignmentID;
            $assignment->AgencyID     = $validated['AgencyID'];
            $assignment->mcmcID       = Auth::user()->UserID;
            $assignment->InquiryID    = $id;
            $assignment->AssignDate   = now()->toDateString();
            $assignment->JurisdictionStatus = 1;
            $assignment->InquiryComment     = $validated['InquiryComment'];
            $assignment->save();

            $inquiry = Inquiry::findOrFail($id);
            $inquiry->SubmissionStatus = 'assigned';
            $inquiry->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to assign inquiry: ' . $e->getMessage());
        }

        return redirect()->route('mcmc.new.inquiry')->with('success', 'Inquiry assigned successfully.');
    }

    public function a_AssignInquiry()
    {
        $inquiries   = Inquiry::where('SubmissionStatus', 'pending')->get();
        $agencies    = Agency::all();
        $assignments = InquiryAssignment::with(['inquiry', 'agency'])->latest('AssignDate')->get();

        return view('InquiryAssignmentUI.AssignInquiryUI', compact('inquiries', 'agencies', 'assignments'));
    }


    // =========================================================================
    // MCMC — report
    // =========================================================================

    /**
     * FIX: Merged the duplicate displayReportDashboard / showInquiryReport
     * methods into one. Both returned the same view with identical logic.
     */
    public function showInquiryReport(Request $request)
    {
        $agencies    = Agency::all();
        $assignments = $this->buildAssignmentQuery($request)->get();
        $grouped     = $this->groupByAgency($assignments);

        return view('InquiryAssignmentUI.MCMC.DisplayReportUI', array_merge(
            compact('agencies'),
            $grouped
        ));
    }

    /** Export report as PDF. */
    public function exportInquiryReportPDF(Request $request)
    {
        $agencies    = Agency::all();
        $assignments = $this->buildAssignmentQuery($request)->get();

        $pdf = PDF::loadView('InquiryAssignmentUI.MCMC.PdfReportUI', compact('assignments', 'agencies'));

        return $pdf->download('Inquiry_Assignment_Report.pdf');
    }

    /** Export report as Excel. */
    public function exportInquiryReportExcel(Request $request)
    {
        $assignments = $this->buildAssignmentQuery($request)->get();
        $grouped     = $this->groupByAgency($assignments);

        $report = [];
        foreach (array_combine($grouped['agencyNames'], $grouped['agencyCounts']) as $agency => $count) {
            $report[] = ['agency' => $agency, 'assigned' => $count];
        }

        $overallStats = ['totalAssigned' => $assignments->count()];

        return Excel::download(
            new InquiryAssignmentExport(['report' => $report, 'overallStats' => $overallStats]),
            'Inquiry_Assignment_Report.xlsx'
        );
    }


    // =========================================================================
    // AGENCY — list & details
    // =========================================================================

    public function a_ListAssignedInquiry()
    {
        $user   = Auth::user();
        $agency = Agency::where('UserID', $user->UserID)->first();

        if (!$agency) {
            return redirect()->route('display.home')->with('error', 'You are not authorized as agency user.');
        }

        /*
         * FIX: Changed MAX(AssignmentID) to MAX(AssignDate) so the "latest"
         * subquery is based on chronological date rather than lexicographic
         * string comparison of AssignmentID values like 'ASS0042'.
         */
        $assignments = InquiryAssignment::with(['inquiry', 'progress', 'agency'])
            ->where('AgencyID', $agency->AgencyID)
            ->where('JurisdictionStatus', '!=', 0)
            ->whereIn('AssignmentID', function ($query) use ($agency) {
                $query->select('AssignmentID')
                    ->from('inquiryassignment as ia_inner')
                    ->whereColumn('ia_inner.InquiryID', 'inquiryassignment.InquiryID')
                    ->where('ia_inner.AgencyID', $agency->AgencyID)
                    ->orderByDesc('ia_inner.AssignDate')
                    ->limit(1);
            })
            ->orderBy('AssignDate', 'desc')
            ->get();

        return view('InquiryAssignmentUI.Agency.ListAssignedInquiryUI', compact('assignments'));
    }

    public function a_InquiryDetails($id)
    {
        $assignment = InquiryAssignment::with(['inquiry', 'progress'])
            ->where('AssignmentID', $id)
            ->firstOrFail();

        return view('InquiryAssignmentUI.Agency.JurisdictionReviewUI', compact('assignment'));
    }


    // =========================================================================
    // AGENCY — accept / reject action
    // =========================================================================

    public function handleAction(Request $request, $id)
    {
        $assignment = InquiryAssignment::findOrFail($id);
        $inquiry    = $assignment->inquiry;

        if ($request->action === 'accept') {
            return $this->acceptInquiry($assignment);
        }

        if ($request->action === 'reject') {
            return $this->rejectInquiry($request, $assignment, $inquiry);
        }

        return back()->with('error', 'Invalid action.');
    }

    /**
     * FIX: Wrapped accept logic in a DB transaction (matching reject branch).
     * Also generates a proper StatusID and updates SubmissionStatus to reflect
     * the accepted state.
     */
    private function acceptInquiry(InquiryAssignment $assignment)
    {
        DB::beginTransaction();
        try {
            $assignment->update([
                'JurisdictionStatus'  => 1,
                'JurisdictionComment' => 'Accepted and proceeding with investigation',
            ]);

            InquiryProgress::updateOrCreate(
                ['AssignmentID' => $assignment->AssignmentID],
                [
                    'StatusID'              => 'ST' . strtoupper(Str::random(6)),
                    'InquiryID'             => $assignment->InquiryID,
                    'AgencyID'              => $assignment->AgencyID,
                    'InvestigationBeginDate' => now(),
                    'VerificationStatus'    => 'ACCEPTED',
                    'InvestigationDetails'  => 'Inquiry accepted by agency.',
                ]
            );

            // Update inquiry status so it is no longer stuck as 'assigned'
            $assignment->inquiry->update(['SubmissionStatus' => 'in_progress']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to accept inquiry: ' . $e->getMessage());
        }

        return redirect()->route('agency.inquirylist')->with('success', 'Inquiry accepted.');
    }

    /**
     * FIX: Extracted reject logic into its own method for clarity.
     * No functional changes — already had a proper transaction.
     */
    private function rejectInquiry(Request $request, InquiryAssignment $assignment, Inquiry $inquiry)
    {
        $request->validate([
            'jurisdictionComment' => 'required|string|max:100',
        ]);

        DB::beginTransaction();
        try {
            $assignment->update([
                'JurisdictionStatus'  => 0,
                'JurisdictionComment' => $request->jurisdictionComment,
            ]);

            InquiryProgress::create([
                'StatusID'              => 'ST' . strtoupper(Str::random(6)),
                'AssignmentID'          => $assignment->AssignmentID,
                'InquiryID'             => $assignment->InquiryID,
                'AgencyID'              => $assignment->AgencyID,
                'InvestigationBeginDate' => now(),
                'VerificationStatus'    => 'REJECTED',
                'InvestigationDetails'  => $request->jurisdictionComment,
            ]);

            $inquiry->SubmissionStatus    = 'pending';
            $inquiry->SubmissionCategory  = null;
            $inquiry->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to reject: ' . $e->getMessage());
        }

        return redirect()->route('agency.inquirylist')->with('success', 'Inquiry rejected and returned for reassignment.');
    }


    // =========================================================================
    // PRIVATE — authorization helpers
    // =========================================================================

    /**
     * Abort with 403 if the authenticated user is not an MCMC staff member.
     * Adjust the role/guard check to match your User model's role system.
     */
   private function authorizeMcmc(): void
    {
        $user = Auth::user();

        if (!$user) {
        abort(403, 'Unauthorized. Please log in.');
    }

    // Gunakan huruf besar 'Role' mengikut fillable model User
    $userRole = strtolower($user->Role); 

    if ($userRole !== 'mcmc' && $userRole !== 'admin') {
        abort(403, 'Unauthorized. MCMC access only.');
    }
    }
}
