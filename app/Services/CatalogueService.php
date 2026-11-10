<?php

namespace App\Services;

use App\Models\Forfait;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

class CatalogueService
{
    public function __construct(private readonly ActivityLogger $logger)
    {
    }

    /** @param array<string, mixed> $data */
    public function createService(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            $service = Service::create($data);

            $this->logger->log('service.cree', $service, "Prestation créée : {$service->nom}", null, $data);

            return $service;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateService(Service $service, array $data): Service
    {
        return DB::transaction(function () use ($service, $data) {
            $service->fill($data);

            if ($service->isDirty()) {
                $new = $service->getDirty();
                $old = array_intersect_key($service->getOriginal(), $new);
                $service->save();

                $this->logger->log('service.modifie', $service, "Prestation modifiée : {$service->nom}", $old, $new);
            }

            return $service;
        });
    }

    public function setServiceActive(Service $service, bool $active): void
    {
        if ($service->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($service, $active) {
            $service->forceFill(['is_active' => $active])->save();

            $this->logger->log(
                $active ? 'service.active' : 'service.desactive',
                $service,
                ($active ? 'Prestation activée : ' : 'Prestation désactivée : ').$service->nom,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $serviceIds
     */
    public function createForfait(array $data, array $serviceIds): Forfait
    {
        return DB::transaction(function () use ($data, $serviceIds) {
            $forfait = Forfait::create($data);
            $forfait->services()->sync($serviceIds);

            $this->logger->log(
                'forfait.cree',
                $forfait,
                "Forfait créé : {$forfait->nom}",
                null,
                $data + ['services' => $serviceIds],
            );

            return $forfait;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $serviceIds
     */
    public function updateForfait(Forfait $forfait, array $data, array $serviceIds): Forfait
    {
        return DB::transaction(function () use ($forfait, $data, $serviceIds) {
            $forfait->fill($data);

            $old = [];
            $new = [];

            if ($forfait->isDirty()) {
                $new = $forfait->getDirty();
                $old = array_intersect_key($forfait->getOriginal(), $new);
                $forfait->save();
            }

            $before = $forfait->services()->pluck('services.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $after = collect($serviceIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($before !== $after) {
                $forfait->services()->sync($after);
                $old['services'] = $before;
                $new['services'] = $after;
            }

            if ($new !== []) {
                $this->logger->log('forfait.modifie', $forfait, "Forfait modifié : {$forfait->nom}", $old, $new);
            }

            return $forfait;
        });
    }

    public function setForfaitActive(Forfait $forfait, bool $active): void
    {
        if ($forfait->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($forfait, $active) {
            $forfait->forceFill(['is_active' => $active])->save();

            $this->logger->log(
                $active ? 'forfait.active' : 'forfait.desactive',
                $forfait,
                ($active ? 'Forfait activé : ' : 'Forfait désactivé : ').$forfait->nom,
            );
        });
    }
}