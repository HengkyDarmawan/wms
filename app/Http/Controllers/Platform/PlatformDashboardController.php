<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Enums\PaymentStatus;
use App\Domain\Platform\Models\Company;
use App\Domain\Platform\Models\SubscriptionPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Beranda Super Admin (17-platform-login §6.1): daftar company dengan status
 * company & langganan, masa berjalan, dan penanda data siap dihapus. Tidak ada
 * tautan ke data operasional company (BR-SUB-04).
 */
class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $cari = trim((string) $request->query('q', ''));

        return view('platform.dashboard', [
            'companies' => Company::query()
                ->with('subscription', 'plan')
                ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$cari.'%')
                    ->orWhere('code', 'like', '%'.$cari.'%')->orWhere('subdomain', 'like', '%'.$cari.'%')))
                ->orderBy('name')
                ->paginate(25)->withQueryString(),
            'pendingPayments' => SubscriptionPayment::query()->where('status', PaymentStatus::Pending->value)->count(),
            'cari' => $cari,
        ]);
    }
}
