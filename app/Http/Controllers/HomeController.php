<?php

namespace App\Http\Controllers;

use App\Models\DNS;
use App\Models\Home;
use App\Models\Labels;
use App\Models\Pricing;
use App\Models\Settings;
use App\Process;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;


class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $p = new Process();
        $p->startTimer();

        //Get & set the settings, 1 minute cache
        $settings = Settings::getSettings();
        Settings::setSettingsToSession($settings);

        //Check for past due date and refresh the due date if so:
        $due_soon = Home::doDueSoon(Home::dueSoonData());

        //Orders services most recently added first, cached with limit from settings
        $recently_added = Home::recentlyAdded();

        //Get count tally for each of the services type
        $service_count = Home::doServicesCount(Home::servicesCount());

        $selected_cost_filters = Home::normalizeCostFilter(Session::get('cost_filter'));
        $selected_billing_cycle_filter = Home::normalizeBillingCycleFilter(Session::get('cost_billing_cycle_filter'));

        //Get pricing for weekly, monthly, yearly, 2 yearly
        $pricing_breakdown = Home::breakdownPricingFiltered(
            Pricing::allPricing(),
            $selected_cost_filters,
            $selected_billing_cycle_filter
        );

        //Summary of servers specs
        $server_summary = Home::serverSummary();

        $p->stopTimer();

        $formatted_pricing = $this->formatPricingBreakdown($pricing_breakdown);

        $information = [
            'servers' => $service_count['servers'],
            'domains' => $service_count['domains'],
            'shared' => $service_count['shared'],
            'reseller' => $service_count['reseller'],
            'misc' => $service_count['other'],
            'seedbox' => $service_count['seedbox'],
            'labels' => Labels::labelsCount(),
            'dns' => DNS::dnsCount(),
            'total_services' => $service_count['total'],
            'total_inactive' => $pricing_breakdown['inactive_count'],
            'total_cost_weekly' => $formatted_pricing['total_cost_weekly'],
            'total_cost_monthly' => $formatted_pricing['total_cost_monthly'],
            'total_cost_yearly' => $formatted_pricing['total_cost_yearly'],
            'total_cost_2_yearly' => $formatted_pricing['total_cost_2_yearly'],
            'due_soon' => $due_soon,
            'newest' => $recently_added,
            'execution_time' => number_format($p->getTimeTaken(), 2),
            'servers_summary' => $server_summary,
            'currency' => Session::get('dashboard_currency'),
            'cost_filters' => Home::costFilterOptions(),
            'selected_cost_filters' => $selected_cost_filters,
            'billing_cycle_filters' => Home::billingCycleFilterOptions(),
            'selected_billing_cycle_filter' => $selected_billing_cycle_filter,
        ];

        return view('home', compact('information'));
    }

    public function filterCosts(Request $request)
    {
        $validated = $request->validate([
            'service_types' => ['nullable', 'array'],
            'service_types.*' => ['integer', Rule::in(array_keys(Home::costFilterOptions()))],
            'billing_cycle' => ['nullable', 'string', Rule::in(array_keys(Home::billingCycleFilterOptions()))],
        ]);

        $selected_cost_filters = Home::normalizeCostFilter($validated['service_types'] ?? null);
        $selected_billing_cycle_filter = Home::normalizeBillingCycleFilter($validated['billing_cycle'] ?? null);
        Session::put('cost_filter', $selected_cost_filters);
        Session::put('cost_billing_cycle_filter', $selected_billing_cycle_filter);

        $pricing_breakdown = Home::breakdownPricingFiltered(
            Pricing::allPricing(),
            $selected_cost_filters,
            $selected_billing_cycle_filter
        );

        return response()->json(array_merge(
            $this->formatPricingBreakdown($pricing_breakdown),
            [
                'currency' => Session::get('dashboard_currency'),
                'selected_cost_filters' => $selected_cost_filters,
                'selected_billing_cycle_filter' => $selected_billing_cycle_filter,
            ]
        ));
    }

    private function formatPricingBreakdown(array $pricing_breakdown): array
    {
        return [
            'total_cost_weekly' => number_format($pricing_breakdown['total_cost_weekly'], 2),
            'total_cost_monthly' => number_format($pricing_breakdown['total_cost_monthly'], 2),
            'total_cost_yearly' => number_format($pricing_breakdown['total_cost_yearly'], 2),
            'total_cost_2_yearly' => number_format(($pricing_breakdown['total_cost_yearly'] * 2), 2),
        ];
    }
}
