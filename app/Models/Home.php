<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class Home extends Model
{
    use HasFactory;

    public static function costFilterOptions(): array
    {
        return [
            1 => 'Servers',
            2 => 'Shared',
            3 => 'Reseller',
            4 => 'Domains',
            5 => 'Misc',
            6 => 'Seedboxes',
        ];
    }

    public static function normalizeCostFilter(?array $service_types): array
    {
        $available_types = array_keys(self::costFilterOptions());

        if (is_null($service_types)) {
            return $available_types;
        }

        $normalized_types = array_map('intval', $service_types);
        $filtered_types = array_values(array_intersect($available_types, array_unique($normalized_types)));

        return empty($filtered_types) ? $available_types : $filtered_types;
    }

    public static function billingCycleFilterOptions(): array
    {
        return [
            'all' => 'All cycles',
            'monthly' => 'Monthly only',
            'quarterly' => 'Quarterly only',
            'semi-annual' => 'Semi-annual only',
            'annual' => 'Annual-based',
        ];
    }

    public static function normalizeBillingCycleFilter(?string $billing_cycle): string
    {
        $available_cycles = array_keys(self::billingCycleFilterOptions());

        if (is_null($billing_cycle)) {
            return 'all';
        }

        $normalized_cycle = strtolower(trim($billing_cycle));

        return in_array($normalized_cycle, $available_cycles, true) ? $normalized_cycle : 'all';
    }

    public static function homePageCacheForget(): void
    {
        Cache::forget('services_count');//Main page services_count cache
        Cache::forget('due_soon');//Main page due_soon cache
        Cache::forget('recently_added');//Main page recently_added cache
        Cache::forget('all_pricing');//All the pricing
        Cache::forget('services_count_all');
        Cache::forget('pricing_breakdown');
    }

    public static function servicesCount()
    {
        return Cache::remember('services_count', now()->addHours(6), function () {
            return DB::table('pricings')
                ->select('service_type', DB::raw('COUNT(*) as amount'))
                ->groupBy('service_type')
                ->where('active', 1)
                ->get();
        });
    }

    public static function dueSoonData()
    {
        return Cache::remember('due_soon', now()->addHours(6), function () {
            return DB::table('pricings as p')
                ->leftJoin('servers as s', 'p.service_id', 's.id')
                ->leftJoin('shared_hosting as sh', 'p.service_id', 'sh.id')
                ->leftJoin('reseller_hosting as r', 'p.service_id', 'r.id')
                ->leftJoin('domains as d', 'p.service_id', 'd.id')
                ->leftJoin('misc_services as ms', 'p.service_id', 'ms.id')
                ->leftJoin('seedboxes as sb', 'p.service_id', 'sb.id')
                ->where('p.active', 1)
                ->orderBy('next_due_date', 'ASC')
                ->limit(Session::get('due_soon_amount'))
                ->get(['p.*', 's.hostname', 'd.domain', 'd.extension', 'r.main_domain as reseller', 'sh.main_domain', 'ms.name', 'sb.title']);
        });
    }

    public static function serverSummary()
    {
        return Cache::remember('servers_summary', now()->addHours(6), function () {
            $cpu_sum = DB::table('servers')->get()->where('active', 1)->sum('cpu');
            $ram_mb = DB::table('servers')->get()->where('active', 1)->sum('ram_as_mb');
            $disk_gb = DB::table('servers')->get()->where('active', 1)->sum('disk_as_gb');
            $bandwidth = DB::table('servers')->get()->where('active', 1)->sum('bandwidth');
            $locations_sum = DB::table('servers')->get()->where('active', 1)->groupBy('location_id')->count();
            $providers_sum = DB::table('servers')->get()->where('active', 1)->groupBy('provider_id')->count();
            return array(
                'cpu_sum' => $cpu_sum,
                'ram_mb_sum' => $ram_mb,
                'disk_gb_sum' => $disk_gb,
                'bandwidth_sum' => $bandwidth,
                'locations_sum' => $locations_sum,
                'providers_sum' => $providers_sum,
            );
        });
    }

    public static function recentlyAdded()
    {
        return Cache::remember('recently_added', now()->addHours(6), function () {
            return DB::table('pricings as p')
                ->leftJoin('servers as s', 'p.service_id', 's.id')
                ->leftJoin('shared_hosting as sh', 'p.service_id', 'sh.id')
                ->leftJoin('reseller_hosting as r', 'p.service_id', 'r.id')
                ->leftJoin('domains as d', 'p.service_id', 'd.id')
                ->leftJoin('misc_services as ms', 'p.service_id', 'ms.id')
                ->leftJoin('seedboxes as sb', 'p.service_id', 'sb.id')
                ->where('p.active', 1)
                ->orderBy('created_at', 'DESC')
                ->limit(Session::get('recently_added_amount'))
                ->get(['p.*', 's.hostname', 'd.domain', 'd.extension', 'r.main_domain as reseller', 'sh.main_domain', 'ms.name', 'sb.title']);
        });
    }

    public static function doDueSoon($due_soon)
    {
        $pricing = new Pricing();
        $count = $altered_due_soon = 0;
        $server_due_date_changed = false;
        foreach ($due_soon as $service) {
            if (Carbon::createFromFormat('Y-m-d', $service->next_due_date)->isPast()) {
                $months = $pricing->termAsMonths($service->term);//Get months for term to update the next due date to
                $new_due_date = Carbon::createFromFormat('Y-m-d', $service->next_due_date)->addMonths($months)->format('Y-m-d');
                DB::table('pricings')//Update the DB
                ->where('service_id', $service->service_id)
                    ->update(['next_due_date' => $new_due_date]);
                $due_soon[$count]->next_due_date = $new_due_date;//Update array being sent to view
                $altered_due_soon = 1;
                if ($service->service_type === 1) {
                    $server_due_date_changed = true;
                    Server::serverSpecificCacheForget($service->service_id);
                }
            } else {
                break;//Break because if this date isnt past than the ones after it in the loop wont be either
            }
            $count++;
        }

        if ($server_due_date_changed) {
            Server::serverRelatedCacheForget();
        }

        if ($altered_due_soon === 1) {//Made changes to due soon so re-write it
            Cache::put('due_soon', $due_soon);
        }

        return $due_soon;
    }

    public static function breakdownPricing($all_pricing): array
    {
        $pricing = json_decode($all_pricing, true);

        return Cache::remember('pricing_breakdown', now()->addWeek(1), function () use ($pricing) {
            return self::aggregatePricingBreakdown($pricing);
        });
    }

    public static function breakdownPricingFiltered($all_pricing, ?array $service_types = null, ?string $billing_cycle = null): array
    {
        $pricing = json_decode($all_pricing, true);
        $selected_types = self::normalizeCostFilter($service_types);
        $selected_billing_cycle = self::normalizeBillingCycleFilter($billing_cycle);

        if (count($selected_types) !== count(self::costFilterOptions())) {
            $pricing = array_values(array_filter($pricing, function ($price) use ($selected_types) {
                return in_array((int)$price['service_type'], $selected_types, true);
            }));
        }

        $term_filters = self::billingCycleTerms($selected_billing_cycle);

        if (!is_null($term_filters)) {
            $pricing = array_values(array_filter($pricing, function ($price) use ($term_filters) {
                return in_array((int)$price['term'], $term_filters, true);
            }));
        }

        return self::aggregatePricingBreakdown($pricing);
    }

    private static function aggregatePricingBreakdown(array $pricing): array
    {
        $total_cost_weekly = $total_cost_pm = $inactive_count = 0;

        foreach ($pricing as $price) {
            if ($price['active'] !== 1) {
                $inactive_count++;
                continue;
            }

            if (Session::get('dashboard_currency') !== 'USD') {
                $the_price = Pricing::convertFromUSD($price['as_usd'], Session::get('dashboard_currency'));
            } else {
                $the_price = $price['as_usd'];
            }

            $term_months = self::termInMonths((int)$price['term']);
            $total_cost_weekly += ($the_price / ($term_months * 4));
            $total_cost_pm += ($the_price / $term_months);
        }

        return array(
            'total_cost_weekly' => $total_cost_weekly,
            'total_cost_monthly' => $total_cost_pm,
            'total_cost_yearly' => ($total_cost_pm * 12),
            'inactive_count' => $inactive_count,
        );
    }

    private static function termInMonths(int $term): int
    {
        if ($term === 2) {
            return 3;
        }

        if ($term === 3) {
            return 6;
        }

        if ($term === 4) {
            return 12;
        }

        if ($term === 5) {
            return 24;
        }

        if ($term === 6) {
            return 36;
        }

        return 1;
    }

    private static function billingCycleTerms(string $billing_cycle): ?array
    {
        if ($billing_cycle === 'monthly') {
            return [1];
        }

        if ($billing_cycle === 'quarterly') {
            return [2];
        }

        if ($billing_cycle === 'semi-annual') {
            return [3];
        }

        if ($billing_cycle === 'annual') {
            return [4, 5, 6];
        }

        return null;
    }

    public static function doServicesCount($services_count): array
    {
        $services_count = json_decode($services_count, true);

        return Cache::remember('services_count_all', now()->addWeek(1), function () use ($services_count) {
            $servers_count = $domains_count = $shared_count = $reseller_count = $other_count = $seedbox_count = $total_services = 0;
            foreach ($services_count as $sc) {
                $total_services += $sc['amount'];
                if ($sc['service_type'] === 1) {
                    $servers_count = $sc['amount'];
                } else if ($sc['service_type'] === 2) {
                    $shared_count = $sc['amount'];
                } else if ($sc['service_type'] === 3) {
                    $reseller_count = $sc['amount'];
                } else if ($sc['service_type'] === 4) {
                    $domains_count = $sc['amount'];
                } else if ($sc['service_type'] === 5) {
                    $other_count = $sc['amount'];
                } else if ($sc['service_type'] === 6) {
                    $seedbox_count = $sc['amount'];
                }
            }

            return array(
                'servers' => $servers_count,
                'shared' => $shared_count,
                'reseller' => $reseller_count,
                'domains' => $domains_count,
                'other' => $other_count,
                'seedbox' => $seedbox_count,
                'total' => $total_services
            );
        });

    }


}
