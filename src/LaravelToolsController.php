<?php

namespace franciscoblancojn\LaravelTools;

use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;

class LaravelToolsController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected Request $request;
    protected String $key_id = 'id';
    protected String $key_search = 'search';
    protected String $key_date = 'date';
    protected String $key_sort = 'sort';
    protected String $key_sort_direction = 'sort_direction';
    protected String $key_npage = 'npage';
    protected Int $default_npage = 10;
    protected $configQuery = [
        'sort' => ['created_at', 'updated_at'],
        'search' => ['name', 'description'],
        'types' => ['status'],
        'boolean' => [],
        'date' => ['created_at'],
    ];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function Respond(
        string $message,
        mixed $data,
        int $code = 200,
    ) {
        $this->request['message'] = $message;
        $this->request['code'] = $code;
        return $data;
    }
    public function getQueryStandar(Request $request, \Illuminate\Database\Eloquent\Builder $query)
    {
        $id = $request->input($this->key_id);
        if ($id) {
            $query->where($this->key_id, $id);
            return $query;
        }
        $search = $request->input($this->key_search);

        if ($search && !empty($this->configQuery['search'])) {

            $query->where(function ($q) use ($search) {

                foreach ($this->configQuery['search'] as $key => $value) {

                    // 🔹 Formato viejo ['name','email']
                    if (is_int($key)) {
                        $column = $value;
                    }
                    // 🔹 Formato nuevo ['search' => ['r.name','r.email']]
                    else {
                        if (is_array($value)) {
                            foreach ($value as $col) {
                                $q->orWhere($col, 'like', '%' . $search . '%');
                            }
                            continue;
                        }

                        $column = $value;
                    }

                    $q->orWhere($column, 'like', '%' . $search . '%');
                }
            });
        }

        foreach ($this->configQuery['types'] as $key => $value) {
            // 👇 Si es formato viejo ['status']
            if (is_int($key)) {
                $inputKey = $value;   // status
                $column   = $value;   // status
            }
            // 👇 Si es formato nuevo ['status' => 'r.status']
            else {
                $inputKey = $key;     // status
                $column   = $value;   // r.status
            }
            $types = $request->input($inputKey);

            if ($types) {
                $query->whereIn($column, explode(',', $types));
            }
        }
        foreach ($this->configQuery['boolean'] as $key => $value) {

            // 🔹 Formato viejo ['active']
            if (is_int($key)) {
                $inputKey = $value;
                $column   = $value;
            }
            // 🔹 Formato nuevo ['active' => 'r.active']
            else {
                $inputKey = $key;
                $column   = $value;
            }

            $bool = $request->input($inputKey);

            if (!is_null($bool)) {

                if (in_array($bool, [1, '1', true, 'true'], true)) {
                    $query->where($column, true);
                } elseif (in_array($bool, [0, '0', false, 'false'], true)) {
                    $query->where($column, false);
                }
            }
        }
        foreach ($this->configQuery['date'] as $key => $value) {

            // Formato viejo: ['created_at']
            if (is_int($key)) {
                $inputKey = $this->key_date; // 'date'
                $column   = $value;          // created_at
            }
            // Formato nuevo: ['date' => 'r.created_at']
            else {
                $inputKey = $key;            // date
                $column   = $value;          // r.created_at
            }

            $date       = $request->input($inputKey);
            $dateStart  = $request->input($inputKey . '_start');
            $dateEnd    = $request->input($inputKey . '_end');

            if ($dateStart) {
                $dateStart = Carbon::parse(urldecode($dateStart))->startOfDay();
                $query->where($column, '>=', $dateStart);
            }

            if ($dateEnd) {
                $dateEnd = Carbon::parse(urldecode($dateEnd))->endOfDay();
                $query->where($column, '<=', $dateEnd);
            }

            if ($date) {
                $query->whereDate($column, urldecode($date));
            }
        }
        $sort = $request->input($this->key_sort);

        if ($sort && !empty($this->configQuery['sort'])) {

            $direction = $request->input($this->key_sort_direction, 'asc');
            $direction = strtolower($direction);

            if (!in_array($direction, ['asc', 'desc'])) {
                $direction = 'asc';
            }
            foreach ($this->configQuery['sort'] as $key => $value) {

                // 🔹 Formato viejo ['created_at']
                if (is_int($key)) {
                    $inputKey = $value;   // created_at
                    $column   = $value;   // created_at
                }
                // 🔹 Formato nuevo ['created_at' => 'r.created_at']
                else {
                    $inputKey = $key;     // created_at
                    $column   = $value;   // r.created_at
                }

                if ($sort === $inputKey) {
                    $query->orderBy($column, $direction);
                    break; // evita múltiples orderBy innecesarios
                }
            }
        }

        return $query;
    }
    public function onPaginateStandar(Request $request, \Illuminate\Database\Eloquent\Builder $query)
    {
        $perPage = (int) $request->input($this->key_npage, $this->default_npage);
        return $query->paginate($perPage);
    }
    public function getQueryTotalStandar(\Illuminate\Database\Eloquent\Builder $query)
    {
        return $query->count();
    }
    public function getQuery(Request $request, \Illuminate\Database\Eloquent\Builder $query)
    {
        $query = $this->getQueryStandar($request, $query);
        /** @var LengthAwarePaginator $result */
        $result = $this->onPaginateStandar($request, $query);
        $total = $this->getQueryTotalStandar($query);
        return [
            'items' => $result->items(),
            'count' => $result->total(),
            'total' => $total,
        ];
    }
}
