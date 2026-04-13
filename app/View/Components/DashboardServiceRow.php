<?php

namespace App\View\Components;

use Illuminate\View\Component;

class DashboardServiceRow extends Component
{
    public $service;

    public string $dateDisplay;

    public string $displayName;

    public string $typeLabel;

    public string $routeName;

    public function __construct($service, string $dateDisplay)
    {
        $this->service = $service;
        $this->dateDisplay = $dateDisplay;
        $this->displayName = $this->resolveDisplayName();
        $this->typeLabel = $this->resolveTypeLabel();
        $this->routeName = $this->resolveRouteName();
    }

    public function render()
    {
        return view('components.dashboard-service-row');
    }

    private function resolveDisplayName(): string
    {
        $service_type = (int)$this->service->service_type;

        if ($service_type === 1) {
            return (string)$this->service->hostname;
        }

        if ($service_type === 2) {
            return (string)$this->service->main_domain;
        }

        if ($service_type === 3) {
            return (string)$this->service->reseller;
        }

        if ($service_type === 4) {
            return $this->service->domain . '.' . $this->service->extension;
        }

        if ($service_type === 5) {
            return (string)$this->service->name;
        }

        if ($service_type === 6) {
            return (string)$this->service->title;
        }

        return '';
    }

    private function resolveTypeLabel(): string
    {
        $labels = [
            1 => 'VPS',
            2 => 'Shared',
            3 => 'Reseller',
            4 => 'Domain',
            5 => 'Misc',
            6 => 'Seedbox',
        ];

        return $labels[(int)$this->service->service_type] ?? 'Service';
    }

    private function resolveRouteName(): string
    {
        $routes = [
            1 => 'servers.show',
            2 => 'shared.show',
            3 => 'reseller.show',
            4 => 'domains.show',
            5 => 'misc.show',
            6 => 'seedboxes.show',
        ];

        return $routes[(int)$this->service->service_type] ?? '/';
    }
}