<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\ProjectStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $year = $request->input('year', now()->year);

        // Revenue stats
        $revenue = $this->getRevenueStats($userId, $year);

        // Project stats
        $projects = $this->getProjectStats($userId);

        // Invoice stats
        $invoices = $this->getInvoiceStats($userId, $year);

        // Reminder stats
        $reminders = $this->getReminderStats($userId);

        return $this->success([
            'revenue' => $revenue,
            'projects' => $projects,
            'invoices' => $invoices,
            'reminders' => $reminders,
            'year' => $year,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRevenueStats(int $userId, int $year): array
    {
        $paidInvoices = Invoice::query()
            ->where('user_id', $userId)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('paid_at', $year);

        $totalRevenue = (float) $paidInvoices->sum('total');

        // Monthly breakdown
        $monthlyRevenue = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthTotal = Invoice::query()
                ->where('user_id', $userId)
                ->where('status', InvoiceStatus::Paid)
                ->whereYear('paid_at', $year)
                ->whereMonth('paid_at', $month)
                ->sum('total');

            $monthlyRevenue[] = [
                'month' => $month,
                'label' => now()->setMonth($month)->format('M'),
                'total' => (float) $monthTotal,
            ];
        }

        // Outstanding (unpaid invoices)
        $outstanding = Invoice::query()
            ->where('user_id', $userId)
            ->unpaid()
            ->sum('total');

        return [
            'total_year' => $totalRevenue,
            'outstanding' => (float) $outstanding,
            'monthly' => $monthlyRevenue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getProjectStats(int $userId): array
    {
        $query = Project::query()->where('user_id', $userId);

        return [
            'total' => $query->count(),
            'by_status' => [
                'draft' => $query->clone()->byStatus(ProjectStatus::Draft)->count(),
                'sent' => $query->clone()->byStatus(ProjectStatus::Sent)->count(),
                'accepted' => $query->clone()->byStatus(ProjectStatus::Accepted)->count(),
                'in_progress' => $query->clone()->byStatus(ProjectStatus::InProgress)->count(),
                'completed' => $query->clone()->byStatus(ProjectStatus::Completed)->count(),
                'declined' => $query->clone()->byStatus(ProjectStatus::Declined)->count(),
                'cancelled' => $query->clone()->byStatus(ProjectStatus::Cancelled)->count(),
            ],
            'active' => $query->clone()->active()->count(),
            'offers_pending' => $query->clone()->offers()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getInvoiceStats(int $userId, int $year): array
    {
        $query = Invoice::query()
            ->where('user_id', $userId)
            ->whereYear('issued_at', $year);

        $overdueAmount = Invoice::query()
            ->where('user_id', $userId)
            ->where('status', InvoiceStatus::Overdue)
            ->sum('total');

        return [
            'total_year' => $query->count(),
            'by_status' => [
                'draft' => $query->clone()->where('status', InvoiceStatus::Draft)->count(),
                'sent' => $query->clone()->where('status', InvoiceStatus::Sent)->count(),
                'paid' => $query->clone()->where('status', InvoiceStatus::Paid)->count(),
                'overdue' => $query->clone()->where('status', InvoiceStatus::Overdue)->count(),
                'cancelled' => $query->clone()->where('status', InvoiceStatus::Cancelled)->count(),
            ],
            'overdue_amount' => (float) $overdueAmount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getReminderStats(int $userId): array
    {
        $query = Reminder::withoutGlobalScope('user')
            ->where('user_id', $userId);

        return [
            'total_pending' => $query->clone()->pending()->count(),
            'overdue' => $query->clone()->overdue()->count(),
            'due_today' => $query->clone()->pending()
                ->whereDate('due_at', today())
                ->count(),
            'upcoming_7_days' => $query->clone()->upcoming(7)->count(),
            'by_priority' => [
                'high' => $query->clone()->pending()->where('priority', 'high')->count(),
                'normal' => $query->clone()->pending()->where('priority', 'normal')->count(),
                'low' => $query->clone()->pending()->where('priority', 'low')->count(),
            ],
        ];
    }
}
