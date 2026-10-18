<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        $numUsers = (int) env('PERF_USERS', 200);
        $numCollectors = (int) env('PERF_COLLECTORS', 50);
        $numOrders = (int) env('PERF_ORDERS', 1000);
        $numTips = (int) env('PERF_TIPS', 100);
        $numBlogs = (int) env('PERF_BLOGS', 100);

        $runId = substr((string) (int) (microtime(true) * 1000), -6);
        $now = now()->toDateTimeString();
        $defaultPassword = Hash::make('password123');

        $this->command?->info("=== Iniciando PerformanceSeeder ( lote #{$runId} ) ===");

        // 1. Catálogos base: rols, states, type_identifications
        $roleMap = $this->seedRoles($now);
        $stateMap = $this->seedStates($now);
        $typeIdMap = $this->seedTypeIdentifications($now);

        // 2. Niveles (levels)
        $levelIds = $this->seedLevels($now);

        // 3. Tipos de residuo (type_wastes)
        $typeWasteIds = $this->seedTypeWastes($now);

        // 4. Usuarios y Recolectores (users, collectors, collector_locations, user_levels)
        [$userIds, $collectorIds] = $this->seedUsersAndCollectors(
            $numUsers,
            $numCollectors,
            $runId,
            $defaultPassword,
            $roleMap,
            $stateMap,
            $typeIdMap,
            $levelIds,
            $now
        );

        // 5. Órdenes, asignaciones y residuos por orden (orders, collector_orders, order_type_wastes)
        $this->seedOrders(
            $numOrders,
            $userIds,
            $collectorIds,
            $typeWasteIds,
            $stateMap,
            $now
        );

        // 6. Tips y Blogs (tips, blogs)
        $this->seedTips($numTips, $runId, $now);
        $this->seedBlogs($numBlogs, $runId, $now);

        $this->command?->info('=== PerformanceSeeder completado exitosamente ===');
    }

    private function seedRoles(string $now): array
    {
        if (!Schema::hasTable('rols')) {
            return ['Administrador' => 1, 'Usuario' => 2, 'Recolector' => 3];
        }

        $hasTimestamps = Schema::hasColumn('rols', 'created_at');
        foreach (['Administrador', 'Usuario', 'Recolector'] as $roleName) {
            $exists = DB::table('rols')->where('name', $roleName)->exists();
            if (!$exists) {
                $row = ['name' => $roleName];
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                DB::table('rols')->insert($row);
            }
        }

        return DB::table('rols')->pluck('id', 'name')->all();
    }

    private function seedStates(string $now): array
    {
        if (!Schema::hasTable('states')) {
            return [
                'Habilitado' => 1,
                'Inhabilitado' => 2,
                'Pendiente' => 3,
                'Eliminado' => 4,
                'Creación de orden' => 5,
                'Asignada a recolector' => 6,
                'Inicio de servicio' => 7,
                'Recogido' => 8,
                'Orden finalizada' => 9,
                'Cancelada' => 10,
                'Reprogramada' => 11,
            ];
        }

        $states = [
            ['name' => 'Habilitado', 'color' => 'green'],
            ['name' => 'Inhabilitado', 'color' => 'red'],
            ['name' => 'Pendiente', 'color' => 'yellow'],
            ['name' => 'Eliminado', 'color' => 'gray'],
            ['name' => 'Creación de orden', 'color' => 'blue'],
            ['name' => 'Asignada a recolector', 'color' => 'orange'],
            ['name' => 'Inicio de servicio', 'color' => 'purple'],
            ['name' => 'Recogido', 'color' => 'teal'],
            ['name' => 'Orden finalizada', 'color' => 'green'],
            ['name' => 'Cancelada', 'color' => 'red'],
            ['name' => 'Reprogramada', 'color' => 'amber'],
        ];

        $hasTimestamps = Schema::hasColumn('states', 'created_at');
        foreach ($states as $st) {
            if (!DB::table('states')->where('name', $st['name'])->exists()) {
                $row = $st;
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                DB::table('states')->insert($row);
            }
        }

        return DB::table('states')->pluck('id', 'name')->all();
    }

    private function seedTypeIdentifications(string $now): array
    {
        if (!Schema::hasTable('type_identifications')) {
            return [1, 2, 3];
        }

        $types = ['Cédula de Ciudadanía', 'Cédula de Extranjería', 'Pasaporte'];
        $hasTimestamps = Schema::hasColumn('type_identifications', 'created_at');

        foreach ($types as $tName) {
            if (!DB::table('type_identifications')->where('name', $tName)->exists()) {
                $row = ['name' => $tName];
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                DB::table('type_identifications')->insert($row);
            }
        }

        return DB::table('type_identifications')->pluck('id')->all();
    }

    private function seedLevels(string $now): array
    {
        if (!Schema::hasTable('levels')) {
            return [1, 2, 3, 4, 5];
        }

        $levels = [
            ['name' => 'Semilla Verde', 'min_point' => 0, 'max_point' => 100],
            ['name' => 'Brote Ecológico', 'min_point' => 101, 'max_point' => 300],
            ['name' => 'Árbol Guardián', 'min_point' => 301, 'max_point' => 600],
            ['name' => 'Bosque Sostenible', 'min_point' => 601, 'max_point' => 1000],
            ['name' => 'Héroe del Planeta', 'min_point' => 1001, 'max_point' => 99999],
        ];

        $hasTimestamps = Schema::hasColumn('levels', 'created_at');
        foreach ($levels as $lvl) {
            if (!DB::table('levels')->where('name', $lvl['name'])->exists()) {
                $row = $lvl;
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                DB::table('levels')->insert($row);
            }
        }

        $this->command?->info('  -> Tabla levels poblada.');
        return DB::table('levels')->pluck('id')->all();
    }

    private function seedTypeWastes(string $now): array
    {
        if (!Schema::hasTable('type_wastes')) {
            return [1, 2, 3, 4, 5, 6];
        }

        $wastes = [
            ['name' => 'Plástico PET', 'points' => 15, 'is_active' => true],
            ['name' => 'Cartón Corrugado', 'points' => 10, 'is_active' => true],
            ['name' => 'Vidrio Transparente', 'points' => 12, 'is_active' => true],
            ['name' => 'Aluminio y Latas', 'points' => 25, 'is_active' => true],
            ['name' => 'Papel Archivo', 'points' => 8, 'is_active' => true],
            ['name' => 'Chatarra Electrónica', 'points' => 40, 'is_active' => true],
        ];

        $hasTimestamps = Schema::hasColumn('type_wastes', 'created_at');
        foreach ($wastes as $w) {
            if (!DB::table('type_wastes')->where('name', $w['name'])->exists()) {
                $row = $w;
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                DB::table('type_wastes')->insert($row);
            }
        }

        $this->command?->info('  -> Tabla type_wastes poblada.');
        return DB::table('type_wastes')->pluck('id')->all();
    }

    private function seedUsersAndCollectors(
        int $numUsers,
        int $numCollectors,
        string $runId,
        string $defaultPassword,
        array $roleMap,
        array $stateMap,
        array $typeIdMap,
        array $levelIds,
        string $now
    ): array {
        $userIds = [];
        $collectorIds = [];

        $enabledStateId = $stateMap['Habilitado'] ?? reset($stateMap) ?: 1;
        $userRoleId = $roleMap['Usuario'] ?? 2;
        $collectorRoleId = $roleMap['Recolector'] ?? 3;
        $defaultTypeId = $typeIdMap[0] ?? 1;

        if (Schema::hasTable('users')) {
            $hasCreatedAt = Schema::hasColumn('users', 'created_at');
            $hasPoints = Schema::hasColumn('users', 'points');
            $hasFirebase = Schema::hasColumn('users', 'firebase_token');

            $userRows = [];
            for ($i = 1; $i <= $numUsers; $i++) {
                $row = [
                    'name' => "Usuario Prueba {$i}",
                    'phone' => '300' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                    'identification' => '10' . $runId . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'rol_id' => $userRoleId,
                    'state_id' => $enabledStateId,
                    'type_identification_id' => $defaultTypeId,
                    'email' => "perf.user.{$runId}.{$i}@recycleud.test",
                    'image' => null,
                    'password' => $defaultPassword,
                ];
                if ($hasPoints) {
                    $row['points'] = ($i * 15) % 1200;
                }
                if ($hasFirebase) {
                    $row['firebase_token'] = "fcm_user_{$runId}_{$i}";
                }
                if ($hasCreatedAt) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                $userRows[] = $row;
            }

            foreach (array_chunk($userRows, 100) as $chunk) {
                DB::table('users')->insert($chunk);
            }

            $collectorUserRows = [];
            for ($j = 1; $j <= $numCollectors; $j++) {
                $row = [
                    'name' => "Recolector Prueba {$j}",
                    'phone' => '310' . str_pad((string) $j, 7, '0', STR_PAD_LEFT),
                    'identification' => '20' . $runId . str_pad((string) $j, 4, '0', STR_PAD_LEFT),
                    'rol_id' => $collectorRoleId,
                    'state_id' => $enabledStateId,
                    'type_identification_id' => $defaultTypeId,
                    'email' => "perf.collector.{$runId}.{$j}@recycleud.test",
                    'image' => null,
                    'password' => $defaultPassword,
                ];
                if ($hasPoints) {
                    $row['points'] = 0;
                }
                if ($hasFirebase) {
                    $row['firebase_token'] = "fcm_collector_{$runId}_{$j}";
                }
                if ($hasCreatedAt) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                $collectorUserRows[] = $row;
            }

            foreach (array_chunk($collectorUserRows, 100) as $chunk) {
                DB::table('users')->insert($chunk);
            }

            $userIds = DB::table('users')
                ->where('email', 'like', "perf.user.{$runId}.%")
                ->pluck('id')
                ->all();

            $collectorUserIds = DB::table('users')
                ->where('email', 'like', "perf.collector.{$runId}.%")
                ->pluck('id')
                ->all();

            $this->command?->info('  -> Tabla users poblada (' . count($userIds) . ' usuarios + ' . count($collectorUserIds) . ' recolectores).');
        } else {
            $userIds = range(1, max(1, $numUsers));
            $collectorUserIds = range($numUsers + 1, $numUsers + max(1, $numCollectors));
        }

        if (Schema::hasTable('collectors')) {
            $hasDocs = Schema::hasColumn('collectors', 'identification_document');
            $hasTimestamps = Schema::hasColumn('collectors', 'created_at');

            $collectorRows = [];
            foreach ($collectorUserIds as $idx => $cUserId) {
                $row = [
                    'user_id' => $cUserId,
                    'state_id' => $enabledStateId,
                ];
                if ($hasDocs) {
                    $row['identification_document'] = "https://storage.recycleud.test/docs/id_{$cUserId}.pdf";
                    $row['driving_license_document'] = "https://storage.recycleud.test/docs/lic_{$cUserId}.pdf";
                }
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                $collectorRows[] = $row;
            }

            foreach (array_chunk($collectorRows, 100) as $chunk) {
                DB::table('collectors')->insert($chunk);
            }

            $collectorIds = DB::table('collectors')
                ->orderByDesc('id')
                ->limit($numCollectors)
                ->pluck('id')
                ->all();

            $this->command?->info('  -> Tabla collectors poblada (' . count($collectorIds) . ' registros).');
        } else {
            $collectorIds = range(1, max(1, $numCollectors));
        }

        if (Schema::hasTable('collector_locations') && count($collectorIds) > 0) {
            $locRows = [];
            foreach ($collectorIds as $idx => $cId) {
                $locRows[] = [
                    'collector_id' => $cId,
                    'latitude' => round(4.60971 + (($idx % 30) * 0.0025), 6),
                    'longitude' => round(-74.08175 + (($idx % 30) * 0.0025), 6),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($locRows, 100) as $chunk) {
                DB::table('collector_locations')->insert($chunk);
            }
            $this->command?->info('  -> Tabla collector_locations poblada (' . count($locRows) . ' ubicaciones).');
        }

        if (Schema::hasTable('user_levels') && count($levelIds) > 0 && count($userIds) > 0) {
            $hasTimestamps = Schema::hasColumn('user_levels', 'created_at');
            $userLevelRows = [];
            foreach ($userIds as $idx => $uId) {
                $row = [
                    'user_id' => $uId,
                    'level_id' => $levelIds[$idx % count($levelIds)],
                ];
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                $userLevelRows[] = $row;
            }
            foreach (array_chunk($userLevelRows, 100) as $chunk) {
                DB::table('user_levels')->insertOrIgnore($chunk);
            }
            $this->command?->info('  -> Tabla user_levels poblada (' . count($userLevelRows) . ' asignaciones de nivel).');
        }

        return [$userIds, $collectorIds];
    }

    private function seedOrders(
        int $numOrders,
        array $userIds,
        array $collectorIds,
        array $typeWasteIds,
        array $stateMap,
        string $now
    ): void {
        if (!Schema::hasTable('orders') || count($userIds) === 0) {
            return;
        }

        $orderStateNames = [
            'Creación de orden',
            'Asignada a recolector',
            'Inicio de servicio',
            'Recogido',
            'Orden finalizada',
            'Reprogramada',
            'Cancelada',
        ];

        $orderStateIds = [];
        foreach ($orderStateNames as $name) {
            if (isset($stateMap[$name])) {
                $orderStateIds[$name] = $stateMap[$name];
            }
        }
        $fallbackStateId = reset($orderStateIds) ?: (reset($stateMap) ?: 1);

        $hasAddress = Schema::hasColumn('orders', 'address');
        $hasNotes = Schema::hasColumn('orders', 'notes');
        $hasTimestamps = Schema::hasColumn('orders', 'created_at');

        $orderRows = [];
        for ($i = 1; $i <= $numOrders; $i++) {
            $stateName = $orderStateNames[$i % count($orderStateNames)];
            $stateId = $orderStateIds[$stateName] ?? $fallbackStateId;
            $daysOffset = ($i % 30) - 15;
            $orderDate = now()->addDays($daysOffset)->format('Y-m-d H:i:s');

            $row = [
                'user_id' => $userIds[$i % count($userIds)],
                'latitude' => round(4.60000 + (($i % 80) * 0.0018), 6),
                'longitude' => round(-74.09000 + (($i % 80) * 0.0018), 6),
                'date' => $orderDate,
                'state_id' => $stateId,
            ];
            if ($hasAddress) {
                $row['address'] = 'Calle ' . (10 + ($i % 120)) . ' #' . (10 + ($i % 80)) . '-' . (10 + ($i % 50));
            }
            if ($hasNotes) {
                $row['notes'] = "Orden de prueba de rendimiento #{$i}";
            }
            if ($hasTimestamps) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            $orderRows[] = $row;
        }

        foreach (array_chunk($orderRows, 100) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $insertedOrderIds = DB::table('orders')
            ->orderByDesc('id')
            ->limit($numOrders)
            ->pluck('id')
            ->all();

        $this->command?->info('  -> Tabla orders poblada (' . count($insertedOrderIds) . ' órdenes).');

        if (Schema::hasTable('collector_orders') && count($collectorIds) > 0) {
            $hasCoTimestamps = Schema::hasColumn('collector_orders', 'created_at');
            $collectorOrderRows = [];

            foreach ($insertedOrderIds as $idx => $orderId) {
                // Asignar recolector al ~80% de las órdenes
                if ($idx % 5 !== 0) {
                    $row = [
                        'order_id' => $orderId,
                        'collector_id' => $collectorIds[$idx % count($collectorIds)],
                    ];
                    if ($hasCoTimestamps) {
                        $row['created_at'] = $now;
                        $row['updated_at'] = $now;
                    }
                    $collectorOrderRows[] = $row;
                }
            }

            foreach (array_chunk($collectorOrderRows, 100) as $chunk) {
                DB::table('collector_orders')->insert($chunk);
            }

            $this->command?->info('  -> Tabla collector_orders poblada (' . count($collectorOrderRows) . ' asignaciones).');
        }

        if (Schema::hasTable('order_type_wastes') && count($typeWasteIds) > 0) {
            $hasOtwTimestamps = Schema::hasColumn('order_type_wastes', 'created_at');
            $wasteRows = [];

            foreach ($insertedOrderIds as $idx => $orderId) {
                $itemsCount = ($idx % 2) + 1;
                for ($k = 0; $k < $itemsCount; $k++) {
                    $weight = round(1.5 + (($idx + $k) % 10) * 0.8, 2);
                    $points = (int) round($weight * 10);
                    $row = [
                        'order_id' => $orderId,
                        'type_waste_id' => $typeWasteIds[($idx + $k) % count($typeWasteIds)],
                        'weight' => $weight,
                        'points' => $points,
                    ];
                    if ($hasOtwTimestamps) {
                        $row['created_at'] = $now;
                        $row['updated_at'] = $now;
                    }
                    $wasteRows[] = $row;
                }
            }

            foreach (array_chunk($wasteRows, 100) as $chunk) {
                DB::table('order_type_wastes')->insert($chunk);
            }

            $this->command?->info('  -> Tabla order_type_wastes poblada (' . count($wasteRows) . ' ítems de residuo).');
        }
    }

    private function seedTips(int $numTips, string $runId, string $now): void
    {
        if (!Schema::hasTable('tips')) {
            return;
        }

        $hasIsActive = Schema::hasColumn('tips', 'is_active');
        $hasTimestamps = Schema::hasColumn('tips', 'created_at');

        $tipRows = [];
        for ($i = 1; $i <= $numTips; $i++) {
            $row = [
                'title' => "Tip ecológico #{$i} ({$runId})",
                'description' => "Separa tus residuos reciclables limpios y secos para optimizar el proceso de recolección #{$i}.",
            ];
            if ($hasIsActive) {
                $row['is_active'] = ($i % 10 !== 0);
            }
            if ($hasTimestamps) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            $tipRows[] = $row;
        }

        foreach (array_chunk($tipRows, 100) as $chunk) {
            DB::table('tips')->insert($chunk);
        }

        $this->command?->info("  -> Tabla tips poblada ({$numTips} tips).");
    }

    private function seedBlogs(int $numBlogs, string $runId, string $now): void
    {
        if (!Schema::hasTable('blogs')) {
            return;
        }

        $hasUpperTitle = Schema::hasColumn('blogs', 'Title');
        $hasIsActive = Schema::hasColumn('blogs', 'is_active');
        $hasUpperActive = Schema::hasColumn('blogs', 'IsActive');
        $hasTimestamps = Schema::hasColumn('blogs', 'created_at');

        $blogRows = [];
        for ($i = 1; $i <= $numBlogs; $i++) {
            if ($hasUpperTitle) {
                $row = [
                    'Title' => "Artículo ambiental #{$i} ({$runId})",
                    'Description' => "Guía práctica de economía circular y reciclaje universitario #{$i}.",
                    'Url' => "https://recycleud.test/blogs/articulo-{$runId}-{$i}",
                    'Image' => "https://storage.recycleud.test/blogs/img_{$i}.jpg",
                ];
            } else {
                $row = [
                    'title' => "Artículo ambiental #{$i} ({$runId})",
                    'description' => "Guía práctica de economía circular y reciclaje universitario #{$i}.",
                    'url' => "https://recycleud.test/blogs/articulo-{$runId}-{$i}",
                    'image' => "https://storage.recycleud.test/blogs/img_{$i}.jpg",
                ];
            }

            if ($hasIsActive) {
                $row['is_active'] = true;
            } elseif ($hasUpperActive) {
                $row['IsActive'] = true;
            }

            if ($hasTimestamps) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            $blogRows[] = $row;
        }

        foreach (array_chunk($blogRows, 100) as $chunk) {
            DB::table('blogs')->insert($chunk);
        }

        $this->command?->info("  -> Tabla blogs poblada ({$numBlogs} blogs).");
    }
}

