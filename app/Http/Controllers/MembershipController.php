<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\MembershipBenefit;
use App\Models\MembershipPurchase;
use App\Models\MembershipSettings;
use App\Models\RewardItem;
use App\Models\User;
use App\Services\Sumopod\SumopodClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function index(): View
    {
        if (! MembershipSettings::enabled()) {
            return view('pages.membership.disabled');
        }

        $benefits = MembershipBenefit::orderBy('sort_order')->get();
        $settings = MembershipSettings::current();
        $user = Auth::user();
        $membership = $user?->membership;

        if ($membership && $membership->isActive()) {
            return view('pages.membership.active', [
                'membership' => $membership,
                'benefits' => $benefits,
                'pointsBalance' => $user->pointsBalance(),
                'items' => RewardItem::query()
                    ->where('active', true)
                    ->where(fn ($q) => $q->whereNull('stock')->orWhere('stock', '>', 0))
                    ->orderBy('sort_order')
                    ->get(),
            ]);
        }

        if ($membership && $membership->isLapsed()) {
            return view('pages.membership.lapsed', [
                'membership' => $membership,
                'settings' => $settings,
                'benefits' => $benefits,
                'pointsBalance' => $user->pointsBalance(),
                'forfeitAt' => $membership->expires_at->copy()->addDays($settings->grace_period_days),
            ]);
        }

        return view('pages.membership.paywall', [
            'settings' => $settings,
            'benefits' => $benefits,
        ]);
    }

    public function loginIntent(Request $request): RedirectResponse
    {
        $request->session()->put('membership_purchase_intent', true);

        return redirect()->route('register');
    }

    public function purchase(Request $request, SumopodClient $sumopodClient): RedirectResponse
    {
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verifikasi email dulu sebelum membeli membership.');

        $purchase = MembershipPurchase::create([
            'user_id' => $request->user()->id,
            'amount' => MembershipSettings::current()->price,
        ]);

        $payment = $sumopodClient->createPayment($purchase);

        $purchase->update(['sumopod_payment_link_url' => $payment['payment_link_url']]);

        return redirect()->to(route('membership.payment', $purchase));
    }

    public function payment(MembershipPurchase $purchase): View|RedirectResponse
    {
        abort_unless(auth()->id() === $purchase->user_id, 403);

        if ($purchase->status === 'paid') {
            return redirect()->route('membership');
        }

        return view('pages.membership.payment', ['purchase' => $purchase]);
    }

    public function bypass(MembershipPurchase $purchase): RedirectResponse
    {
        abort_unless(auth()->id() === $purchase->user_id, 403);

        if ($purchase->status !== 'pending_payment') {
            return redirect()->route('membership');
        }

        $purchase->update(['status' => 'paid', 'paid_at' => now()]);
        static::renewOrCreateMembership($purchase->user, MembershipSettings::current()->duration_days);

        return redirect()->route('membership');
    }

    public static function renewOrCreateMembership(User $user, int $durationDays): Membership
    {
        $membership = Membership::where('user_id', $user->id)->first();

        $base = $membership && $membership->expires_at->isFuture() ? $membership->expires_at : now();
        $newExpiry = $base->copy()->addDays($durationDays);

        if ($membership) {
            $membership->update(['expires_at' => $newExpiry, 'points_forfeited_at' => null]);

            return $membership;
        }

        return Membership::create(['user_id' => $user->id, 'expires_at' => $newExpiry]);
    }
}
