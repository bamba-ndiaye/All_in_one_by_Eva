<?php

namespace App\Services;

use App\Models\Employe;
use Illuminate\Support\Facades\DB;

class EmployeService
{
    public function __construct(private readonly ActivityLogger $logger)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $serviceIds
     */
    public function create(array $data, array $serviceIds): Employe
    {
        return DB::transaction(function () use ($data, $serviceIds) {
            $employe = Employe::create($data);
            $employe->services()->sync($serviceIds);

            $this->logger->log(
                'employe.cree',
                $employe,
                "Employé créé : {$employe->nomComplet()}",
                null,
                $data + ['services' => $serviceIds],
            );

            return $employe;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $serviceIds
     */
    public function update(Employe $employe, array $data, array $serviceIds): Employe
    {
        return DB::transaction(function () use ($employe, $data, $serviceIds) {
            $employe->fill($data);

            $old = [];
            $new = [];

            if ($employe->isDirty()) {
                $new = $employe->getDirty();
                $old = array_intersect_key($employe->getOriginal(), $new);
                $employe->save();
            }

            $before = $employe->services()->pluck('services.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $after = collect($serviceIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($before !== $after) {
                $employe->services()->sync($after);
                $old['services'] = $before;
                $new['services'] = $after;
            }

            if ($new !== []) {
                $this->logger->log('employe.modifie', $employe, "Employé modifié : {$employe->nomComplet()}", $old, $new);
            }

            return $employe;
        });
    }

    public function setActive(Employe $employe, bool $active): void
    {
        if ($employe->is_active === $active) {
            return;
        }

        DB::transaction(function () use ($employe, $active) {
            $employe->forceFill(['is_active' => $active])->save();

            $this->logger->log(
                $active ? 'employe.active' : 'employe.desactive',
                $employe,
                ($active ? 'Employé activé : ' : 'Employé désactivé : ').$employe->nomComplet(),
            );
        });
    }

    /**
     * Remplace toutes les plages horaires de l'employé.
     *
     * @param  list<array{jour: int, debut: string, fin: string}>  $plages
     * @return bool true si quelque chose a changé
     */
    public function saveDisponibilites(Employe $employe, array $plages): bool
    {
        return DB::transaction(function () use ($employe, $plages) {
            $before = $employe->disponibilites()->get()
                ->map(fn ($d) => ['jour' => $d->jour_semaine->value, 'debut' => $d->debut(), 'fin' => $d->fin()])
                ->values()
                ->all();

            $after = collect($plages)
                ->map(fn ($p) => ['jour' => (int) $p['jour'], 'debut' => $p['debut'], 'fin' => $p['fin']])
                ->sortBy([['jour', 'asc'], ['debut', 'asc']])
                ->values()
                ->all();

            if ($before === $after) {
                return false;
            }

            $employe->disponibilites()->delete();
            $employe->disponibilites()->createMany(array_map(fn ($p) => [
                'jour_semaine' => $p['jour'],
                'heure_debut' => $p['debut'],
                'heure_fin' => $p['fin'],
            ], $after));

            $this->logger->log(
                'employe.horaires',
                $employe,
                "Horaires modifiés : {$employe->nomComplet()}",
                ['plages' => $before],
                ['plages' => $after],
            );

            return true;
        });
    }
}