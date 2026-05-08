<?php

namespace App\Http\Controllers\ExternalApi;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WareHouseController extends Controller
{
    protected $baseUrl;
    protected $login;
    protected $password;

    public function __construct()
    {
        $this->baseUrl = config('services.warehouse.api_url');
        $this->login = config('services.warehouse.login');
        $this->password = config('services.warehouse.password');
    }

    private function getToken()
    {
        return Cache::remember('warehouse_api_token', 3600, function () {
            $response = Http::post($this->baseUrl . 'login', [
                'email' => $this->login,
                'password' => $this->password,
            ]);

            if ($response->failed()) {
                throw new \Exception('Warehouse API login failed');
            }

            return $response->json()['token'];
        });
    }

    public function checkedQozoqPage(Request $request)
    {
        $token = $this->getToken();
        $query = $request->only([
            'boundary_name',
            'car_number',
            'phone',
            'date',
            'status',
            'company_name',
            'car_type',
            'page',
        ]);
        if ($request->filled('date')) {
            try {
                $query['date'] = Carbon::parse($request->date)->format('Y-m-d');
            } catch (\Exception $e) {
                $query['date'] = $request->date; // fallback
            }
        }
        $response = Http::withToken($token, 'Bearer')
            ->get($this->baseUrl . 'checked-qozoq', $query);

        if ($response->failed()) {
            $status = $response->status();
            $json = $response->json();

            throw new \Exception("Warehouse API request failed. Status: $status, Body: " . json_encode($json ?? 'No response body'));
        }

        $payload = $response->json();

        return view('warehouse.checked-qozoq', [
            'department' => $request->user()->department ?? null,
            'items' => $payload['data']['data'] ?? [],
            'pagination' => $payload['data'],
            'filters' => $query,
        ]);
    }
    public function chekedQozoqExport()
    {
        $token = $this->getToken();

        $response = Http::withToken($token, 'Bearer')
            ->get($this->baseUrl . 'checked-qozoq/export');

        if ($response->failed()) {
            $status = $response->status();
            $json = $response->json();

            throw new \Exception("Warehouse API export failed. Status: $status, Body: " . json_encode($json ?? 'No response body'));
        }

        return response($response->body(), 200)
            ->header('Content-Type', $response->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
            ->header('Content-Disposition', $response->header('Content-Disposition', 'attachment; filename=Export.xlsx'));
    }

    public function importQozoqPage(Request $request)
    {
        $token = $this->getToken();
        $query = $request->only([
            'boundary_name',
            'car_number',
            'phone',
            'date',
            'status',
            'company_name',
            'car_type',
            'page',
        ]);
        if ($request->filled('date')) {
            try {
                $query['date'] = Carbon::parse($request->date)->format('Y-m-d');
            } catch (\Exception $e) {
                $query['date'] = $request->date; 
            }
        }
        $response = Http::withToken($token, 'Bearer')
            ->get($this->baseUrl . 'import-qozoq', $query);

        if ($response->failed()) {
            $status = $response->status();
            $json = $response->json();

            throw new \Exception("Warehouse API request failed. Status: $status, Body: " . json_encode($json ?? 'No response body'));
        }

        $payload = $response->json();

        return view('warehouse.import-qozoq', [
            'items' => $payload['data']['data'] ?? [],
            'pagination' => $payload['data'],
            'filters' => $query,
        ]);
    }
    public function importQozoqExport()
    {
        $token = $this->getToken();

        $response = Http::withToken($token, 'Bearer')
            ->get($this->baseUrl . 'import-qozoq/export');

        if ($response->failed()) {
            $status = $response->status();
            $json = $response->json();

            throw new \Exception("Warehouse API export failed. Status: $status, Body: " . json_encode($json ?? 'No response body'));
        }

        return response($response->body(), 200)
            ->header('Content-Type', $response->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'))
            ->header('Content-Disposition', $response->header('Content-Disposition', 'attachment; filename=Import.xlsx'));
    }


    public function turkey(Request $request)
    {
        $token = $this->getToken();
        $query = $request->only([
            'ordinal_number',
            'input_sequence_number',
            'car_number',
            'phone',
            'date',
            'entrance',
            'company_name',
            'page',
        ]);
        if ($request->filled('date')) {
            try {
                $query['date'] = Carbon::parse($request->date)->format('Y-m-d');
            } catch (\Exception $e) {
                $query['date'] = $request->date; 
            }
        }
        $response = Http::withToken($token, 'Bearer')
            ->get($this->baseUrl . 'turkey', $query);

        if ($response->failed()) {
            $status = $response->status();
            $json = $response->json();

            throw new \Exception("Warehouse API request failed. Status: $status, Body: " . json_encode($json ?? 'No response body'));
        }

        $payload = $response->json();

        return view('warehouse.turkey', [
            'items' => $payload['data']['data'] ?? [],
            'pagination' => $payload['data'],
            'filters' => $query,
        ]);
    }
}
