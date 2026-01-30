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
        if ($search) {
            foreach ($this->configQuery['search'] as $key) {
                $query->orWhere($key, 'like', '%' . $search . '%');
            }
        }
        foreach ($this->configQuery['types'] as $key) {
            $types  = $request->input($key);
            if ($types) {
                $query->whereIn($key, explode(',', $types));
            }
        }
        foreach ($this->configQuery['boolean'] as $key) {
            $bool  = $request->input($key);
            if (!is_null($bool)) {
                if (in_array($bool, [1, '1', true, 'true'], true)) {
                    $query->where($key, true);
                } elseif (in_array($bool, [0, '0', false, 'false'], true)) {
                    $query->where($key, false);
                }
            }
        }
        $date = $request->input($this->key_date);
        $dateStart = $request->input($this->key_date . '_start');
        $dateEnd = $request->input($this->key_date . '_end');
        if ($date || $dateStart || $dateEnd) {
            if ($dateStart) {
                $dateStart = Carbon::parse(urldecode($dateStart))->startOfDay();
            }
            if ($dateEnd) {
                $dateEnd = Carbon::parse(urldecode($dateEnd))->endOfDay();
            }
            if ($date) {
                $date = urldecode($date);
            }
            foreach ($this->configQuery['date'] as $key) {
                if ($dateStart) {
                    $query->where($key, '>=', $dateStart);
                }
                if ($dateEnd) {
                    $query->where($key, '<=', $dateEnd);
                }
                if ($date) {
                    $query->where($key, $date);
                }
            }
        }
        $sort = $request->input($this->key_sort);
        if ($sort) {
            $direction = $request->input($this->key_sort_direction, 'asc');
            if (!in_array($direction, ['asc', 'desc'])) {
                $direction = 'asc';
            }
            foreach ($this->configQuery['sort'] as $key) {
                if ($sort === $key) {
                    $query->orderBy($key, $direction);
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
