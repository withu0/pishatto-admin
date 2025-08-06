<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    protected $gradeService;

    public function __construct(GradeService $gradeService)
    {
        $this->gradeService = $gradeService;
    }

    /**
     * Display the grade management page
     */
    public function index(): Response
    {
        $guests = Guest::select('id', 'nickname', 'grade', 'grade_points', 'grade_updated_at', 'points')
            ->orderBy('grade_points', 'desc')
            ->paginate(20);

        $gradeStats = [
            'total_guests' => Guest::count(),
            'grade_distribution' => Guest::selectRaw('grade, COUNT(*) as count')
                ->groupBy('grade')
                ->pluck('count', 'grade')
                ->toArray(),
            'grade_info' => $this->gradeService->getAllGradesInfo(),
        ];

        return Inertia::render('Admin/Grades/Index', [
            'guests' => $guests,
            'gradeStats' => $gradeStats,
        ]);
    }

    /**
     * Update grades for all guests
     */
    public function updateAllGrades(Request $request)
    {
        $results = $this->gradeService->updateAllGuestGrades();

        return response()->json([
            'success' => true,
            'message' => "Updated grades for {$results['total_guests']} guests. {$results['upgraded_guests']} guests were upgraded.",
            'data' => $results,
        ]);
    }

    /**
     * Update grade for a specific guest
     */
    public function updateGuestGrade(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|integer|exists:guests,id',
        ]);

        $guest = Guest::findOrFail($request->guest_id);
        $gradeUpdate = $this->gradeService->calculateAndUpdateGrade($guest);

        return response()->json([
            'success' => true,
            'data' => $gradeUpdate,
            'message' => $gradeUpdate['upgraded'] 
                ? 'Grade updated successfully!' 
                : 'Grade information updated.',
        ]);
    }

    /**
     * Get grade statistics
     */
    public function getGradeStats()
    {
        $stats = [
            'total_guests' => Guest::count(),
            'grade_distribution' => Guest::selectRaw('grade, COUNT(*) as count')
                ->groupBy('grade')
                ->pluck('count', 'grade')
                ->toArray(),
            'grade_info' => $this->gradeService->getAllGradesInfo(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get guests by grade
     */
    public function getGuestsByGrade(Request $request)
    {
        $request->validate([
            'grade' => 'required|string|in:green,orange,bronze,silver,gold,platinum,centurion',
        ]);

        $guests = Guest::where('grade', $request->grade)
            ->select('id', 'nickname', 'grade', 'grade_points', 'grade_updated_at', 'points')
            ->orderBy('grade_points', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $guests,
        ]);
    }
} 