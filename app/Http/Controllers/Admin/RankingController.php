<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ranking;
use App\Models\Cast;
use Illuminate\Http\Request;

class RankingController extends Controller
{
    public function getRankingData()
    {
        $rankings = Ranking::latest()
            ->get()
            ->map(function ($ranking) {
                $cast = null;
                if ($ranking->cast_id && $ranking->cast) {
                    $cast = $ranking->cast->nickname ?? $ranking->cast->phone;
                }

                return [
                    'id' => $ranking->id,
                    'cast' => $cast ?? 'Unknown',
                    'category' => $ranking->category,
                    'rank' => $ranking->rank,
                    'score' => $ranking->score,
                    'date' => $ranking->created_at ? $ranking->created_at->format('Y-m-d') : 'Unknown',
                ];
            });

        return response()->json(['rankings' => $rankings]);
    }
} 