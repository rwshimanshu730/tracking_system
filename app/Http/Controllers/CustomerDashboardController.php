<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectBug;
use App\Models\ProjectFile;
use Illuminate\View\View;

class CustomerDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth('customer')->user();

        $baseQuery = Project::query()
            ->whereHas('customerMembers', function ($q) use ($user) {
                $q->where('customers.id', $user->id);
            });

        $projects = (clone $baseQuery)
            ->withCount(['tasks', 'bugs', 'files'])
            ->latest()
            ->paginate(12);

        $projectIds = (clone $baseQuery)->pluck('id')->all();

        if (empty($projectIds)) {
            $totalTasks = 0;
            $tasksCompleted = 0;
            $totalBugs = 0;
            $totalFiles = 0;
            $avgProgress = 0;
        } else {
            $totalTasks = ProjectTask::whereIn('project_id', $projectIds)->count();
            $tasksCompleted = ProjectTask::whereIn('project_id', $projectIds)->where('progress', '>=', 100)->count();
            $totalBugs = ProjectBug::whereIn('project_id', $projectIds)->count();
            $totalFiles = ProjectFile::whereIn('project_id', $projectIds)->count();
            $avgProgressRaw = ProjectTask::whereIn('project_id', $projectIds)->avg('progress');
            $avgProgress = $avgProgressRaw ? round($avgProgressRaw) : 0;
        }

        return view('customers.dashboard', [
            'pageTitle' => 'Customer Dashboard',
            'projects' => $projects,
            'reports' => [
                'total_projects' => count($projectIds),
                'total_tasks' => $totalTasks,
                'tasks_completed' => $tasksCompleted,
                'total_bugs' => $totalBugs,
                'total_files' => $totalFiles,
                'avg_progress' => $avgProgress,
            ],
        ]);
    }
}
