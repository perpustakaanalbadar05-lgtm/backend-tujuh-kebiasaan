<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            self::logAudit($model, 'insert');
        });

        static::updated(function ($model) {
            self::logAudit($model, 'update');
        });

        static::deleted(function ($model) {
            self::logAudit($model, 'delete');
        });
    }

    protected static function logAudit($model, $action)
    {
        // Hindari logging jika sedang menjalankan seeders atau via console tanpa Auth
        if (!Auth::check()) {
            return;
        }

        $oldValues = null;
        $newValues = null;

        if ($action === 'update') {
            $oldValues = json_encode($model->getOriginal());
            $newValues = json_encode($model->getChanges());
        } elseif ($action === 'insert') {
            $newValues = json_encode($model->getAttributes());
        } elseif ($action === 'delete') {
            $oldValues = json_encode($model->getAttributes());
        }

        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
