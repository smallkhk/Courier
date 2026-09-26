<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminResources;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/** Generic, audited CRUD for the configuration entities in AdminResources. */
class ResourceController extends Controller
{
    public function index(Request $request, string $resource)
    {
        $def = AdminResources::get($resource);
        $q = $def['model']::query()->with($def['with'] ?? [])->orderBy($def['order']);
        if ($request->filled('q')) {
            $first = array_key_first($def['fields']);
            $q->where(fn ($w) => $w->where($first, 'like', '%'.$request->string('q').'%')->orWhere(array_keys($def['fields'])[1], 'like', '%'.$request->string('q').'%'));
        }

        return view('admin.resource.index', ['def' => $def, 'resource' => $resource, 'rows' => $q->paginate(50)->withQueryString()]);
    }

    public function create(string $resource)
    {
        $def = AdminResources::get($resource);
        $model = new $def['model']($def['defaults'] ?? []);

        return view('admin.resource.form', ['def' => $def, 'resource' => $resource, 'model' => $model]);
    }

    public function store(Request $request, string $resource)
    {
        $def = AdminResources::get($resource);
        $model = new $def['model'];
        $this->save($request, $def, $model);
        Audit::log("config.{$resource}.created", $model, ['values' => $model->getAttributes()]);

        return redirect()->route('admin.resource.index', $resource)->with('success', ucfirst($def['singular']).' created.');
    }

    public function edit(string $resource, int $id)
    {
        $def = AdminResources::get($resource);

        return view('admin.resource.form', ['def' => $def, 'resource' => $resource, 'model' => $def['model']::findOrFail($id)]);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $def = AdminResources::get($resource);
        $model = $def['model']::findOrFail($id);
        $changed = $this->save($request, $def, $model);
        Audit::log("config.{$resource}.updated", $model, ['changed' => $changed]);

        return redirect()->route('admin.resource.index', $resource)->with('success', ucfirst($def['singular']).' saved.');
    }

    public function destroy(string $resource, int $id)
    {
        $def = AdminResources::get($resource);
        $model = $def['model']::findOrFail($id);
        try {
            $model->delete();
        } catch (QueryException) {
            return back()->with('error', 'This '.$def['singular'].' is in use and cannot be deleted. Mark it inactive instead.');
        }
        Audit::log("config.{$resource}.deleted", null, ['id' => $id, 'values' => $model->getAttributes()]);

        return back()->with('success', ucfirst($def['singular']).' deleted.');
    }

    /** @return list<string> changed fields */
    private function save(Request $request, array $def, Model $model): array
    {
        $rules = [];
        foreach ($def['fields'] as $name => $f) {
            $rules[$name] = str_replace('{id}', (string) ($model->getKey() ?? 'NULL'), $f[2]);
        }
        $input = $request->all();
        foreach ($def['fields'] as $name => $f) {
            if ($f[0] === 'bool') {
                $input[$name] = $request->boolean($name);
            }
        }
        $v = Validator::make($input, $rules);
        if (! empty($def['unique'])) {
            $v->after(function ($v) use ($def, $model, $input) {
                $q = $def['model']::query();
                foreach ($def['unique'] as $col) {
                    $q->where($col, $input[$col] ?? null);
                }
                if ($model->exists) {
                    $q->whereKeyNot($model->getKey());
                }
                if ($q->exists()) {
                    $v->errors()->add($def['unique'][0], 'This combination already exists.');
                }
            });
        }
        $data = $v->validate();

        $relations = [];
        foreach ($def['fields'] as $name => $f) {
            $val = $data[$name] ?? null;
            switch ($f[0]) {
                case 'relation':
                    $relations[$name] = array_map('intval', (array) $val);

                    continue 2;
                case 'list':
                    $val = $val ? array_values(array_filter(array_map('trim', explode(',', $val)))) : [];
                    break;
                case 'hours':
                    $val = collect((array) $val)->map(fn ($h) => trim((string) $h) ?: null)->all();
                    break;
                case 'closures':
                    $val = collect(preg_split('/\R/', (string) $val))->map('trim')->filter()->map(function ($line) {
                        [$date, $note] = array_pad(preg_split('/\s+/', $line, 2), 2, null);

                        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? ['date' => $date, 'note' => $note] : null;
                    })->filter()->values()->all();
                    break;
                case 'text':
                    if ($name === 'currency') {
                        $val = strtoupper($val);
                    }
                    break;
            }
            $model->{$name} = $val === '' ? null : $val;
        }
        $changed = array_keys($model->getDirty());
        $model->save();
        foreach ($relations as $rel => $ids) {
            if (['attached' => [], 'detached' => [], 'updated' => []] !== $model->{$rel}()->sync($ids)) {
                $changed[] = $rel;
            }
        }
        Cache::forget('courier.settings');

        return $changed;
    }
}
