<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Cast;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradeController extends Controller
{
    protected $gradeService;

    public function __construct(GradeService $gradeService)
    {
        $this->gradeService = $gradeService;
    }

    /**
     * Get grade information for a guest
     */
    public function getGuestGrade(Request $request, $guest_id): JsonResponse
    {
        $guest = Guest::findOrFail($guest_id);
        $gradeInfo = $this->gradeService->getGradeInfo($guest);

        return response()->json([
            'success' => true,
            'data' => $gradeInfo,
        ]);
    }

    /**
     * Update grade for a specific guest
     */
    public function updateGuestGrade(Request $request): JsonResponse
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
     * Get grade information for a cast
     */
    public function getCastGrade(Request $request, $cast_id): JsonResponse
    {
        $cast = Cast::findOrFail($cast_id);
        $gradeInfo = $this->gradeService->getCastGradeInfo($cast);

        return response()->json([
            'success' => true,
            'data' => $gradeInfo,
        ]);
    }

    /**
     * Update grade for a specific cast
     */
    public function updateCastGrade(Request $request): JsonResponse
    {
        $request->validate([
            'cast_id' => 'required|integer|exists:casts,id',
        ]);

        $cast = Cast::findOrFail($request->cast_id);
        $gradeUpdate = $this->gradeService->calculateAndUpdateCastGrade($cast);

        return response()->json([
            'success' => true,
            'data' => $gradeUpdate,
            'message' => $gradeUpdate['upgraded'] 
                ? 'Grade updated successfully!' 
                : 'Grade information updated.',
        ]);
    }

    /**
     * Get all grade information (thresholds, names, benefits)
     */
    public function getAllGradesInfo(): JsonResponse
    {
        $gradeInfo = $this->gradeService->getAllGradesInfo();

        return response()->json([
            'success' => true,
            'data' => $gradeInfo,
        ]);
    }

    /**
     * Get grade benefits for a specific grade
     */
    public function getGradeBenefits(Request $request): JsonResponse
    {
        $request->validate([
            'grade' => 'required|string|in:green,orange,bronze,silver,gold,platinum,centurion',
        ]);

        $benefits = $this->gradeService->getGradeBenefits($request->grade);

        return response()->json([
            'success' => true,
            'data' => [
                'grade' => $request->grade,
                'benefits' => $benefits,
            ],
        ]);
    }

    /**
     * Update grades for all guests (admin only)
     */
    public function updateAllGrades(): JsonResponse
    {
        $results = $this->gradeService->updateAllGuestGrades();

        return response()->json([
            'success' => true,
            'data' => $results,
            'message' => "Updated grades for {$results['total_guests']} guests. {$results['upgraded_guests']} guests were upgraded.",
        ]);
    }
} 